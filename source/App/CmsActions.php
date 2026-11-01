<?php
declare(strict_types=1);

namespace Moves\App;

use InvalidArgumentException;
use Moves\Services\CmsAdmins;
use Moves\Services\CmsAuth;
use Moves\Services\CmsContent as Content;
use Moves\Services\CmsMedia;
use Moves\Services\CmsSettings;
use Moves\Services\CmsSite;
use Moves\Services\Csrf;
use Moves\Services\Helpdesk;
use Moves\Services\HelpdeskNotifier;
use Moves\Services\PlatformBilling as Billing;
use Moves\Services\PlatformDirectory as Directory;
use Throwable;

/** Toda mutação do CMS: POST, CSRF, permissão no backend e resposta PRG. */
final class CmsActions
{
    /** ação => [permissão, método] */
    private const ACTIONS = [
        'page-save' => ['pages.manage', 'pageSave'], 'page-publish' => ['content.publish', 'pagePublish'], 'page-restore' => ['pages.manage', 'pageRestore'],
        'post-save' => ['content.manage', 'postSave'], 'post-publish' => ['content.publish', 'postPublish'], 'post-delete' => ['content.manage', 'postDelete'],
        'category-save' => ['content.manage', 'categorySave'], 'category-delete' => ['content.manage', 'categoryDelete'],
        'faq-save' => ['content.manage', 'faqSave'], 'faq-delete' => ['content.manage', 'faqDelete'],
        'media-upload' => ['media.manage', 'mediaUpload'], 'media-update' => ['media.manage', 'mediaUpdate'], 'media-delete' => ['media.manage', 'mediaDelete'],
        'menu-save' => ['menus.manage', 'menuSave'], 'menu-delete' => ['menus.manage', 'menuDelete'], 'seo-save' => ['seo.manage', 'seoSave'],
        'user-suspend' => ['users.manage', 'userSuspend'], 'company-status' => ['companies.manage', 'companyStatus'], 'company-support-access' => ['companies.manage', 'companySupportAccess'],
        'plan-save' => ['plans.manage', 'planSave'],
        'subscription-create' => ['subscriptions.manage', 'subscriptionCreate'], 'subscription-plan' => ['subscriptions.manage', 'subscriptionPlan'], 'subscription-cancel' => ['subscriptions.manage', 'subscriptionCancel'], 'subscription-renewal' => ['charges.manage', 'subscriptionRenewal'],
        'charge-paid' => ['charges.manage', 'chargePaid'], 'charge-failed' => ['charges.manage', 'chargeFailed'],
        'ticket-reply' => ['tickets.reply', 'ticketReply'], 'ticket-status' => ['tickets.reply', 'ticketStatus'], 'ticket-priority' => ['tickets.assign', 'ticketPriority'], 'ticket-assign' => ['tickets.assign', 'ticketAssign'], 'ticket-attachment-delete' => ['tickets.assign', 'ticketAttachmentDelete'], 'ticket-bulk-status' => ['tickets.reply', 'ticketBulkStatus'],
        'ticket-create' => ['tickets.reply', 'ticketCreate'], 'ticket-triage' => ['tickets.assign', 'ticketTriage'], 'ticket-time' => ['tickets.reply', 'ticketTime'],
        'quick-reply-save' => ['tickets.categories', 'quickReplySave'], 'quick-reply-delete' => ['tickets.categories', 'quickReplyDelete'],
        'ticket-category-save' => ['tickets.categories', 'ticketCategorySave'], 'sla-save' => ['notifications.configure', 'slaSave'], 'notifications-save' => ['notifications.configure', 'notificationsSave'],
        'notifications-read' => [null, 'notificationsRead'],
        'admin-grant' => ['admins.manage', 'adminGrant'], 'admin-update' => ['admins.manage', 'adminUpdate'], 'permissions-save' => ['permissions.manage', 'permissionsSave'],
        'settings-save' => ['settings.manage', 'settingsSave'],
    ];

    private array $admin;
    private int $actor;

    public function handle(string $name): void
    {
        header('Cache-Control: no-store');
        $admin = CmsAuth::current();
        if ($admin === null) {
            http_response_code(401);
            header('Location: /entrar', true, 303);
            return;
        }
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Sessão expirada. Volte e tente novamente.';
            return;
        }
        if (!isset(self::ACTIONS[$name])) {
            http_response_code(404);
            return;
        }
        [$permission, $method] = self::ACTIONS[$name];
        if ($permission !== null && !CmsAuth::can($admin, $permission)) {
            http_response_code(403);
            echo 'Sem permissão para esta ação.';
            return;
        }
        $this->admin = $admin;
        $this->actor = (int) $admin['id'];
        $back = $this->back();
        try {
            $result = $this->{$method}();
            $_SESSION['cms_flash'] = ['success', $result[0]];
            $back = $result[1] ?? $back;
        } catch (InvalidArgumentException $error) {
            $_SESSION['cms_flash'] = ['error', $error->getMessage()];
        } catch (Throwable $error) {
            error_log('Realizze CMS: ação ' . $name . ' falhou: ' . $error->getMessage());
            $_SESSION['cms_flash'] = ['error', 'Não foi possível concluir a operação.'];
        }
        header('Location: ' . $back, true, 303);
    }

    private function back(): string
    {
        $back = $_POST['back'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');
        $path = is_string($back) ? (string) parse_url($back, PHP_URL_PATH) : '/';
        $query = is_string($back) ? parse_url($back, PHP_URL_QUERY) : null;
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return '/';
        }
        return $path . (is_string($query) ? '?' . $query : '');
    }

    private function id(string $key = 'id'): int
    {
        $id = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Registro inválido.');
        }
        return $id;
    }

    private function optionalId(): ?int
    {
        return ($_POST['id'] ?? '') === '' ? null : $this->id();
    }

    private function str(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function pageSave(): array
    {
        $id = Content::savePage($this->optionalId(), $_POST, $this->actor);
        return ['Página salva.', '/paginas/' . $id];
    }

    private function pagePublish(): array
    {
        $publish = $this->str('publish') === '1';
        Content::setPageStatus($this->id(), $publish, $this->actor);
        return [$publish ? 'Página publicada.' : 'Página despublicada.'];
    }

    private function pageRestore(): array
    {
        Content::restoreRevision($this->id(), $this->id('revision_id'), $this->actor);
        return ['Versão restaurada como rascunho de edição.'];
    }

    private function postSave(): array
    {
        $input = $_POST;
        $cover = $_FILES['cover_file'] ?? null;
        if (is_array($cover) && ($cover['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $mediaId = CmsMedia::upload($cover, ['title' => $this->str('title')], $this->actor);
            $input['cover'] = CmsMedia::urlById($mediaId);
        }
        $id = Content::savePost($this->optionalId(), $input, $this->actor);
        $next = $this->str('publish_after');
        if ($next === '') {
            return ['Conteúdo salvo.', '/conteudos/' . $id];
        }
        if (!CmsAuth::can($this->admin, 'content.publish')) {
            throw new InvalidArgumentException('Conteúdo salvo, mas você não tem permissão para publicar.');
        }
        Content::setPostStatus($id, $next === '1', $this->actor);
        return [$next === '1' ? 'Conteúdo publicado.' : 'Conteúdo voltou para rascunho.', '/conteudos/' . $id];
    }

    private function postPublish(): array
    {
        $publish = $this->str('publish') === '1';
        Content::setPostStatus($this->id(), $publish, $this->actor);
        return [$publish ? 'Conteúdo publicado.' : 'Conteúdo despublicado.'];
    }

    private function postDelete(): array
    {
        Content::deletePost($this->id(), $this->actor);
        return ['Conteúdo excluído.', '/conteudos'];
    }

    private function categorySave(): array
    {
        Content::saveCategory($this->optionalId(), $_POST, $this->actor);
        return ['Categoria salva.'];
    }

    private function categoryDelete(): array
    {
        Content::deleteCategory($this->id(), $this->actor);
        return ['Categoria excluída. Os conteúdos dela ficaram sem categoria.'];
    }

    private function faqSave(): array
    {
        Content::saveFaq($this->optionalId(), $_POST, $this->actor);
        return ['Pergunta salva.'];
    }

    private function faqDelete(): array
    {
        Content::deleteFaq($this->id(), $this->actor);
        return ['Pergunta excluída.'];
    }

    private function mediaUpload(): array
    {
        CmsMedia::upload(is_array($_FILES['file'] ?? null) ? $_FILES['file'] : [], $_POST, $this->actor);
        return ['Arquivo enviado.'];
    }

    private function mediaUpdate(): array
    {
        CmsMedia::update($this->id(), $_POST, $this->actor);
        return ['Dados do arquivo salvos.'];
    }

    private function mediaDelete(): array
    {
        CmsMedia::delete($this->id(), $this->actor);
        return ['Arquivo excluído.'];
    }

    private function menuSave(): array
    {
        CmsSite::saveMenuItem($this->optionalId(), $_POST, $this->actor);
        return ['Item de menu salvo.'];
    }

    private function menuDelete(): array
    {
        CmsSite::deleteMenuItem($this->id(), $this->actor);
        return ['Item de menu excluído.'];
    }

    private function seoSave(): array
    {
        CmsSite::saveSeoRoute($_POST, $this->actor);
        return ['SEO salvo.'];
    }

    private function userSuspend(): array
    {
        $suspend = $this->str('suspend') === '1';
        Directory::setUserSuspended($this->id(), $suspend, $this->str('reason'), $this->admin);
        return [$suspend ? 'Usuário suspenso.' : 'Usuário reativado.'];
    }

    private function companyStatus(): array
    {
        $active = $this->str('active') === '1';
        Directory::setCompanyStatus($this->id(), $active, $this->str('reason'), $this->actor);
        return [$active ? 'Empresa reativada.' : 'Empresa inativada.'];
    }

    private function companySupportAccess(): array
    {
        $grant = $this->str('grant') === '1';
        Helpdesk::setOrganizationAccess($this->id(), $this->id('user_id'), $grant, $this->actor);
        return [$grant ? 'Acesso aos chamados da empresa concedido.' : 'Acesso aos chamados da empresa removido.'];
    }

    private function planSave(): array
    {
        $id = Billing::savePlan($this->optionalId(), $_POST, $this->actor);
        return ['Plano salvo.', '/planos/' . $id];
    }

    private function subscriptionCreate(): array
    {
        $id = Billing::createSubscription($_POST, $this->actor);
        return ['Assinatura criada. Ela só fica ativa quando o pagamento for confirmado.', '/assinaturas/' . $id];
    }

    private function subscriptionPlan(): array
    {
        Billing::changePlan($this->id(), $_POST, $this->actor);
        return ['Plano da assinatura alterado.'];
    }

    private function subscriptionCancel(): array
    {
        Billing::cancelSubscription($this->id(), $this->str('reason'), $this->actor);
        return ['Assinatura cancelada.'];
    }

    private function subscriptionRenewal(): array
    {
        $charge = Billing::issueRenewal($this->id(), $this->actor);
        return ['Cobrança de renovação disponível.', '/cobrancas/' . $charge];
    }

    private function chargePaid(): array
    {
        Billing::confirmPayment($this->id(), $this->str('reference'), $this->actor);
        return ['Pagamento confirmado.'];
    }

    private function chargeFailed(): array
    {
        Billing::registerFailure($this->id(), $this->str('note'), $this->actor);
        return ['Falha registrada.'];
    }

    private function ticket(): array
    {
        $ticket = Helpdesk::ticket($this->id());
        if ($ticket === null) {
            throw new InvalidArgumentException('Chamado não encontrado.');
        }
        return $ticket;
    }

    private function ticketReply(): array
    {
        $internal = $this->str('internal') === '1';
        Helpdesk::staffReply($this->ticket(), $this->admin, $this->str('body'), $internal, Helpdesk::uploadedFiles('attachments'));
        return [$internal ? 'Nota interna registrada.' : 'Resposta enviada ao cliente.'];
    }

    private function ticketStatus(): array
    {
        Helpdesk::changeStatus($this->ticket(), $this->str('status'), $this->admin);
        return ['Situação alterada.'];
    }

    private function ticketPriority(): array
    {
        Helpdesk::changePriority($this->ticket(), $this->str('priority'), $this->admin);
        return ['Prioridade alterada.'];
    }

    private function ticketAssign(): array
    {
        $assignee = $this->str('assignee_user_id') === '' ? null : $this->id('assignee_user_id');
        Helpdesk::assign($this->ticket(), $assignee, $this->admin);
        return ['Atribuição atualizada.'];
    }

    private function ticketAttachmentDelete(): array
    {
        Helpdesk::deleteAttachment($this->id(), $this->actor);
        return ['Anexo removido.'];
    }

    private function ticketCreate(): array
    {
        $ticket = Helpdesk::staffOpen($_POST, $this->admin);
        return ['Chamado ' . $ticket['protocol'] . ' aberto.', '/chamados/' . $ticket['id']];
    }

    private function ticketTriage(): array
    {
        Helpdesk::saveTriage($this->ticket(), $_POST, $this->admin);
        return ['Equipe e tags salvas.'];
    }

    private function ticketTime(): array
    {
        Helpdesk::addTime($this->ticket(), $_POST['minutes'] ?? null, $this->str('note'), $this->admin);
        return ['Tempo registrado.'];
    }

    private function ticketBulkStatus(): array
    {
        [$changed, $skipped] = Helpdesk::bulkStatus(is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [], $this->str('status'), $this->admin);
        return [$changed . ' chamado(s) alterado(s).' . ($skipped > 0 ? ' ' . $skipped . ' ficaram de fora por não permitir essa situação.' : '')];
    }

    private function quickReplySave(): array
    {
        Helpdesk::saveQuickReply($this->optionalId(), $_POST, $this->actor);
        return ['Resposta rápida salva.'];
    }

    private function quickReplyDelete(): array
    {
        Helpdesk::deleteQuickReply($this->id(), $this->actor);
        return ['Resposta rápida excluída.'];
    }

    private function ticketCategorySave(): array
    {
        Helpdesk::saveCategory($this->optionalId(), $_POST, $this->actor);
        return ['Categoria salva.'];
    }

    private function slaSave(): array
    {
        Helpdesk::saveSla($_POST, $this->actor);
        return ['Metas de atendimento salvas.'];
    }

    private function notificationsSave(): array
    {
        CmsSettings::saveNotifications($_POST, $this->actor);
        return ['Preferências de notificação salvas.'];
    }

    private function notificationsRead(): array
    {
        HelpdeskNotifier::markAllRead($this->actor, 'staff');
        return ['Notificações marcadas como lidas.'];
    }

    private function adminGrant(): array
    {
        CmsAdmins::grant($this->str('email'), $this->id('role_id'), $this->admin);
        return ['Administrador salvo.'];
    }

    private function adminUpdate(): array
    {
        CmsAdmins::update($this->id(), $this->id('role_id'), $this->str('status'), $this->admin);
        return ['Administrador atualizado.'];
    }

    private function permissionsSave(): array
    {
        CmsAdmins::saveMatrix(is_array($_POST['matrix'] ?? null) ? $_POST['matrix'] : [], $this->actor);
        return ['Permissões salvas.'];
    }

    private function settingsSave(): array
    {
        CmsSettings::save($_POST, $this->actor);
        return ['Configurações salvas.'];
    }
}
