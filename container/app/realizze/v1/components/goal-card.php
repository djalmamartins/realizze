<?php
/**
 * Linha de uma meta: foto, progresso, valor, previsão, situação e ações.
 * Parâmetros: $goal, $today, $history, $forecast, $term, $institutions, $details, $csrf, $appView, $e.
 */
use Moves\Services\Finance;

$id = (int) $goal['id'];
$saved = (int) $goal['saved_cents'];
$progress = Finance::goalProgress((int) $goal['target_cents'], $saved, $goal['deadline'] ?: null, $today);
$photoUrl = $goal['image_path'] ? '/minha-conta/financeiro/meta-foto?id=' . $id . '&v=' . substr((string) $goal['image_path'], 0, 8) : null;
$bank = $goal['institution_name'] ?: $goal['institution'];
$place = implode(' · ', array_filter([$goal['savings_place'], $bank]));
$months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$monthLabel = static function (string $date) use ($months): string {
    $parsed = new DateTimeImmutable($date);
    return $months[(int) $parsed->format('n') - 1] . '/' . $parsed->format('Y');
};
$goalIcon = require __DIR__ . '/goal-icon.php';
$terms = ['curto' => 'Curto prazo', 'medio' => 'Médio prazo', 'longo' => 'Longo prazo'];
$trend = Finance::goalTrend($saved, $history, $today);
$top = max(1, max($trend));
$line = [];
foreach ($trend as $index => $point) {
    $line[] = round($index * 80 / max(1, count($trend) - 1), 1) . ',' . round(26 - $point / $top * 24, 1);
}
$hasTrend = count($history) >= 2 && min($trend) !== max($trend);
?>
<li class="goal-row">
  <div class="goal-thumb<?= $photoUrl ? '' : ' is-empty' ?>">
    <?php if ($photoUrl): ?><img src="<?= $e($photoUrl) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><?= $goalIcon($goal['icon'], 30) ?><?php endif; ?>
  </div>
  <div class="goal-main">
    <div class="goal-name"><h3><?= $e($goal['name']) ?></h3><?php if ($term !== null): ?><span class="goal-term is-<?= $e($term) ?>"><?= $e($terms[$term]) ?></span><?php endif; ?></div>
    <?php if ($goal['purpose']): ?><p class="goal-purpose"><?= $e($goal['purpose']) ?></p><?php endif; ?>
    <div class="goal-progress">
      <div class="track track-live goal-bar is-<?= $e($forecast['status']) ?>" role="progressbar" aria-valuenow="<?= $progress['percent'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso de <?= $e($goal['name']) ?>"><span data-width="<?= $progress['percent'] ?>"></span></div>
      <strong><?= $progress['percent'] ?>%</strong>
    </div>
    <p class="goal-amount"><strong><?= $e(Finance::money($saved)) ?></strong> de <?= $e(Finance::money((int) $goal['target_cents'])) ?></p>
    <?php if ($place !== ''): ?>
    <div class="goal-place">
      <?= $appView->render('components::bank-badge', ['name' => $bank ?: (string) $goal['savings_place'], 'logo' => $goal['institution_logo'], 'slug' => $goal['institution_slug']]) ?>
      <span><small>Guardando em</small><strong><?= $e($place) ?></strong></span>
    </div>
    <?php elseif ($details): ?>
    <button type="button" class="goal-place-link" data-finance-open="goal-edit-<?= $id ?>">Informar onde está guardando</button>
    <?php endif; ?>
  </div>
  <div class="goal-metric"><span>Valor da meta</span><strong><?= $e(Finance::money((int) $goal['target_cents'])) ?></strong></div>
  <div class="goal-metric">
    <span>Previsão de conclusão</span>
    <?php if ($progress['done']): ?><strong>Concluída</strong>
    <?php elseif ($forecast['finish'] !== null): ?><strong><?= $e($monthLabel($forecast['finish'])) ?></strong><small>Faltam <?= (int) $forecast['months'] ?> <?= $forecast['months'] > 1 ? 'meses' : 'mês' ?></small>
    <?php else: ?><strong>Sem previsão</strong><?php if ($goal['deadline']): ?><small>Prazo <?= $e($monthLabel($goal['deadline'])) ?></small><?php endif; ?><?php endif; ?>
  </div>
  <div class="goal-status is-<?= $e($forecast['status']) ?>">
    <div><strong><?= $e($forecast['title']) ?></strong><small><?= $e($forecast['text']) ?></small></div>
    <?php if ($hasTrend): ?><svg viewBox="0 0 80 28" width="80" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="<?= $e(implode(' ', $line)) ?>"/></svg><?php endif; ?>
  </div>
  <div class="goal-actions-col">
    <button type="button" class="primary" data-finance-open="goal-add-<?= $id ?>">Guardar</button>
    <details class="goal-menu">
      <summary aria-label="Mais ações de <?= $e($goal['name']) ?>">…</summary>
      <div><button type="button" data-finance-open="goal-history-<?= $id ?>">Histórico</button><button type="button" data-finance-open="goal-edit-<?= $id ?>">Editar</button></div>
    </details>
  </div>
<dialog class="finance-dialog" id="goal-add-<?= $id ?>" aria-labelledby="goal-add-title-<?= $id ?>">
  <form method="post" action="/minha-conta/financeiro/meta-valor" data-finance-form>
    <header><h2 id="goal-add-title-<?= $id ?>">Guardar em <?= $e($goal['name']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= $id ?>">
    <p class="muted">Registre aqui o valor que você já guardou<?= $place !== '' ? ' em ' . $e($place) : '' ?>. O Realizze não movimenta seu dinheiro.</p>
    <div class="finance-dialog-row">
      <label>Valor (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
      <label>Data<input name="date" type="date" value="<?= $e($today) ?>" max="<?= $e($today) ?>" required></label>
    </div>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Registrar aporte</button></footer>
  </form>
</dialog>
<dialog class="finance-dialog" id="goal-history-<?= $id ?>" aria-labelledby="goal-history-title-<?= $id ?>">
  <div class="finance-dialog-body goal-history">
    <header><h2 id="goal-history-title-<?= $id ?>">Aportes em <?= $e($goal['name']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?php if ($history === []): ?>
    <p class="muted">Nenhum aporte registrado ainda.</p>
    <?php else: ?>
    <ul>
      <?php foreach ($history as $item): ?>
      <li>
        <span><strong><?= $e(Finance::money($item['amount_cents'])) ?></strong><small><?= $e((new DateTimeImmutable($item['contributed_at']))->format('d/m/Y')) ?></small></span>
        <form method="post" action="/minha-conta/financeiro/meta-aporte-excluir" data-finance-confirm="Remover este aporte? O valor guardado da meta será reduzido."><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= $item['id'] ?>"><button type="submit" class="finance-link-danger">Remover</button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</dialog>
<dialog class="finance-dialog" id="goal-edit-<?= $id ?>" aria-labelledby="goal-edit-title-<?= $id ?>">
  <form method="post" action="/minha-conta/financeiro/meta-editar" enctype="multipart/form-data" data-finance-form>
    <header><h2 id="goal-edit-title-<?= $id ?>">Editar meta</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= $id ?>">
    <?= $appView->render('components::goal-fields', ['goal' => $goal, 'institutions' => $institutions, 'details' => $details, 'e' => $e, 'photoUrl' => $photoUrl]) ?>
    <footer class="goal-edit-footer"><button type="submit" form="goal-delete-<?= $id ?>" class="finance-link-danger">Excluir meta</button><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
  <form id="goal-delete-<?= $id ?>" method="post" action="/minha-conta/financeiro/meta-excluir" data-finance-confirm="Excluir a meta “<?= $e($goal['name']) ?>”?" hidden><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= $id ?>"></form>
</dialog>
</li>
