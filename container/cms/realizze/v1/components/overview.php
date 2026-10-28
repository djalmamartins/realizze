<?php
/** CMS-UI-003: visão geral sem acesso a dados administrativos. */
$cmsMetrics = ['Páginas', 'Conteúdos', 'Arquivos de mídia', 'Publicações pendentes'];
?>
<div class="cms-heading"><div><span class="cms-eyebrow">Painel administrativo</span><h1>Visão geral</h1><p>Organize conteúdos, páginas e recursos do Realizze.</p></div><span class="cms-chip">Layout em desenvolvimento</span></div>
<section class="cms-kpis" aria-label="Indicadores não conectados">
<?php foreach ($cmsMetrics as $title): ?><article class="cms-card"><span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span><strong>—</strong><small>Dados indisponíveis</small></article><?php endforeach; ?>
</section>
<div class="cms-grid"><section class="cms-card"><h2>Conteúdos recentes</h2><p>Histórico de edições e publicações do site institucional.</p><ul class="cms-list"><li><div><strong>Nenhum conteúdo disponível</strong><small>A listagem será ativada após a integração segura.</small></div><span class="cms-status">Prévia</span></li></ul></section><section class="cms-card"><h2>Atividade da plataforma</h2><p>Auditoria de alterações, permissões e publicações.</p><div class="cms-skeleton"></div><div class="cms-skeleton"></div><div class="cms-skeleton"></div><p>Nenhum log administrativo é consultado nesta tela.</p></section></div>
<aside class="cms-notice"><strong>Prévia protegida: sem funções administrativas</strong>Este layout não permite login, edição, publicação ou leitura de dados privados. A rota permanece indisponível até a implantação da autenticação e das permissões específicas do CMS.</aside>
