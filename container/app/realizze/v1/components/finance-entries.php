<?php
/** Lista, resumo e modal de lançamentos a receber ou a pagar do contexto atual. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isIncome = $kind === 'income';
$labels = $isIncome
    ? ['title' => 'Contas a receber', 'lead' => 'Receitas, vencimentos e recebimentos do mês.', 'new' => 'Novo recebimento', 'expected' => 'A receber', 'done' => 'Recebido', 'settle' => 'Receber', 'status' => 'Recebido', 'empty' => 'Nenhum recebimento neste mês']
    : ['title' => 'Contas a pagar', 'lead' => 'Despesas, vencimentos e pagamentos do mês.', 'new' => 'Nova despesa', 'expected' => 'A pagar', 'done' => 'Pago', 'settle' => 'Pagar', 'status' => 'Pago', 'empty' => 'Nenhuma despesa neste mês'];
$page = $isIncome ? 'receber' : 'pagar';
$rawMonth = is_string($_GET['mes'] ?? null) ? $_GET['mes'] : '';
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $rawMonth) ? $rawMonth : date('Y-m');
$start = new DateTimeImmutable($month . '-01');
$end = $start->modify('last day of this month');
$status = in_array($_GET['situacao'] ?? '', ['open', 'paid', 'overdue'], true) ? $_GET['situacao'] : 'all';
$monthNames = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$monthLabel = $monthNames[(int) $start->format('n')] . ' de ' . $start->format('Y');
$csrf = (string) ($csrf ?? '');
$loadFailed = false;
$accounts = $categories = $entries = [];
$summary = ['expected' => 0, 'paid' => 0, 'overdue' => 0, 'count' => 0];
try {
    $finance = new Finance((int) FinanceContext::currentId());
    $accounts = $finance->accounts();
    $categories = $finance->categories($kind);
    $entries = $finance->entries($kind, $start->format('Y-m-d'), $end->format('Y-m-d'), $status);
    $summary = $finance->summary($kind, $start->format('Y-m-d'), $end->format('Y-m-d'));
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$today = date('Y-m-d');
$link = static fn (string $target, string $situation = 'all'): string => '/minha-conta?' . http_build_query(['pagina' => $page, 'mes' => $target] + ($situation === 'all' ? [] : ['situacao' => $situation]));
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="entries-title">
  <div class="finance-toolbar">
    <div><h2 id="entries-title"><?= $e($labels['title']) ?></h2><p class="muted"><?= $e($labels['lead']) ?></p></div>
    <button type="button" class="primary" data-finance-open="entry-dialog"<?= $accounts === [] ? ' disabled title="Cadastre uma conta primeiro"' : '' ?>>+ <?= $e($labels['new']) ?></button>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo do mês">
    <div class="finance-goal-stat"><span><?= $e($labels['expected']) ?></span><strong class="<?= $isIncome ? 'green' : 'red' ?>"><?= $e(Finance::money($summary['expected'])) ?></strong><small><?= $e($monthLabel) ?></small></div>
    <div class="finance-goal-stat"><span><?= $e($labels['done']) ?></span><strong><?= $e(Finance::money($summary['paid'])) ?></strong><small><?= (int) $summary['count'] ?> lançamento<?= $summary['count'] === 1 ? '' : 's' ?> no mês</small></div>
    <div class="finance-goal-stat"><span>Em atraso</span><strong class="<?= $summary['overdue'] > 0 ? 'red' : '' ?>"><?= $e(Finance::money($summary['overdue'])) ?></strong><small>Vencidos e não baixados</small></div>
  </div>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Mês">
      <a href="<?= $e($link($start->modify('-1 month')->format('Y-m'), $status)) ?>" aria-label="Mês anterior">‹</a>
      <strong><?= $e($monthLabel) ?></strong>
      <a href="<?= $e($link($start->modify('+1 month')->format('Y-m'), $status)) ?>" aria-label="Próximo mês">›</a>
    </nav>
    <nav class="finance-status" aria-label="Situação">
      <?php foreach (['all' => 'Todos', 'open' => 'Em aberto', 'paid' => $labels['status'], 'overdue' => 'Em atraso'] as $value => $label): ?>
      <a href="<?= $e($link($month, $value)) ?>"<?= $status === $value ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar os lançamentos</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($entries === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon($isIncome ? 'arrow-down-left' : 'arrow-up-right') ?></span><h3><?= $e($labels['empty']) ?></h3><p>Use o botão acima para registrar o primeiro, ou troque o mês ou a situação.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="<?= $e($labels['title']) ?>">
    <table class="finance-table finance-entries">
      <thead><tr><th scope="col">Descrição</th><th scope="col">Vencimento</th><th scope="col">Categoria</th><th scope="col">Conta</th><th scope="col">Situação</th><th scope="col" class="num">Valor</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($entries as $entry): $overdue = $entry['paid_at'] === null && $entry['due_date'] < $today; ?>
      <tr>
        <td><strong><?= $e($entry['description']) ?></strong></td>
        <td><?= $e((new DateTimeImmutable($entry['due_date']))->format('d/m/Y')) ?></td>
        <td><?php if ($entry['category']): ?><span class="finance-dot" style="background:<?= $e($entry['category_color']) ?>"></span><?= $e($entry['category']) ?><?php else: ?>—<?php endif; ?></td>
        <td><?= $e($entry['account']) ?></td>
        <td><span class="finance-badge <?= $entry['paid_at'] ? 'is-paid' : ($overdue ? 'is-overdue' : 'is-open') ?>"><?= $entry['paid_at'] ? $e($labels['status']) : ($overdue ? 'Em atraso' : 'Em aberto') ?></span></td>
        <td class="num <?= $isIncome ? 'green' : 'red' ?>"><strong><?= $isIncome ? '+ ' : '− ' ?><?= $e(Finance::money((int) $entry['amount_cents'])) ?></strong></td>
        <td class="finance-actions">
          <?php if (!$entry['paid_at']): ?>
          <form method="post" action="/minha-conta/financeiro/baixa"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="kind" value="<?= $e($kind) ?>"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><button type="submit"><?= $e($labels['settle']) ?></button></form>
          <?php endif; ?>
          <form method="post" action="/minha-conta/financeiro/excluir" data-finance-confirm="Excluir “<?= $e($entry['description']) ?>”?"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="kind" value="<?= $e($kind) ?>"><input type="hidden" name="mes" value="<?= $e($month) ?>"><input type="hidden" name="id" value="<?= (int) $entry['id'] ?>"><button type="submit" class="is-danger">Excluir</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="entry-dialog" aria-labelledby="entry-dialog-title">
  <form method="post" action="/minha-conta/financeiro/lancamento" data-finance-form>
    <header><h2 id="entry-dialog-title"><?= $e($labels['new']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="kind" value="<?= $e($kind) ?>"><input type="hidden" name="mes" value="<?= $e($month) ?>">
    <label>Descrição<input name="description" maxlength="160" required autofocus></label>
    <div class="finance-dialog-row">
      <label>Valor (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
      <label>Vencimento<input name="due_date" type="date" value="<?= $e(substr($today, 0, 7) === $month ? $today : $month . '-01') ?>" required></label>
    </div>
    <div class="finance-dialog-row">
      <label>Conta<select name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
      <label>Categoria<select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= $e($category['name']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="finance-check"><input type="checkbox" name="paid" value="1"> Já foi <?= $isIncome ? 'recebido' : 'pago' ?> hoje</label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
