<!doctype html>
<html lang="pt-BR">
<head>
    <?php $this->insert('components/head', [
        'title' => $title ?? 'Realizze | Gestão de condomínios',
        'description' => $description ?? 'Uma experiência mais simples e conectada para a gestão de condomínios.',
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
