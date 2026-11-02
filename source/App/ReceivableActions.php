<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\Csrf;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use Moves\Services\SessionAuth;
use Throwable;

/** Endpoints web do Módulo 01; nenhuma identidade ou organização vem do navegador. */
final class ReceivableActions
{
    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            \Moves\Services\AppError::render(403);
            return;
        }
        $contextId = FinanceContext::currentId();
        if ($contextId === null || $contextId <= 0) {
            \Moves\Services\AppError::render(403);
            return;
        }

        try {
            $receivables = new Receivables($contextId);
            $entryId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $message = match ($action) {
                'settle' => $this->settle($receivables, $entryId, (int) $user['id']),
                'cancel' => $this->cancel($receivables, $entryId, (int) $user['id']),
                default => throw new InvalidArgumentException('Ação inválida.'),
            };
        } catch (InvalidArgumentException $exception) {
            $message = ['error', $exception->getMessage()];
        } catch (Throwable $exception) {
            error_log('Realizze receivables: ' . $exception->getMessage());
            $message = ['error', 'Não foi possível concluir a operação. Tente novamente.'];
        }

        $_SESSION['receivables_flash'] = $message;
        $query = [];
        foreach (['inicio', 'fim', 'situacao', 'busca', 'categoria', 'cliente', 'page'] as $name) {
            if (isset($_POST[$name]) && is_string($_POST[$name]) && $_POST[$name] !== '') {
                $query[$name] = mb_substr($_POST[$name], 0, 160);
            }
        }
        header('Cache-Control: no-store');
        header('Location: ' . \Moves\Services\AppUrl::section('receber', null, $query), true, 303);
    }

    private function settle(Receivables $receivables, int|false $entryId, int $userId): array
    {
        $result = $receivables->settle($this->requiredId($entryId), $_POST, $userId);
        return ['success', $result['duplicate']
            ? 'Esta baixa já havia sido processada.'
            : ($result['status'] === 'received' ? 'Recebimento concluído.' : 'Recebimento parcial registrado.')];
    }

    private function cancel(Receivables $receivables, int|false $entryId, int $userId): array
    {
        $receivables->cancel($this->requiredId($entryId), (string) ($_POST['reason'] ?? ''), $userId);
        return ['success', 'Recebimento cancelado com histórico preservado.'];
    }

    private function requiredId(int|false $id): int
    {
        if ($id === false) {
            throw new InvalidArgumentException('Recebimento inválido.');
        }
        return $id;
    }
}
