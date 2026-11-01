<!doctype html>
<html lang="pt-BR">
<head>
    <?php $this->insert('components/head', [
        'title' => ($seo['title'] ?? null) ?: ($title ?? 'Realizze | Controle, planeje e realize'),
        'description' => ($seo['description'] ?? null) ?: ($description ?? 'Organize suas finanças pessoais e empresariais com o Realizze.'),
        'canonical' => $seo['canonical'] ?? '',
        'ogImage' => $seo['ogImage'] ?? '',
        'noindex' => $seo['noindex'] ?? false,
    ]); ?>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<?php $this->insert('components/header'); ?>
<main id="conteudo"><?= $this->section('content', '') ?></main>
<?php
$optout = $this->section('optout');
if ($optout !== null) {
    echo $optout;
} else {
    $this->insert('components/optout');
}
?>
<?php $this->insert('components/footer'); ?>
<?php $this->insert('components/scripts'); ?>
</body>
</html>
