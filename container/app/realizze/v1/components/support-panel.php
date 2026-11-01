<?php
/**
 * Aba lateral de suporte. Sem JavaScript, o botão é um link comum para /suporte.
 * Fica fechada até o clique, para não cobrir as operações financeiras.
 */
use Moves\Services\CmsSettings; use Moves\Services\HelpdeskNotifier; use Moves\Services\SessionAuth; use Moves\Support\CmsView as V;
try {
    $panelUser = SessionAuth::current();
    if ($panelUser === null) { return; }
    $panelUnread = HelpdeskNotifier::unread((int) $panelUser['id'], 'customer');
    $panelSettings = CmsSettings::publicValues();
} catch (\Throwable $error) {
    error_log('Realizze suporte: aba lateral indisponível.');
    return;
}
$panelCss = BASE_PATH . '/public/assets/realizze-app/v1/css/support.min.css';
$panelJs = BASE_PATH . '/public/assets/realizze-app/v1/js/support.min.js';
$whatsapp = preg_match('/^\d{10,15}$/', (string) ($panelSettings['support_whatsapp'] ?? '')) === 1 ? 'https://wa.me/' . $panelSettings['support_whatsapp'] : '';
$siteUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
$suggestionCategory = '';
foreach (\Moves\Services\Helpdesk::categories(true) as $category) {
    if ($category['name'] === 'Sugestão') {
        $suggestionCategory = (string) $category['id'];
    }
}
$modules = ['Geral', 'Receber', 'Pagar', 'Movimentações', 'Cartões', 'Relatórios', 'Assinatura'];
?>
<link rel="stylesheet" href="/assets/realizze-app/v1/css/support.min.css?v=<?= (int) @filemtime($panelCss) ?>">
<a class="sp-tab" id="sp-tab" href="/suporte" aria-haspopup="dialog" aria-controls="sp-panel"><span>Suporte</span><?php if ($panelUnread > 0): ?><b aria-label="<?= (int) $panelUnread ?> novidades"><?= (int) $panelUnread ?></b><?php endif; ?></a>
<aside class="sp-panel" id="sp-panel" role="dialog" aria-label="Suporte Realizze" hidden>
 <header><button type="button" class="sp-icon" data-sp-back hidden aria-label="Voltar">←</button><h2 data-sp-title tabindex="-1">Suporte</h2><button type="button" class="sp-icon" data-sp-close aria-label="Fechar suporte">×</button></header>
 <section data-sp-view="home">
  <button type="button" class="sp-option" data-sp-go="service"><strong>Atendimento</strong><span>Envie dúvidas, solicitações ou sugestões.</span></button>
  <a class="sp-option" href="/suporte"><strong>Meus chamados<?= $panelUnread > 0 ? ' (' . (int) $panelUnread . ' novidades)' : '' ?></strong><span>Acompanhe situação, respostas e histórico.</span></a>
  <a class="sp-option" href="<?= V::e($siteUrl) ?>/blog" target="_blank" rel="noopener"><strong>Atualizações</strong><span>Acompanhe as novidades da plataforma.</span></a>
  <?php if ($suggestionCategory !== ''): ?><button type="button" class="sp-option" data-sp-go="form" data-sp-category="<?= V::e($suggestionCategory) ?>"><strong>Sugestões</strong><span>Envie uma ideia de novo recurso.</span></button><?php endif; ?>
  <?php if (!empty($panelSettings['support_email']) || !empty($panelSettings['contact_phone'])): ?><h3>Contato</h3><?php if (!empty($panelSettings['contact_phone'])): ?><p><?= V::e($panelSettings['contact_phone']) ?></p><?php endif; ?><?php if (!empty($panelSettings['support_email'])): ?><p><a href="mailto:<?= V::e($panelSettings['support_email']) ?>"><?= V::e($panelSettings['support_email']) ?></a></p><?php endif; ?><?php endif; ?>
  <?php if (!empty($panelSettings['support_hours'])): ?><h3>Expediente</h3><p><?= V::e($panelSettings['support_hours']) ?></p><?php endif; ?>
  <?php if ($whatsapp !== ''): ?><a class="sp-btn" href="<?= V::e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
 </section>
 <section data-sp-view="service" hidden>
  <h3>Tipo de atendimento</h3><div class="sp-toggle" role="group" aria-label="Tipo de atendimento"><button type="button" aria-pressed="true" data-sp-type="technical"><strong>Técnico</strong><span>Dúvidas e problemas de uso</span></button><button type="button" aria-pressed="false" data-sp-type="billing"><strong>Financeiro</strong><span>Assinatura e cobrança</span></button></div>
  <h3>Em qual módulo e tela?</h3><div class="sp-chips" role="group" aria-label="Módulo"><?php foreach ($modules as $i => $module): ?><button type="button" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" data-sp-module="<?= V::e($module) ?>"><?= V::e($module) ?></button><?php endforeach; ?></div>
  <h3>Veja os artigos relacionados</h3><p><a href="<?= V::e($siteUrl) ?>/#perguntas" target="_blank" rel="noopener">Perguntas frequentes</a></p>
  <button type="button" class="sp-btn sp-btn-primary" data-sp-go="form">Abrir chamado</button>
 </section>
 <section data-sp-view="form" hidden><?php $formId = 'sp-panel-form'; $supportOld = null; require __DIR__ . '/support-form.php'; ?></section>
</aside>
<script src="/assets/realizze-app/v1/js/support.min.js?v=<?= (int) @filemtime($panelJs) ?>" defer></script>
