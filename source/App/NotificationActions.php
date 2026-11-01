<?php
declare(strict_types=1);

namespace Moves\App;

use Moves\Services\AppError;
use Moves\Services\AppUrl;
use Moves\Services\Csrf;
use Moves\Services\Notifications;
use Moves\Services\SessionAuth;

/** Abrir uma notificação (marca como lida e leva à tela) e marcar todas como lidas. */
final class NotificationActions
{
    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            AppError::render(403);
            return;
        }
        $target = AppUrl::section('notificacoes');
        if ($action === 'open') {
            $target = Notifications::open((int) $user['id'], (int) ($_POST['id'] ?? 0));
            if ($target === null) {
                AppError::render(404);
                return;
            }
        } elseif ($action === 'preferences') {
            Notifications::savePreferences((int) $user['id'], $_POST);
            $target = AppUrl::section('notificacoes', null, ['preferencias' => 1]);
        } else {
            $scope = Notifications::scope();
            if ($scope !== null) {
                Notifications::markAllRead($scope);
            }
        }
        header('Cache-Control: no-store');
        header('Location: ' . $target, true, 303);
    }
}
