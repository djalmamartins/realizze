<?php
declare(strict_types=1);
$name = htmlspecialchars((string) ($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Minha conta | Realizze</title></head>
<body>
<main>
<h1>Minha conta</h1>
<p>Bem-vindo, <?= $name ?>.</p>
<form action="/sair" method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
<button type="submit">Sair da conta</button>
</form>
</main>
</body>
</html>
