<?php
/** CMS media library layout preview. No files or uploads are accessed. */
?>
<?php $cmsHeading = ['eyebrow' => 'Gerenciamento / Arquivos', 'title' => 'Biblioteca de mídia', 'description' => 'Organize imagens, documentos e arquivos do site.', 'badge' => 'Prévia visual']; require __DIR__ . '/../components/page-heading.php'; ?>
<section class="cms-card" aria-label="Biblioteca de mídia demonstrativa"><div class="cms-module-head"><div><h2>Arquivos</h2><p>Visualização em grade e filtros de mídia.</p></div><button type="button" disabled>Enviar arquivos</button></div>
<div class="cms-media-controls"><label for="cms-media-search">Pesquisar arquivos<input id="cms-media-search" class="cms-field" type="search" placeholder="Pesquisar pelo nome" disabled></label><label for="cms-media-filter">Tipo de arquivo<select id="cms-media-filter" class="cms-field" disabled><option>Todos os formatos</option></select></label></div>
<div class="cms-media-empty"><div class="cms-media-empty-icon" aria-hidden="true"><?= $cmsIcon('media') ?></div><h2>Nenhum arquivo disponível</h2><p>Depois da integração, os arquivos autorizados aparecerão aqui com miniaturas, metadados e ações de gerenciamento.</p><button type="button" disabled>Adicionar primeiro arquivo</button></div></section>
<section class="cms-card cms-preview-inventory" aria-labelledby="cms-media-inventory-title">
  <div class="cms-module-head">
    <div><h2 id="cms-media-inventory-title">Lista de arquivos</h2><p>Metadados de arquivos serão exibidos somente após autorização.</p></div>
    <span class="cms-status">Sem acesso ao armazenamento</span>
  </div>
  <?php $cmsEmptyTable = [
      'caption' => 'Arquivos da biblioteca',
      'columns' => ['Arquivo', 'Formato', 'Tamanho', 'Acesso'],
      'title' => 'Nenhum arquivo consultado',
      'description' => 'A prévia não lê arquivos, miniaturas, caminhos ou dados de usuários.',
  ]; require __DIR__ . '/../components/empty-table.php'; ?>
</section>
<aside class="cms-notice"><strong>Envio desativado</strong>Uploads exigirão autenticação, validação de tipo e tamanho, armazenamento seguro e verificação de permissões.</aside>
