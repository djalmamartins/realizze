<?php
/** Integrações previstas. Nenhuma está ativa: nada é conectado, enviado ou simulado aqui. */
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cards = [
    ['Open Finance', 'Importar contas e extratos com o seu consentimento.', 'credit-card'],
    ['Cartões', 'Acompanhar faturas e lançamentos dos cartões.', 'credit-card'],
    ['PIX', 'Gerar cobranças e identificar pagamentos.', 'arrows-left-right'],
    ['WhatsApp', 'Lembretes pela API oficial, com autorização do cliente.', 'message-circle'],
    ['Banco do Brasil', 'Conexão direta com a API do banco.', 'building'],
    ['Itaú', 'Conexão direta com a API do banco.', 'building'],
];
?>
<section class="card finance-workspace" aria-labelledby="integrations-title">
  <div class="finance-toolbar"><div><h2 id="integrations-title">Integrações</h2><p class="muted">Nenhuma integração está ativa nesta versão.</p></div></div>
  <div class="integration-grid">
    <?php foreach ($cards as [$title, $text, $iconName]): ?>
    <article class="integration-card"><span class="finance-account-icon" aria-hidden="true"><?= $icon($iconName) ?></span><div><h3><?= $e($title) ?></h3><p class="muted"><?= $e($text) ?></p></div><span class="finance-badge is-open">Indisponível</span></article>
    <?php endforeach; ?>
  </div>
</section>
