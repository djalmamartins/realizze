<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\Authorization;
use Moves\Services\Csrf;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\OrganizationContext;
use Moves\Services\Organizations;
use Moves\Services\SessionAuth;
use Throwable;

/** Mutações financeiras do app: exigem POST, sessão e CSRF; sempre no contexto atual. */
final class FinanceActions
{
    private const PAGES = ['income' => 'receber', 'expense' => 'pagar'];

    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $kind = in_array($_POST['kind'] ?? null, ['income', 'expense'], true) ? $_POST['kind'] : 'income';
        try {
            $organization = OrganizationContext::current();
            if ($organization !== null && $action !== 'company'
                && !(new Authorization())->can((int) $user['id'], (int) $organization['id'], 'finance.manage')) {
                \Moves\Services\AppError::render(403);
                return;
            }
            $finance = new Finance((int) FinanceContext::currentId());
            $id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $message = match ($action) {
                'create' => $this->create($finance, $kind, (int) $user['id']),
                'company' => $this->company((int) $user['id']),
                'category' => [$finance->createCategory($_POST) > 0 ? 'success' : 'error', 'Categoria criada.'],
                'category_delete' => $finance->deleteCategory($id) ? ['success', 'Categoria excluída. Os lançamentos ficaram sem categoria.'] : ['error', 'Categoria não encontrada.'],
                'budget' => $this->budget($finance),
                'goal' => [$finance->createGoal($_POST) > 0 ? 'success' : 'error', 'Meta criada.'],
                'goal_add' => $finance->addToGoal($id, $_POST['amount'] ?? null, (int) $user['id']) ? ['success', 'Valor adicionado à meta.'] : ['error', 'Meta não encontrada.'],
                'goal_delete' => $finance->deleteGoal($id) ? ['success', 'Meta excluída.'] : ['error', 'Meta não encontrada.'],
                'account' => [$finance->createAccount($_POST) > 0 ? 'success' : 'error', 'Conta cadastrada.'],
                'transfer' => [$finance->transfer($_POST, (int) $user['id']) > 0 ? 'success' : 'error', 'Transferência registrada.'],
                'settle' => $finance->settle($id) ? ['success', $kind === 'income' ? 'Recebimento confirmado.' : 'Pagamento confirmado.'] : ['error', 'Lançamento não encontrado.'],
                'delete' => $finance->delete($id) ? ['success', 'Lançamento excluído.'] : ['error', 'Lançamento não encontrado.'],
                default => ['error', 'Ação inválida.'],
            };
        } catch (InvalidArgumentException $exception) {
            $message = ['error', $exception->getMessage()];
        } catch (Throwable $exception) {
            error_log('Realizze finance: ' . $exception->getMessage());
            $message = ['error', 'Não foi possível salvar. Tente novamente.'];
        }
        $_SESSION['finance_flash'] = $message;
        $month = is_string($_POST['mes'] ?? null) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_POST['mes']) ? $_POST['mes'] : date('Y-m');
        header('Cache-Control: no-store');
        $page = in_array($_POST['back'] ?? null, ['contas', 'movimentacoes', 'overview', 'categorias', 'planejamento', 'metas'], true) ? $_POST['back'] : self::PAGES[$kind];
        header('Location: /minha-conta?' . ($page === 'overview' ? '' : 'pagina=' . $page . '&') . 'mes=' . $month, true, 303);
    }

    /** @return array{0:string,1:string} */
    private function budget(Finance $finance): array
    {
        $finance->saveBudget($_POST);
        return ['success', 'Orçamento salvo.'];
    }

    /** Exporta os lançamentos do período em CSV, abrível em planilhas. */
    public function export(): void
    {
        if (SessionAuth::current() === null) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $period = Finance::period($_GET);
        $rows = (new Finance((int) FinanceContext::currentId()))->exportRows($period['start']->format('Y-m-d'), $period['end']->format('Y-m-d'));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="realizze-' . $period['start']->format('Y-m') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Vencimento', 'Pagamento', 'Tipo', 'Descrição', 'Categoria', 'Conta', 'Conta de destino', 'Valor'], ';', '"', '');
        $types = ['income' => 'Receita', 'expense' => 'Despesa', 'transfer' => 'Transferência'];
        foreach ($rows as $row) {
            // Evita que planilhas interpretem textos do usuário como fórmulas.
            $safe = static fn (?string $text): string => preg_replace('/^([=+\-@\t\r])/', "'$1", (string) $text) ?? '';
            fputcsv($out, [
                $row['due_date'], $row['paid_at'], $types[$row['kind']], $safe($row['description']), $safe($row['category']),
                $safe($row['account']), $safe($row['target_account']), number_format((int) $row['amount_cents'] / 100, 2, ',', ''),
            ], ';', '"', '');
        }
        fclose($out);
    }

    /** @return array{0:string,1:string} */
    private function company(int $userId): array
    {
        $id = Organizations::create($userId, (string) ($_POST['name'] ?? ''), (string) ($_POST['cnpj'] ?? ''));
        OrganizationContext::select($id, $_POST['csrf_token'] ?? null);
        return ['success', 'Empresa cadastrada. Você já está nela.'];
    }

    /** @return array{0:string,1:string} */
    private function create(Finance $finance, string $kind, int $userId): array
    {
        $finance->createEntry($kind, $_POST, $userId);
        return ['success', $kind === 'income' ? 'Recebimento registrado.' : 'Despesa registrada.'];
    }
}
