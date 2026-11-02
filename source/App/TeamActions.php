<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\AppError;
use Moves\Services\AppUrl;
use Moves\Services\Csrf;
use Moves\Services\MailQueue;
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationTeam;
use Moves\Services\SessionAuth;
use Throwable;

/** Ações da equipe: sempre na empresa da sessão, com a permissão validada em OrganizationTeam. */
final class TeamActions
{
    public function handle(string $action): void
    {
        $user = SessionAuth::current();
        $organization = OrganizationContext::current();
        if ($user === null || $organization === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            AppError::render(403);
            return;
        }
        $orgId = (int) $organization['id'];
        $actor = (int) $user['id'];
        $id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        try {
            $message = match ($action) {
                'invite' => $this->invite($orgId, $actor, (string) $organization['name'], (string) ($_POST['email'] ?? ''), $role),
                'resend' => $this->resend($orgId, $actor, (string) $organization['name'], $id),
                'revoke' => $this->done(OrganizationTeam::revoke($orgId, $actor, $id), 'Convite cancelado.'),
                'role' => $this->done(OrganizationTeam::changeRole($orgId, $actor, $id, $role), 'Papel alterado.'),
                'suspend' => $this->done(OrganizationTeam::setActive($orgId, $actor, $id, false), 'Acesso suspenso.'),
                'activate' => $this->done(OrganizationTeam::setActive($orgId, $actor, $id, true), 'Acesso reativado.'),
                'remove' => $this->done(OrganizationTeam::remove($orgId, $actor, $id), 'Acesso revogado.'),
                default => throw new InvalidArgumentException('Ação inválida.'),
            };
        } catch (InvalidArgumentException $exception) {
            $message = ['error', $exception->getMessage()];
        } catch (Throwable $exception) {
            error_log('Realizze team: ' . $exception->getMessage());
            $message = ['error', 'Não foi possível concluir. Tente novamente.'];
        }
        $_SESSION['finance_flash'] = $message;
        header('Cache-Control: no-store');
        header('Location: ' . AppUrl::section('equipe'), true, 303);
    }

    private function done(mixed $_result, string $text): array
    {
        return ['success', $text];
    }

    private function invite(int $orgId, int $actor, string $company, string $email, string $role): array
    {
        $token = OrganizationTeam::invite($orgId, $actor, $email, $role);
        return $this->deliver(mb_strtolower(trim($email)), $company, $token);
    }

    private function resend(int $orgId, int $actor, string $company, int $id): array
    {
        $sent = OrganizationTeam::resend($orgId, $actor, $id);
        return $this->deliver($sent['email'], $company, $sent['token']);
    }

    /** O link fica disponível uma vez na tela, para o caso de o e-mail não chegar. */
    private function deliver(string $email, string $company, string $token): array
    {
        $link = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/convite-empresa?token=' . $token;
        $_SESSION['team_invite_link'] = $link;
        $safeCompany = htmlspecialchars($company, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        try {
            (new MailQueue())->enqueue(
                $email,
                'Convite para a empresa ' . mb_substr($company, 0, 200) . ' no Realizze',
                '<p>Você foi convidado para acessar <strong>' . $safeCompany . '</strong> no Realizze.</p><p><a href="' . $safeLink . '">Abrir convite</a></p><p>O convite vale por 7 dias.</p>',
                "Você foi convidado para acessar {$company} no Realizze.\n{$link}\nO convite vale por 7 dias."
            );
        } catch (Throwable $exception) {
            error_log('Realizze team mail: ' . $exception->getMessage());
            return ['warning', 'Convite criado, mas o e-mail não foi enviado. Envie o link abaixo à pessoa.'];
        }
        return ['success', 'Convite criado com validade de 7 dias.'];
    }
}
