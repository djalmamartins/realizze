<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\Csrf;
use Moves\Services\Helpdesk;
use Moves\Services\HelpdeskNotifier;
use Moves\Services\OrganizationContext;
use Moves\Services\SessionAuth;
use Throwable;

/** Ações do cliente no suporte. Usuário e empresa vêm da sessão; nunca do formulário. */
final class SupportActions
{
    public function handle(string $action, ?string $protocol = null): void
    {
        header('Cache-Control: no-store');
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
        }
        $userId = (int) $user['id'];
        $back = '/suporte';
        try {
            if ($action === 'open') {
                $organization = ($_POST['context'] ?? '') === 'organization' ? OrganizationContext::current() : null;
                try {
                    $ticket = Helpdesk::open($userId, $organization === null ? null : (int) $organization['id'], $_POST, Helpdesk::uploadedFiles('attachments'));
                } catch (InvalidArgumentException $error) {
                    $_SESSION['support_old'] = array_intersect_key($_POST, array_flip(['subject', 'body', 'category_id', 'priority', 'support_type', 'module_hint']));
                    $back = str_contains($error->getMessage(), 'registrada') ? '/suporte' : '/suporte/novo';
                    throw $error;
                }
                $_SESSION['support_flash'] = ['success', 'Chamado ' . $ticket['protocol'] . ' aberto.'];
                $back = '/suporte/' . $ticket['protocol'];
            } elseif ($action === 'read-all') {
                HelpdeskNotifier::markAllRead($userId, 'customer');
            } else {
                $ticket = Helpdesk::customerTicket($userId, (string) $protocol);
                if ($ticket === null) {
                    http_response_code(404);
                    return;
                }
                $back = '/suporte/' . $ticket['protocol'];
                $message = match ($action) {
                    'reply' => $this->reply($userId, $ticket),
                    'resolve' => $this->then(static fn () => Helpdesk::customerResolve($userId, $ticket), 'Chamado marcado como resolvido.'),
                    'confirm' => $this->then(static fn () => Helpdesk::customerConfirm($userId, $ticket), 'Chamado encerrado.'),
                    'reopen' => $this->then(static fn () => Helpdesk::customerReopen($userId, $ticket), 'Chamado reaberto.'),
                    'rate' => $this->then(static fn () => Helpdesk::rate($userId, $ticket, $_POST), 'Obrigado pela avaliação.'),
                    default => throw new InvalidArgumentException('Ação inválida.'),
                };
                $_SESSION['support_flash'] = ['success', $message];
            }
        } catch (InvalidArgumentException $error) {
            $_SESSION['support_flash'] = ['error', $error->getMessage()];
        } catch (Throwable $error) {
            error_log('Realizze suporte: ação falhou.');
            $_SESSION['support_flash'] = ['error', 'Não foi possível concluir a operação.'];
        }
        header('Location: ' . $back, true, 303);
    }

    private function reply(int $userId, array $ticket): string
    {
        Helpdesk::customerReply($userId, $ticket, is_string($_POST['body'] ?? null) ? $_POST['body'] : '', Helpdesk::uploadedFiles('attachments'));
        return 'Resposta enviada.';
    }

    private function then(callable $work, string $message): string
    {
        $work();
        return $message;
    }

    public function voteHelp(string $uri): void
    {
        header('Cache-Control: no-store');
        $user = SessionAuth::current();
        if ($user === null || !Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
        }
        $article = \Moves\Services\CmsSite::helpArticle('uri', $uri);
        if ($article === null) {
            http_response_code(404);
            return;
        }
        \Moves\Services\CmsContent::voteHelp((int) $article['id'], (int) $user['id'], ($_POST['helpful'] ?? '') === '1');
        $_SESSION['support_flash'] = ['success', 'Obrigado pela sua avaliação.'];
        header('Location: /suporte/ajuda/' . rawurlencode($uri), true, 303);
    }

    public function download(int $attachmentId): void
    {
        $user = SessionAuth::current();
        $attachment = $user === null ? null : Helpdesk::attachment($attachmentId);
        // Mesmo 404 para inexistente e não autorizado.
        if ($attachment === null || !Helpdesk::customerCanDownload((int) $user['id'], $attachment)) {
            http_response_code(404);
            return;
        }
        Helpdesk::streamAttachment($attachment);
    }
}
