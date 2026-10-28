<?php
/** CMS UI preview only: no database reads or administrative actions. */
$title = "Conteúdos";
$description = "Organize textos, notícias e materiais editoriais.";
$columns = ["Conteúdo","Tipo","Publicação"];
?>
<div class="cms-heading"><div><span class="cms-eyebrow">Gerenciamento</span><h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p></div><span class="cms-chip">Prévia visual</span></div>
<section class="cms-card cms-module" aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"><div class="cms-module-head"><div><h2>Visão dos registros</h2><p>Os dados serão exibidos após a ativação segura do módulo.</p></div><button type="button" disabled title="Funcionalidade indisponível">Novo registro</button></div><div class="cms-table-wrap"><table class="cms-table"><thead><tr><?php foreach ($columns as $column): ?><th scope="col"><?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?></tr></thead><tbody><tr><td colspan="<?= count($columns) ?>" class="cms-empty"><strong>Nenhum registro disponível</strong><p>Esta é uma prévia de layout. Nenhum dado privado foi consultado.</p></td></tr></tbody></table></div></section>
<aside class="cms-notice"><strong>Módulo ainda indisponível</strong>Cadastro, edição, exclusão e publicação exigirão autenticação administrativa, autorização e proteção CSRF antes de serem liberados.</aside>
