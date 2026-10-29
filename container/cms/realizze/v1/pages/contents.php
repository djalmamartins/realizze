<?php
/** CMS content editor layout preview. No persistence, uploads or publishing. */
?>
<?php $cmsHeading = ['eyebrow' => 'Gerenciamento / Editorial', 'title' => 'Conteúdos', 'description' => 'Planeje artigos, comunicados e páginas editoriais.', 'badge' => 'Prévia visual · sem edição']; require __DIR__ . '/../components/page-heading.php'; ?>
<div class="cms-editor-layout">
 <section class="cms-card cms-editor-main" aria-label="Prévia do editor de conteúdo">
  <div class="cms-module-head"><div><h2>Editor de conteúdo</h2><p>Estrutura visual para uma futura edição segura.</p></div><span class="cms-status">Rascunho demonstrativo</span></div>
  <label class="cms-field-label" for="cms-editor-title">Título</label><input id="cms-editor-title" class="cms-field" value="" placeholder="Título da publicação" disabled>
  <label class="cms-field-label" for="cms-editor-slug">Endereço amigável</label><div class="cms-field-prefix"><span>/blog/</span><input id="cms-editor-slug" placeholder="endereco-do-conteudo" disabled></div>
  <div class="cms-editor-toolbar" aria-label="Ferramentas de formatação indisponíveis"><button type="button" disabled aria-label="Negrito"><strong>B</strong></button><button type="button" disabled aria-label="Itálico"><em>I</em></button><button type="button" disabled aria-label="Lista">☷</button><button type="button" disabled aria-label="Inserir link">↗</button><button type="button" disabled aria-label="Inserir imagem">▧</button></div>
  <div class="cms-editor-placeholder" role="note"><strong>Área de edição</strong><p>O editor será conectado à biblioteca de mídia e ao controle de versões após a implementação das permissões administrativas.</p></div>
  <div class="cms-editor-actions"><button type="button" disabled>Salvar rascunho</button><button type="button" disabled>Publicar</button></div>
 </section>
 <aside class="cms-editor-side"><section class="cms-card"><h2>Publicação</h2><p>Defina visibilidade, agendamento e autoria.</p><label class="cms-field-label" for="cms-editor-status">Status</label><select id="cms-editor-status" class="cms-field" disabled><option>Não publicado</option></select><label class="cms-field-label" for="cms-editor-date">Agendamento</label><input id="cms-editor-date" class="cms-field" type="text" placeholder="Sem agendamento" disabled></section><section class="cms-card"><h2>SEO e compartilhamento</h2><p>Metadados e imagem de destaque serão configurados nesta área.</p><div class="cms-editor-media"><?= $cmsIcon('media') ?><span>Nenhuma imagem selecionada</span></div></section></aside>
</div>
<section class="cms-card cms-preview-inventory" aria-labelledby="cms-contents-inventory-title">
  <div class="cms-module-head">
    <div><h2 id="cms-contents-inventory-title">Inventário editorial</h2><p>Os conteúdos autorizados aparecerão aqui após a integração.</p></div>
    <span class="cms-status">Sem consulta ao banco</span>
  </div>
  <?php $cmsEmptyTable = [
      'caption' => 'Conteúdos editoriais',
      'columns' => ['Título', 'Tipo', 'Responsável', 'Situação', 'Atualização'],
      'title' => 'Nenhum conteúdo consultado',
      'description' => 'A prévia não carrega artigos, autores, versões ou agendamentos reais.',
  ]; require __DIR__ . '/../components/empty-table.php'; ?>
</section>
<?php $cmsWorkflow = [
    'title' => 'Fluxo editorial previsto',
    'steps' => [
        ['title' => 'Redação', 'description' => 'Preparar conteúdo e metadados em rascunho.'],
        ['title' => 'Revisão', 'description' => 'Conferir links, mídia, acessibilidade e versões.'],
        ['title' => 'Aprovação', 'description' => 'Validar autoria e papel autorizado antes de qualquer alteração.'],
        ['title' => 'Publicação', 'description' => 'Registrar auditoria e publicar somente após aprovação.'],
    ],
]; require __DIR__ . '/../components/workflow-preview.php'; ?>
<aside class="cms-notice"><strong>Editor indisponível</strong>Esta tela é apenas uma prévia. Nenhuma alteração é salva, publicada ou enviada ao servidor.</aside>
