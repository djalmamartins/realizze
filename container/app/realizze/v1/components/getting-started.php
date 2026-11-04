<?php
/** UI-ONB: checklist de navegação. Não representa progresso salvo no banco. */
$setupTasks = [
    ['Cadastrar conta financeira', \Moves\Services\AppUrl::section('contas'), 'credit-card'],
    ['Definir categorias', \Moves\Services\AppUrl::section('categorias'), 'list-filter'],
    ['Adicionar cartão', \Moves\Services\AppUrl::section('contas'), 'credit-card'],
    ['Registrar primeiro lançamento', \Moves\Services\AppUrl::section('movimentacoes'), 'arrows-left-right'],
    ['Configurar planejamento', \Moves\Services\AppUrl::section('planejamento'), 'calendar-days'],
];
?>
<section class="card setup-card" aria-labelledby="setup-heading">
  <div class="section-head"><div><p class="eyebrow">PRIMEIROS PASSOS</p><h2 id="setup-heading">Vamos configurar seu Realizze?</h2></div><a href="<?= htmlspecialchars(\Moves\Services\AppUrl::section('contas'), ENT_QUOTES, 'UTF-8') ?>">Começar →</a></div>
  <p class="muted">Comece pelo essencial. As tarefas abaixo são sugestões de navegação e ainda não têm acompanhamento automático.</p>
  <div class="setup-task-grid">
  <?php foreach ($setupTasks as $task): ?>
    <a class="setup-task" href="<?= htmlspecialchars($task[1], ENT_QUOTES, 'UTF-8') ?>"><?= $icon($task[2]) ?><span><?= htmlspecialchars($task[0], ENT_QUOTES, 'UTF-8') ?></span><span aria-hidden="true">→</span></a>
  <?php endforeach; ?>
  </div>
  <p class="muted setup-note">Em breve: progresso automático, configuração por PF/PJ e recomendações personalizadas.</p>
</section>
