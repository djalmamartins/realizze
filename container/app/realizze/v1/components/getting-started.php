<?php
/** Primeiros passos com progresso lido dos dados do contexto ($setup vem de Finance::setupProgress). */
$setup = $setup ?? [];
$setupTasks = [
    'account' => ['Cadastrar conta financeira', \Moves\Services\AppUrl::section('contas'), 'credit-card'],
    'categories' => ['Definir categorias', \Moves\Services\AppUrl::section('categorias'), 'list-filter'],
    'card' => ['Adicionar cartão', \Moves\Services\AppUrl::section('contas'), 'credit-card'],
    'entry' => ['Registrar primeiro lançamento', \Moves\Services\AppUrl::section('movimentacoes'), 'arrows-left-right'],
    'planning' => ['Criar um orçamento ou uma meta', \Moves\Services\AppUrl::section('planejamento'), 'calendar-days'],
];
$setupDone = count(array_filter($setup));
$setupNext = null;
foreach ($setupTasks as $key => $task) { if (empty($setup[$key])) { $setupNext = $task; break; } }
$esc = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<section class="card setup-card" aria-labelledby="setup-heading">
  <div class="section-head"><div><p class="eyebrow">PRIMEIROS PASSOS</p><h2 id="setup-heading">Vamos configurar seu Realizze?</h2></div><?php if ($setupNext !== null): ?><a href="<?= $esc($setupNext[1]) ?>">Continuar →</a><?php endif; ?></div>
  <p class="muted"><?= $setupDone ?> de <?= count($setupTasks) ?> concluídos. Este quadro some quando tudo estiver pronto.</p>
  <div class="setup-task-grid">
  <?php foreach ($setupTasks as $key => $task): $done = !empty($setup[$key]); ?>
    <a class="setup-task<?= $done ? ' is-done' : '' ?>" href="<?= $esc($task[1]) ?>"><?= $icon($task[2]) ?><span><?= $esc($task[0]) ?><?php if ($done): ?><span class="sr-only"> (concluído)</span><?php endif; ?></span><span aria-hidden="true"><?= $done ? '✓' : '→' ?></span></a>
  <?php endforeach; ?>
  </div>
</section>
