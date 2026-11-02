<?php
/**
 * Barra de ferramentas do App, em todas as telas. O período só aparece onde a tela
 * realmente filtra por ele; Anual, só onde a tela aceita o ano inteiro.
 */
use Moves\Services\AppUrl;
use Moves\Services\Csrf;
use Moves\Services\Finance;
use Moves\Services\Notifications;
use Moves\Services\OrganizationContext;
use Moves\Services\PlatformBilling;
use Moves\Services\SessionAuth;

$toolbarSection = (string) ($toolbarSection ?? 'overview');
$toolbarEscape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$toolbarHasPeriod = in_array($toolbarSection, ['overview', 'pagar', 'movimentacoes', 'relatorios', 'planejamento', 'categorias', 'fluxo-de-caixa'], true);
$toolbarHasYear = in_array($toolbarSection, ['overview', 'pagar', 'movimentacoes', 'relatorios', 'planejamento', 'categorias'], true);
$toolbarPeriod = Finance::period($toolbarHasYear ? $_GET : array_diff_key($_GET, ['visao' => 1, 'ano' => 1]));
$toolbarKeep = array_diff_key($_GET, ['pagina' => 1, 'mes' => 1, 'visao' => 1, 'ano' => 1, 'inicio' => 1, 'fim' => 1, 'page' => 1]);
$toolbarLink = static fn (array $query): string => $toolbarEscape(AppUrl::section($toolbarSection, null, $toolbarKeep + $query));
$toolbarAlerts = [];
$toolbarUnread = 0;
$toolbarAlertsFailed = false;
$toolbarPlan = 'Consultar plano';
$toolbarToken = '';
$toolbarSession = null;
try {
    $toolbarToken = Csrf::token();
    $toolbarSession = SessionAuth::current();
    if ($toolbarSession !== null) {
        $toolbarOrganization = OrganizationContext::current();
        $toolbarScope = Notifications::scope();
        $toolbarAlerts = $toolbarScope === null ? [] : Notifications::refresh($toolbarScope);
        $toolbarUnread = Notifications::unreadCount($toolbarAlerts);
        $toolbarContract = PlatformBilling::currentContract((int) $toolbarSession['id'], $toolbarOrganization === null ? null : (int) $toolbarOrganization['id']);
        $toolbarPlan = $toolbarContract === null ? 'Sem assinatura registrada' : (string) $toolbarContract['plan_name'];
    }
} catch (Throwable $exception) {
    error_log('Realizze toolbar: ' . $exception->getMessage());
    $toolbarAlertsFailed = true;
}
$user = is_array($user ?? null) ? $user + (array) $toolbarSession : (array) $toolbarSession;
?>
<div class="toolbar">
<?php if ($toolbarHasPeriod): ?>
<nav class="months months-live" aria-label="Período">
<a href="<?= $toolbarLink($toolbarPeriod['previous']) ?>" aria-label="Período anterior">‹</a>
<a class="months-side" href="<?= $toolbarLink($toolbarPeriod['previous']) ?>" tabindex="-1"><?= $toolbarEscape($toolbarPeriod['previousLabel']) ?></a>
<strong><?= $toolbarEscape($toolbarPeriod['label']) ?></strong>
<a class="months-side" href="<?= $toolbarLink($toolbarPeriod['next']) ?>" tabindex="-1"><?= $toolbarEscape($toolbarPeriod['nextLabel']) ?></a>
<a href="<?= $toolbarLink($toolbarPeriod['next']) ?>" aria-label="Próximo período">›</a>
</nav>
<?php if ($toolbarHasYear): ?>
<nav class="period period-live" aria-label="Visão">
<a href="<?= $toolbarLink($toolbarPeriod['view'] === 'mensal' ? [] : $toolbarPeriod['other']) ?>"<?= $toolbarPeriod['view'] === 'mensal' ? ' aria-current="true"' : '' ?>>Mensal</a>
<a href="<?= $toolbarLink(['visao' => 'anual', 'ano' => $toolbarPeriod['start']->format('Y')]) ?>"<?= $toolbarPeriod['view'] === 'anual' ? ' aria-current="true"' : '' ?>>Anual</a>
</nav>
<?php endif; ?>
<?php endif; ?>
<details class="tool-menu">
<summary class="bell" aria-label="Notificações<?= $toolbarUnread > 0 ? ', ' . $toolbarUnread . ' não lidas' : '' ?>"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><?php if ($toolbarUnread > 0): ?><span class="bell-count" aria-hidden="true"><?= $toolbarUnread > 9 ? '9+' : $toolbarUnread ?></span><?php endif; ?></summary>
<div class="tool-panel tool-notes">
<strong class="tool-title">Notificações</strong>
<?php if ($toolbarAlerts === []): ?><p class="tool-empty"><?= $toolbarAlertsFailed ? 'Não foi possível carregar as notificações.' : 'Nenhuma notificação no momento.' ?></p><?php else: ?>
<ul class="tool-alerts">
<?php foreach (array_slice($toolbarAlerts, 0, 5) as $note): $token = $toolbarToken; $e = $toolbarEscape; $noteCompact = true; ?>
<li><?php require __DIR__ . '/notification-item.php'; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if ($toolbarUnread > 0 && Notifications::supported()): ?><form method="post" action="/minha-conta/notificacoes/lidas"><input type="hidden" name="csrf_token" value="<?= $toolbarEscape($toolbarToken) ?>"><button type="submit" class="tool-read-all">Marcar todas como lidas</button></form><?php endif; ?>
<a class="tool-more" href="<?= $toolbarEscape(AppUrl::section('notificacoes')) ?>">Ver todas<?= count($toolbarAlerts) > 5 ? ' (' . count($toolbarAlerts) . ')' : '' ?></a>
</div>
</details>
<details class="tool-menu">
<summary class="tool-avatar" aria-label="Menu da conta"><span class="avatar" aria-hidden="true"><?= $toolbarEscape((string) (require __DIR__ . '/profile-initial.php')) ?></span></summary>
<div class="tool-panel tool-account">
<p class="tool-who"><strong><?= $toolbarEscape($name ?? '') ?></strong><small><?= $toolbarEscape($user['email'] ?? '') ?></small></p>
<a href="<?= $toolbarEscape(AppUrl::section('perfil')) ?>">Minha conta</a>
<a href="<?= $toolbarEscape(AppUrl::section(OrganizationContext::current() !== null ? 'empresa' : 'configuracoes')) ?>">Configurações</a>
<a href="/minha-assinatura">Assinatura<small><?= $toolbarEscape($toolbarPlan) ?></small></a>
<a href="/suporte">Ajuda e suporte</a>
<form method="post" action="/sair"><input type="hidden" name="csrf_token" value="<?= $toolbarEscape($toolbarToken) ?>"><button type="submit">Sair</button></form>
</div>
</details>
</div>
