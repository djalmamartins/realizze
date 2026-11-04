<?php
/** Indicadores empresariais sem consulta a dados financeiros. */
$metrics = [
    ['Saldo disponível', 'Contas empresariais não integradas'],
    ['A receber', 'Recebimentos não consultados'],
    ['A pagar', 'Despesas não consultadas'],
    ['Resultado do período', 'Apuração ainda indisponível'],
];
?>
<section class="enterprise-kpis" aria-label="Indicadores empresariais ainda não disponíveis">
<?php foreach ($metrics as [$label, $description]): ?>
  <article class="enterprise-kpi">
    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
    <strong aria-label="Valor indisponível">—</strong>
    <small><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></small>
  </article>
<?php endforeach; ?>
</section>
