<?php
/** Central de integrações. Nenhuma está ativa: nada é conectado, enviado ou simulado aqui. */
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cards = [
    ['Open Finance', 'Importar contas e extratos com o seu consentimento, por um provedor homologado.', 'credit-card', 'financeiras'],
    ['Instituições financeiras', 'Conexão direta com a API de cada banco.', 'building', 'financeiras'],
    ['Cartões', 'Acompanhar faturas e lançamentos dos cartões.', 'credit-card', 'financeiras'],
    ['PIX', 'Gerar cobranças e identificar pagamentos.', 'arrows-left-right', 'pagamentos'],
    ['Boleto', 'Emitir boletos por um serviço autorizado.', 'arrows-left-right', 'pagamentos'],
    ['WhatsApp', 'Lembretes pela API oficial, com autorização do cliente.', 'message-circle', 'comunicacao'],
];
$tabs = ['' => 'Todas', 'financeiras' => 'Financeiras', 'comunicacao' => 'Comunicação', 'pagamentos' => 'Pagamentos'];
$tab = is_string($_GET['tipo'] ?? null) && isset($tabs[$_GET['tipo']]) ? $_GET['tipo'] : '';
$visible = array_filter($cards, static fn (array $card): bool => $tab === '' || $card[3] === $tab);
$count = static fn (string $key): int => $key === '' ? count($cards) : count(array_filter($cards, static fn (array $card): bool => $card[3] === $key));
?>
<section class="card finance-workspace" aria-labelledby="integrations-title">
  <div class="finance-toolbar">
    <div><h2 id="integrations-title">Integrações</h2><p class="muted">Nenhuma integração está ativa nesta versão. Nenhum dado bancário é conectado e nenhuma senha de banco é pedida.</p></div>
    <a class="primary integration-request" href="/suporte/novo">Solicitar integração</a>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-status" aria-label="Tipo de integração">
      <?php foreach ($tabs as $key => $label): ?>
      <a href="<?= $e(\Moves\Services\AppUrl::section('integracoes', null, $key === '' ? [] : ['tipo' => $key])) ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= $e($label) ?> (<?= $count($key) ?>)</a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="integration-grid">
    <?php foreach ($visible as [$title, $text, $iconName]): ?>
    <article class="integration-card"><span class="finance-account-icon" aria-hidden="true"><?= $icon($iconName) ?></span><div><h3><?= $e($title) ?></h3><p class="muted"><?= $e($text) ?></p></div><span class="finance-badge is-open">Indisponível</span></article>
    <?php endforeach; ?>
  </div>
</section>
