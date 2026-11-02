<?php
declare(strict_types=1);

namespace Moves\App;

use Moves\Services\Csrf;
use Moves\Services\PersonalAccess;
use Moves\Services\SessionAuth;
use Moves\Services\TwoFactorAuth;
use Moves\Services\UserProfile;
use Moves\Services\UserSessions;

final class ProfileActions
{
    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        if ($user === null) { header('Location: /entrar', true, 302); return; }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            \Moves\Services\AppError::render(403); return;
        }
        $userId = (int) $user['id'];
        $tab = 'dados';
        try {
            $message = match ($action) {
                'update' => $this->update($userId),
                'password' => $this->password($userId),
                '2fa_begin' => $this->beginTwoFactor($userId),
                '2fa_confirm' => $this->confirmTwoFactor($userId),
                '2fa_disable' => $this->disableTwoFactor($userId),
                'session_revoke' => $this->revokeSession($userId),
                'sessions_revoke' => $this->revokeSessions($userId),
                'invite' => $this->invite($userId),
                'invite_revoke' => $this->revokeInvite($userId),
                'invite_accept' => $this->acceptInvite($userId, (string) $user['email']),
                'invite_decline' => $this->declineInvite($userId, (string) $user['email']),
                'notifications' => $this->notifications($userId),
                default => throw new \InvalidArgumentException('Ação inválida.'),
            };
            $tab = match (true) {
                str_starts_with($action, '2fa'), $action === 'password' => 'seguranca',
                str_contains($action, 'session') => 'sessoes',
                str_starts_with($action, 'invite') => 'permissoes',
                $action === 'notifications' => 'notificacoes',
                default => 'dados',
            };
            $_SESSION['profile_flash'] = ['success', $message];
        } catch (\InvalidArgumentException $e) {
            $_SESSION['profile_flash'] = ['error', $e->getMessage()];
            $tab = (string) ($_POST['profile_tab'] ?? $tab);
        } catch (\Throwable $e) {
            error_log('Realizze perfil: ' . $e->getMessage());
            $_SESSION['profile_flash'] = ['error', 'Não foi possível concluir a operação. Tente novamente.'];
        }
        header('Cache-Control: no-store');
        header('Location: /p/perfil?aba=' . rawurlencode($tab), true, 303);
    }

    private function update(int $id): string { UserProfile::update($id, $_POST); if (isset($_POST['remove_photo'])) UserProfile::removePhoto($id); UserProfile::updatePhoto($id, is_array($_FILES['photo'] ?? null) ? $_FILES['photo'] : []); return 'Dados atualizados com sucesso.'; }
    private function password(int $id): string { UserProfile::changePassword($id, (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? '')); return 'Senha alterada e outras sessões encerradas.'; }
    private function beginTwoFactor(int $id): string { $_SESSION['profile_2fa_setup'] = TwoFactorAuth::begin($id, (string) ($_POST['current_password'] ?? '')); return 'Leia o QR Code e confirme o código para concluir.'; }
    private function confirmTwoFactor(int $id): string { $_SESSION['profile_recovery_codes'] = TwoFactorAuth::confirm($id, (string) ($_POST['code'] ?? '')); unset($_SESSION['profile_2fa_setup']); return 'Autenticação em dois fatores ativada.'; }
    private function disableTwoFactor(int $id): string { TwoFactorAuth::disable($id, (string) ($_POST['current_password'] ?? ''), (string) ($_POST['code'] ?? '')); return 'Autenticação em dois fatores desativada.'; }
    private function revokeSession(int $id): string { if (!UserSessions::revoke($id, (int) ($_POST['session_id'] ?? 0))) throw new \InvalidArgumentException('A sessão não foi encontrada ou é a sessão atual.'); return 'Sessão encerrada.'; }
    private function revokeSessions(int $id): string { $count = UserSessions::revokeOthers($id); return $count === 1 ? '1 outra sessão encerrada.' : $count . ' outras sessões encerradas.'; }
    private function invite(int $id): string { $token = PersonalAccess::invite($id, (string) ($_POST['email'] ?? ''), (array) ($_POST['permissions'] ?? [])); $_SESSION['profile_invite_link'] = '/p/perfil/convite?token=' . $token; return 'Convite criado com validade de 7 dias.'; }
    private function revokeInvite(int $id): string { if (!PersonalAccess::revoke($id, (int) ($_POST['invite_id'] ?? 0))) throw new \InvalidArgumentException('Convite não encontrado.'); return 'Acesso revogado.'; }
    private function acceptInvite(int $id, string $email): string { $token=(string)($_SESSION['profile_pending_invite_token']??''); unset($_SESSION['profile_pending_invite_token']); if(!PersonalAccess::accept($id,$email,$token)) throw new \InvalidArgumentException('Convite inválido, expirado ou já utilizado.'); return 'Convite aceito.'; }
    private function declineInvite(int $id, string $email): string { $token=(string)($_SESSION['profile_pending_invite_token']??''); unset($_SESSION['profile_pending_invite_token']); if(!PersonalAccess::decline($id,$email,$token)) throw new \InvalidArgumentException('Convite inválido, expirado ou já utilizado.'); return 'Convite recusado.'; }
    private function notifications(int $id): string { UserProfile::saveNotificationPreferences($id, $_POST); return 'Preferências de notificação atualizadas.'; }
}
