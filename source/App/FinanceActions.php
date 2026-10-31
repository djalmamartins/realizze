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
            http_response_code(403);
            return;
        }
        $kind = in_array($_POST['kind'] ?? null, ['income', 'expense'], true) ? $_POST['kind'] : 'income';
        try {
            $organization = OrganizationContext::current();
            if ($organization !== null && $action !== 'company'
                && !(new Authorization())->can((int) $user['id'], (int) $organization['id'], 'finance.manage')) {
                http_response_code(403);
                return;
            }
            $finance = new Finance((int) FinanceContext::currentId());
            $id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $message = match ($action) {
                'create' => $this->create($finance, $kind, (int) $user['id']),
                'company' => $this->company((int) $user['id']),
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
        $page = in_array($_POST['back'] ?? null, ['contas', 'movimentacoes', 'overview'], true) ? $_POST['back'] : self::PAGES[$kind];
        header('Location: /minha-conta?' . ($page === 'overview' ? '' : 'pagina=' . $page . '&') . 'mes=' . $month, true, 303);
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
