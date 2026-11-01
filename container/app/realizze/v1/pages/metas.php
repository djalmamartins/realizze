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
$goals = [];
try {
    $goals = (new Finance((int) FinanceContext::currentId()))->goals();
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$target = array_sum(array_map(static fn (array $g): int => (int) $g['target_cents'], $goals));
$saved = array_sum(array_map(static fn (array $g): int => (int) $g['saved_cents'], $goals));
$done = count(array_filter($goals, static fn (array $g): bool => (int) $g['saved_cents'] >= (int) $g['target_cents']));
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="goals-title">
  <div class="finance-toolbar">
    <div><h2 id="goals-title">Metas</h2><p class="muted">Defina objetivos, prazos e acompanhe sua evolução.</p></div>
    <button type="button" class="primary" data-finance-open="goal-dialog">+ Nova meta</button>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo das metas">
    <div class="finance-goal-stat"><span>Metas ativas</span><strong><?= count($goals) - $done ?></strong><small><?= $done ?> concluída<?= $done === 1 ? '' : 's' ?></small></div>
    <div class="finance-goal-stat"><span>Valor planejado</span><strong><?= $e(Finance::money($target)) ?></strong><small>Soma dos objetivos</small></div>
    <div class="finance-goal-stat"><span>Já guardado</span><strong class="green"><?= $e(Finance::money($saved)) ?></strong><small>Até agora</small></div>
  </div>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar as metas</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($goals === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('target') ?></span><h3>Nenhuma meta cadastrada</h3><p>Crie sua primeira meta, como uma reserva ou uma viagem.</p></div>
  <?php else: ?>
  <ul class="budget-list">
    <?php foreach ($goals as $goal): $share = min(100, (int) $goal['saved_cents'] / max(1, (int) $goal['target_cents']) * 100); ?>
    <li>
      <div class="budget-head"><strong><?= $e($goal['name']) ?></strong><span class="muted"><?= $e(Finance::money((int) $goal['saved_cents'])) ?> de <?= $e(Finance::money((int) $goal['target_cents'])) ?><?= $goal['deadline'] ? ' · até ' . $e((new DateTimeImmutable($goal['deadline']))->format('d/m/Y')) : '' ?></span></div>
      <div class="track track-live"><span data-width="<?= round($share, 1) ?>"></span></div>
      <div class="goal-actions">
        <form method="post" action="/minha-conta/financeiro/meta-valor" data-finance-form><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= (int) $goal['id'] ?>"><input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required aria-label="Valor a adicionar em <?= $e($goal['name']) ?>"><button type="submit">Adicionar</button></form>
        <form method="post" action="/minha-conta/financeiro/meta-excluir" data-finance-confirm="Excluir a meta “<?= $e($goal['name']) ?>”?"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= (int) $goal['id'] ?>"><button type="submit" class="finance-link-danger">Excluir</button></form>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="goal-dialog" aria-labelledby="goal-dialog-title">
  <form method="post" action="/minha-conta/financeiro/meta" data-finance-form>
    <header><h2 id="goal-dialog-title">Nova meta</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas">
    <label>Nome<input name="name" maxlength="120" required placeholder="Ex.: Reserva de emergência"></label>
    <div class="finance-dialog-row">
      <label>Valor da meta (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
      <label>Prazo<input name="deadline" type="date"></label>
    </div>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
