<?php
/** CMS content editor layout preview. No persistence, uploads or publishing. */
?>
<div class="cms-heading"><div><span class="cms-eyebrow">Gerenciamento / Editorial</span><h1>Conteúdos</h1><p>Planeje artigos, comunicados e páginas editoriais.</p></div><span class="cms-chip">Prévia visual · sem edição</span></div>
<div class="cms-editor-layout">
 <section class="cms-card cms-editor-main" aria-label="Prévia do editor de conteúdo">
  <div class="cms-module-head"><div><h2>Editor de conteúdo</h2><p>Estrutura visual para uma futura edição segura.</p></div><span class="cms-status">Rascunho demonstrativo</span></div>
  <label class="cms-field-label" for="cms-editor-title">Título</label><input id="cms-editor-title" class="cms-field" value="" placeholder="Título da publicação" disabled>
  <label class="cms-field-label" for="cms-editor-slug">Endereço amigável</label><div class="cms-field-prefix"><span>/blog/</span><input id="cms-editor-slug" placeholder="endereco-do-conteudo" disabled></div>
  <div class="cms-editor-toolbar" aria-label="Ferramentas de formatação indisponíveis"><button type="button" disabled aria-label="Negrito"><strong>B</strong></button><button type="button" disabled aria-label="Itálico"><em>I</em></button><button type="button" disabled aria-label="Lista">☷</button><button type="button" disabled aria-label="Inserir link">↗</button><button type="button" disabled aria-label="Inserir imagem">▧</button></div>
  <div class="cms-editor-placeholder" role="note"><strong>Área de edição</strong><p>O editor será conectado à biblioteca de mídia e ao controle de versões após a implementação das permissões administrativas.</p></div>
  <div class="cms-editor-actions"><button type="button" disabled>Salvar rascunho</button><button type="button" disabled>Publicar</button></div>
 </section>
 <aside class="cms-editor-side"><section class="cms-card"><h2>Publicação</h2><p>Defina visibilidade, agendamento e autoria.</p><label class="cms-field-label" for="cms-editor-status">Status</label><select id="cms-editor-status" class="cms-field" disabled><option>Não publicado</option></select><label class="cms-field-label" for="cms-editor-date">Agendamento</label><input id="cms-editor-date" class="cms-field" type="text" placeholder="Sem agendamento" disabled></section><section class="cms-card"><h2>SEO e compartilhamento</h2><p>Metadados e imagem de destaque serão configurados nesta área.</p><div class="cms-editor-media">▧<span>Nenhuma imagem selecionada</span></div></section></aside>
</div>
<aside class="cms-notice"><strong>Editor indisponível</strong>Esta tela é apenas uma prévia. Nenhuma alteração é salva, publicada ou enviada ao servidor.</aside>
