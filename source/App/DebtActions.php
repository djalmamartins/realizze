<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\Csrf;
use Moves\Services\Debts;
use Moves\Services\FinanceContext;
use Moves\Services\SessionAuth;
use Throwable;

/** Mutações do Sair das Dívidas: POST, sessão, CSRF e somente no contexto pessoal. */
final class DebtActions
{
    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $userId = (int) $user['id'];
        $id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        try {
            // Módulo PF: grava sempre no contexto pessoal do usuário logado, nunca no da
            // empresa selecionada na sessão (evita gravar no lugar errado com abas PF e PJ abertas).
            $debts = new Debts(FinanceContext::forUser($userId));
            $message = match ($action) {
                'profile' => $this->profile($debts, $userId),
                'create' => [$debts->create($_POST, $userId) > 0 ? 'success' : 'error', 'Dívida cadastrada.'],
                'update' => $debts->update($id, $_POST) ? ['success', 'Dívida atualizada.'] : ['error', 'Dívida não encontrada.'],
                'archive' => $debts->archive($id) ? ['success', 'Dívida removida da lista.'] : ['error', 'Dívida não encontrada.'],
                'payment' => [$debts->registerPayment($id, $_POST, $userId) > 0 ? 'success' : 'error', 'Pagamento registrado e saldo atualizado.'],
                'negotiation' => [$debts->createNegotiation($id, $_POST, $userId) > 0 ? 'success' : 'error', 'Proposta registrada no histórico.'],
                'negotiation_status' => $debts->updateNegotiationStatus($id, (string) ($_POST['status'] ?? '')) ? ['success', 'Situação da negociação atualizada.'] : ['error', 'Negociação não encontrada.'],
                default => ['error', 'Ação inválida.'],
            };
        } catch (InvalidArgumentException $exception) {
            $message = ['error', $exception->getMessage()];
        } catch (Throwable $exception) {
            error_log('Realizze debts: ' . $exception->getMessage());
            $message = ['error', 'Não foi possível salvar. Tente novamente.'];
        }
        $_SESSION['finance_flash'] = $message;
        header('Cache-Control: no-store');
        header('Location: /p/sair-das-dividas', true, 303);
    }

    /** @return array{0:string,1:string} */
    private function profile(Debts $debts, int $userId): array
    {
        $debts->saveProfile($_POST, $userId);
        return ['success', 'Diagnóstico atualizado.'];
    }
}
