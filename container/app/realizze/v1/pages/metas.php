<?php
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$period = Finance::period($_GET);
$link = static fn (array $query): string => \Moves\Services\AppUrl::section('metas', null, $query);
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$loadFailed = false;
$goals = $history = $institutions = [];
$baseline = ['months' => 0, 'income_cents' => 0, 'expense_cents' => 0, 'committed_cents' => 0, 'categories' => []];
$details = false;
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $goals = $finance->goals();
    $history = $finance->goalContributions(array_map(static fn (array $g): int => (int) $g['id'], $goals));
    $details = $finance->supportsGoalDetails();
    $baseline = $finance->goalBaseline();
    $institutions = $details ? \Moves\Services\FinancialInstitutions::active() : [];
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$today = date('Y-m-d');
$free = $baseline['months'] > 0 ? $baseline['income_cents'] - $baseline['expense_cents'] - $baseline['committed_cents'] : 0;
$rows = array_map(static function (array $goal) use ($history, $free, $today): array {
    $list = $history[(int) $goal['id']] ?? [];
    // O valor lançado ao criar a meta é o que já estava guardado, não o ritmo de aportes.
    $created = substr((string) $goal['created_at'], 0, 10);
    $paced = array_values(array_filter($list, static fn (array $c): bool => $c['contributed_at'] !== $created));
    $goal['term'] = Finance::goalTerm($goal['deadline'] ?: null, $today);
    $goal['forecast'] = Finance::goalForecast((int) $goal['target_cents'], (int) $goal['saved_cents'], $goal['deadline'] ?: null, $goal['monthly_contribution_cents'] === null ? null : (int) $goal['monthly_contribution_cents'], $paced, $free, $today);
    $goal['last_contribution'] = $list[0]['contributed_at'] ?? null;
    $goal['done'] = (int) $goal['saved_cents'] >= (int) $goal['target_cents'];
    return $goal;
}, $goals);

$tabs = ['todas' => ['Todas', ''], 'curto' => ['Curto prazo', 'até 1 ano'], 'medio' => ['Médio prazo', '1 a 3 anos'], 'longo' => ['Longo prazo', 'mais de 3 anos'], 'sem' => ['Sem prazo', ''], 'alcancadas' => ['Alcançadas', '']];
$matches = static fn (array $goal, string $tab): bool => match ($tab) {
    'todas' => true,
    'alcancadas' => $goal['done'],
    'sem' => $goal['term'] === null,
    default => $goal['term'] === $tab,
};
$tab = isset($tabs[$_GET['prazo'] ?? '']) ? $_GET['prazo'] : 'todas';
$orders = ['proximas' => 'Mais próximas', 'progresso' => 'Maior progresso', 'valor' => 'Maior valor', 'recentes' => 'Mais recentes'];
$order = isset($orders[$_GET['ordem'] ?? '']) ? $_GET['ordem'] : 'proximas';
$visible = array_values(array_filter($rows, static fn (array $goal): bool => $matches($goal, $tab)));
usort($visible, static fn (array $a, array $b): int => match ($order) {
    'progresso' => (int) $b['saved_cents'] / max(1, (int) $b['target_cents']) <=> (int) $a['saved_cents'] / max(1, (int) $a['target_cents']),
    'valor' => (int) $b['target_cents'] <=> (int) $a['target_cents'],
    'recentes' => (int) $b['id'] <=> (int) $a['id'],
    default => [$a['done'], $a['deadline'] === null, $a['deadline'], (int) $a['id']] <=> [$b['done'], $b['deadline'] === null, $b['deadline'], (int) $b['id']],
});

$target = array_sum(array_map(static fn (array $g): int => (int) $g['target_cents'], $goals));
$saved = array_sum(array_map(static fn (array $g): int => min((int) $g['saved_cents'], (int) $g['target_cents']), $goals));
$overall = $target > 0 ? (int) floor($saved / $target * 100) : 0;
$slices = [];
foreach (['curto' => ['Curto prazo', '#078b4c'], 'medio' => ['Médio prazo', '#2478a8'], 'longo' => ['Longo prazo', '#e08a1e'], 'sem' => ['Sem prazo', '#a8b5ae']] as $key => [$label, $color]) {
    $total = array_sum(array_map(static fn (array $g): int => $matches($g, $key) ? (int) $g['target_cents'] : 0, $rows));
    if ($total > 0) {
        $slices[] = ['label' => $label, 'color' => $color, 'total' => $total, 'share' => $total / max(1, $target) * 100];
    }
}
$achievements = array_filter($rows, static fn (array $g): bool => $g['done']);
usort($achievements, static fn (array $a, array $b): int => (string) $b['last_contribution'] <=> (string) $a['last_contribution']);
// Dica por regra simples: primeiro a meta mais atrasada; depois, a meta ainda sem ritmo.
$tip = null;
foreach ($rows as $goal) {
    if ($goal['forecast']['title'] === 'Acelere um pouco') {
        $tip = [$goal, $goal['name'] . ': ' . mb_strtolower(mb_substr($goal['forecast']['text'], 0, 1)) . mb_substr($goal['forecast']['text'], 1)];
        break;
    }
}
foreach ($tip === null ? $rows : [] as $goal) {
    if (!$goal['done'] && $goal['forecast']['pace_cents'] === null) {
        $tip = [$goal, $goal['name'] . ' ainda não tem ritmo. Defina quanto guardar por mês para ver a previsão.'];
        break;
    }
}
$money = static fn (int $cents): string => Finance::money($cents);
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="goals-title">
  <div class="finance-toolbar">
    <div><h2 id="goals-title">Acompanhe a evolução dos seus sonhos</h2><p class="muted">Defina metas a curto, médio e longo prazo e conte com o Realizze para alcançar.</p></div>
    <button type="button" class="primary" data-finance-open="goal-dialog">+ Nova meta</button>
  </div>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar as metas</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($goals === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('target') ?></span><h3>Nenhuma meta cadastrada</h3><p>Crie sua primeira meta, como um carro, uma reserva ou uma viagem.</p></div>
  <?php else: ?>
  <div class="goal-tabs-bar">
    <nav class="goal-tabs" aria-label="Filtrar metas por prazo">
      <?php foreach ($tabs as $key => [$label, $hint]): $count = count(array_filter($rows, static fn (array $g): bool => $matches($g, $key))); if (in_array($key, ['sem', 'alcancadas'], true) && $count === 0 && $tab !== $key) continue; ?>
      <a href="<?= $e($link(['prazo' => $key, 'ordem' => $order])) ?>"<?= $key === $tab ? ' class="is-active" aria-current="page"' : '' ?>><span><?= $e($label) ?> <b><?= $count ?></b></span><?php if ($hint !== ''): ?><small><?= $e($hint) ?></small><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <form class="goal-sort" method="get" action="<?= $e($link([])) ?>" data-swap-get>
      <input type="hidden" name="prazo" value="<?= $e($tab) ?>">
      <label>Ordenar por:<select name="ordem" data-goal-sort><?php foreach ($orders as $key => $label): ?><option value="<?= $e($key) ?>"<?= $key === $order ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
    </form>
  </div>
  <div class="goal-layout">
    <div class="goal-list-wrap">
      <?php if ($visible === []): ?>
      <div class="finance-empty"><h3>Nenhuma meta neste prazo</h3><p>Escolha outra aba ou crie uma nova meta.</p></div>
      <?php else: ?>
      <ul class="goal-list">
        <?php foreach ($visible as $goal): ?>
        <?= $appView->render('components::goal-card', ['goal' => $goal, 'today' => $today, 'history' => $history[(int) $goal['id']] ?? [], 'forecast' => $goal['forecast'], 'term' => $goal['term'], 'institutions' => $institutions, 'details' => $details, 'csrf' => $csrf, 'appView' => $appView, 'e' => $e]) ?>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <aside class="goal-aside">
      <div class="goal-side-card">
        <h3>Resumo das metas</h3>
        <dl><div><dt>Total das metas</dt><dd><?= $e($money($target)) ?></dd></div><div><dt>Total acumulado</dt><dd class="green"><?= $e($money($saved)) ?></dd></div><div><dt>Progresso geral</dt><dd><?= $overall ?>%</dd></div></dl>
        <div class="track track-live goal-bar is-success" role="progressbar" aria-valuenow="<?= $overall ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso geral"><span data-width="<?= $overall ?>"></span></div>
      </div>
      <div class="goal-side-card">
        <h3>Metas por prazo</h3>
        <div class="goal-donut-row">
          <svg viewBox="0 0 42 42" width="96" height="96" role="img" aria-label="Divisão do valor das metas por prazo">
            <?php $offset = 25; foreach ($slices as $slice): ?><circle cx="21" cy="21" r="15.915" fill="none" stroke="<?= $e($slice['color']) ?>" stroke-width="6" stroke-dasharray="<?= round($slice['share'], 2) ?> <?= round(100 - $slice['share'], 2) ?>" stroke-dashoffset="<?= round($offset, 2) ?>"/><?php $offset -= $slice['share']; endforeach; ?>
          </svg>
          <ul>
            <?php foreach ($slices as $slice): ?><li><i style="background:<?= $e($slice['color']) ?>"></i><span><?= $e($slice['label']) ?><small><?= $e($money($slice['total'])) ?> · <?= round($slice['share']) ?>%</small></span></li><?php endforeach; ?>
          </ul>
        </div>
      </div>
      <div class="goal-side-card">
        <h3>Conquistas recentes</h3>
        <?php if ($achievements === []): ?>
        <p class="muted">Suas metas alcançadas aparecem aqui.</p>
        <?php else: ?>
        <ul class="goal-achievements">
          <?php foreach (array_slice($achievements, 0, 3) as $goal): ?><li><strong><?= $e($goal['name']) ?></strong><small>Meta alcançada<?= $goal['last_contribution'] ? ' em ' . $e((new DateTimeImmutable($goal['last_contribution']))->format('d/m/Y')) : '' ?></small></li><?php endforeach; ?>
        </ul>
        <a class="goal-side-link" href="<?= $e($link(['prazo' => 'alcancadas'])) ?>">Ver todas as conquistas</a>
        <?php endif; ?>
      </div>
      <?php if ($tip !== null): ?>
      <div class="goal-side-card goal-tip-card">
        <h3>Dica do Realizze</h3>
        <p><?= $e($tip[1]) ?></p>
        <button type="button" data-finance-open="goal-edit-<?= (int) $tip[0]['id'] ?>">Ajustar meta</button>
      </div>
      <?php endif; ?>
    </aside>
  </div>
  <?php endif; ?>
</section>
<?= $appView->render('components::goal-wizard', ['institutions' => $institutions, 'details' => $details, 'baseline' => $baseline, 'today' => $today, 'csrf' => $csrf, 'e' => $e]) ?>
