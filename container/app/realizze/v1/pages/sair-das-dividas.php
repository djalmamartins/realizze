<?php
/** Sair das Dívidas (PF): diagnóstico, dívidas, estratégias e simulador. Cálculos no DebtPlanner. */
use Moves\Services\AiAdvisor;
use Moves\Services\DebtPlanner;
use Moves\Services\Debts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\FinancialInstitutions;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = static fn (?int $cents): string => $cents === null ? '—' : Finance::money($cents);
$rate = static fn (?float $value): string => $value === null ? 'Não informada' : number_format($value, 2, ',', '.') . '% a.m.';
$csrf = (string) ($csrf ?? '');
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
$queryCents = static function (string $key): ?int {
    $value = trim((string) ($_GET[$key] ?? ''));
    if ($value === '') {
        return null;
    }
    try {
        return Finance::parseCents($value);
    } catch (InvalidArgumentException) {
        return null;
    }
};
$monthLabel = static function (?string $ym): string {
    if ($ym === null) {
        return '—';
    }
    $names = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    [$year, $month] = array_map('intval', explode('-', $ym));
    return $names[$month - 1] . '/' . $year;
};
$duration = static function (?int $months): string {
    if ($months === null) {
        return '—';
    }
    $years = intdiv($months, 12);
    $rest = $months % 12;
    $parts = [];
    if ($years > 0) $parts[] = $years . ' ano' . ($years > 1 ? 's' : '');
    if ($rest > 0) $parts[] = $rest . ' ' . ($rest > 1 ? 'meses' : 'mês');
    return $parts === [] ? 'menos de 1 mês' : implode(' e ', $parts);
};

$loadFailed = false;
$debts = $open = $institutions = $accounts = $entries = $categories = [];
$panorama = ['total_cents' => 0, 'paid_cents' => 0, 'monthly_cents' => 0, 'progress' => 0, 'late' => [], 'upcoming' => []];
$controlReady = false;
$profile = null;
$suggested = ['income_cents' => 0, 'expense_cents' => 0, 'months' => 0];
try {
    $service = new Debts((int) FinanceContext::currentId());
    $debts = $service->all();
    $open = $service->open();
    $profile = $service->profile();
    $suggested = $service->suggestedAverages();
    $entries = $service->linkableEntries();
    $panorama = $service->panorama();
    $controlReady = $service->controlReady();
    $categories = $service->expenseCategories();
    $institutions = FinancialInstitutions::active();
    $accounts = (new Finance((int) FinanceContext::currentId()))->accountsWithBalance();
} catch (Throwable $exception) {
    error_log('Realizze debts: ' . $exception->getMessage());
    $loadFailed = true;
}

// Cenários: aumento de renda e corte de despesas, só na simulação (não altera o diagnóstico salvo).
$extraIncome = $queryCents('renda_extra') ?? 0;
$cut = $queryCents('corte') ?? 0;
$capacity = null;
if ($profile !== null) {
    $scenario = $profile;
    $scenario['net_income_cents'] += $extraIncome;
    $scenario['variable_expenses_cents'] = max(0, $scenario['variable_expenses_cents'] - $cut);
    $capacity = DebtPlanner::capacity($scenario, $open);
}
// Sem valor informado, o plano usa o maior entre a capacidade calculada e as parcelas que já existem.
$budgetBase = $queryCents('orcamento') ?? max($capacity['capacity_cents'] ?? 0, $panorama['monthly_cents']);
$extraPayment = $queryCents('extra') ?? 0;
$budget = $budgetBase + $extraPayment;
// Taxa desconhecida entra como 0% para a dívida não sumir do plano; a tela avisa que o prazo pode ser maior.
$planDebts = array_map(static fn (array $d): array => [
    'id' => (int) $d['id'], 'name' => $d['creditor_name'], 'balance_cents' => $d['balance_cents'],
    'monthly_rate' => $d['monthly_rate'] ?? 0.0, 'monthly_payment_cents' => $d['monthly_payment_cents'],
], $open);
$comparison = $open !== [] && $budget > 0 ? DebtPlanner::compare($planDebts, $budget) : null;
$baseComparison = $extraPayment > 0 && $open !== [] && $budgetBase > 0 ? DebtPlanner::compare($planDebts, $budgetBase) : null;
$bestPlan = $comparison !== null && $comparison['recommended'] !== null ? $comparison[$comparison['recommended']] : null;
$withoutRate = array_values(array_filter($open, static fn (array $d): bool => $d['monthly_rate'] === null));
$secured = array_values(array_filter($open, static fn (array $d): bool => in_array($d['collateral'], ['vehicle', 'property', 'payroll', 'guarantor'], true)));
$totalDebt = array_sum(array_column($open, 'balance_cents'));
$debtNames = array_column($open, 'creditor_name', 'id');

// Simulador de negociação (GET, nada é gravado).
$simDebtId = (int) filter_var($_GET['sim_divida'] ?? 0, FILTER_VALIDATE_INT);
$simBalance = $queryCents('sim_saldo');
foreach ($open as $d) {
    if ((int) $d['id'] === $simDebtId && $simBalance === null) {
        $simBalance = $d['balance_cents'];
    }
}
$simulation = null;
$simError = null;
if ($simBalance !== null && isset($_GET['simular'])) {
    try {
        $simInstallments = (int) filter_var($_GET['sim_parcelas'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 420, 'default' => 0]]);
        $simRate = trim((string) ($_GET['sim_taxa'] ?? ''));
        $simInstallment = $queryCents('sim_parcela');
        $simDown = $queryCents('sim_entrada') ?? 0;
        if ($simInstallment === null && $simInstallments > 0 && $simRate !== '') {
            $simInstallment = DebtPlanner::installment(max(1, $simBalance - $simDown), (float) str_replace(',', '.', $simRate), $simInstallments);
        }
        $simulation = DebtPlanner::evaluateProposal($simBalance, $simDown, $simInstallments, $simInstallment ?? 0, $capacity['capacity_cents'] ?? null);
        $simulation['installment_cents'] = $simInstallment ?? 0;
        $simulation['installments'] = $simInstallments;
    } catch (InvalidArgumentException $exception) {
        $simError = $exception->getMessage();
    }
}
$situationText = [
    'no_income' => ['red', 'Informe sua renda para calcular a capacidade de pagamento.'],
    'deficit' => ['red', 'Suas despesas já são maiores que a renda. Antes de assumir parcelas, é preciso reduzir gastos ou aumentar a renda; nenhum plano de quitação cabe hoje.'],
    'no_margin' => ['red', 'Depois da margem para imprevistos e da reserva, não sobra valor seguro para dívidas. Qualquer acordo agora tende a ser rompido.'],
    'over_committed' => ['amber', 'As parcelas atuais passam da sua capacidade sustentável. Vale renegociar prazos ou valores antes de atrasar.'],
    'ok' => ['green', 'As parcelas atuais cabem na sua capacidade sustentável.'],
];
$reasonText = [
    'no_debts' => 'Nenhuma dívida com saldo para simular.',
    'missing_rates' => 'Informe a taxa de juros das dívidas para simular.',
    'below_minimums' => 'O valor mensal não cobre as parcelas mínimas atuais.',
    'interest_exceeds_payment' => 'O valor mensal não cobre nem os juros do mês: a dívida cresceria.',
    'exceeds_horizon' => 'Não quita em 30 anos com esse valor.',
];
$collateralRisk = [
    'vehicle' => 'o atraso pode levar à busca e apreensão do veículo',
    'property' => 'o atraso pode levar à perda do imóvel dado em garantia',
    'payroll' => 'o desconto é feito direto no salário ou benefício',
    'guarantor' => 'o atraso é cobrado também do avalista ou fiador',
];
$strategyNames = ['avalanche' => 'Avalanche (maiores juros primeiro)', 'snowball' => 'Bola de neve (menor saldo primeiro)', 'minimum' => 'Só as parcelas atuais'];
$institutionSelect = static function (?int $selected = null) use ($institutions, $e): string {
    $html = '<option value="">Não está na lista</option>';
    $groups = ['Principais' => [], 'Todas as instituições' => []];
    foreach ($institutions as $i) {
        $groups[$i['logo_file'] ? 'Principais' : 'Todas as instituições'][] = $i;
    }
    foreach ($groups as $label => $rows) {
        if ($rows === []) continue;
        $html .= '<optgroup label="' . $e($label) . '">';
        foreach ($rows as $i) {
            $code = $i['compe_code'] ? ' (' . $i['compe_code'] . ')' : '';
            $html .= '<option value="' . (int) $i['id'] . '"' . ((int) $i['id'] === $selected ? ' selected' : '') . '>' . $e($i['trade_name'] . $code) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
};
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>

<?php if ($loadFailed): ?>
<section class="card finance-workspace"><div class="finance-empty"><h3>Não foi possível carregar o Sair das Dívidas</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div></section>
<?php else: ?>

<section class="card finance-workspace debt-answers" aria-labelledby="debt-answers-title">
  <h2 id="debt-answers-title" class="visually-hidden">Resumo das dívidas</h2>
  <div class="finance-goal-summary debt-summary">
    <div class="finance-goal-stat finance-indicator <?= $panorama['total_cents'] > 0 ? 'is-negative' : 'is-neutral' ?>"><span>Quanto eu devo</span><strong><?= $e($money($panorama['total_cents'])) ?></strong><small><?= count($open) ?> dívida<?= count($open) === 1 ? '' : 's' ?> em aberto</small></div>
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Quanto pagar por mês</span><strong><?= $e($money($panorama['monthly_cents'])) ?></strong><small><?= $bestPlan !== null && $budget > $panorama['monthly_cents'] ? 'No plano: ' . $e($money($budget)) : 'Soma das parcelas informadas' ?></small></div>
    <div class="finance-goal-stat finance-indicator <?= $bestPlan !== null ? 'is-positive' : 'is-neutral' ?>"><span>Quando saio das dívidas</span><strong><?= $open === [] ? '—' : ($bestPlan !== null ? $e($monthLabel($bestPlan['payoff_date'])) : 'Sem previsão') ?></strong><small><?= $bestPlan !== null ? 'Estimativa com ' . $e($money($budget)) . ' por mês' : ($open === [] ? 'Nada em aberto' : 'Informe um valor mensal que cubra os juros') ?></small></div>
  </div>
  <?php if ($debts !== []): ?>
  <div class="debt-progress">
    <div class="budget-head"><strong>Progresso de quitação</strong><span class="muted"><?= (int) $panorama['progress'] ?>% · já pago <?= $e($money($panorama['paid_cents'])) ?></span></div>
    <div class="track track-live debt-track" role="img" aria-label="<?= (int) $panorama['progress'] ?>% pago"><span data-width="<?= (int) $panorama['progress'] ?>"></span></div>
  </div>
  <?php endif; ?>
  <?php if ($panorama['late'] !== []): ?><p class="debt-alert debt-alert-red" role="status"><?= count($panorama['late']) === 1 ? '1 parcela em atraso' : count($panorama['late']) . ' parcelas em atraso' ?>: <?= $e(implode(', ', array_map(static fn (array $d): string => $d['creditor_name'] . ' (' . (new DateTimeImmutable($d['next_due_date']))->format('d/m') . ')', $panorama['late']))) ?>.</p><?php endif; ?>
  <?php if ($panorama['upcoming'] !== []): ?>
  <div class="debt-upcoming"><h3>Próximos vencimentos</h3><ul><?php foreach ($panorama['upcoming'] as $d): ?><li><span><?= $e((new DateTimeImmutable($d['next_due_date']))->format('d/m/Y')) ?></span><span><?= $e($d['creditor_name']) ?></span><strong><?= $e($money($d['monthly_payment_cents'])) ?></strong></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
</section>

<section class="card finance-workspace" aria-labelledby="debt-diagnosis-title">
  <div class="finance-toolbar">
    <div><h2 id="debt-diagnosis-title">Diagnóstico financeiro</h2><p class="muted">Quanto dá para pagar por mês sem comprometer o essencial.</p></div>
    <button type="button" class="primary" data-finance-open="debt-profile-dialog"><?= $profile === null ? 'Fazer diagnóstico' : 'Atualizar diagnóstico' ?></button>
  </div>
  <?php if ($profile === null): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('target') ?></span><h3>Comece pelo diagnóstico</h3><p>Informe renda e despesas do mês. Com isso calculamos uma capacidade de pagamento segura, com margem para imprevistos.</p></div>
  <?php else: ?>
  <div class="finance-goal-summary debt-summary" aria-label="Resumo do diagnóstico">
    <div class="finance-goal-stat finance-indicator is-neutral"><span>Renda líquida</span><strong><?= $e($money($capacity['net_income_cents'])) ?></strong><small><?= $extraIncome > 0 ? 'Com cenário de +' . $e($money($extraIncome)) : 'Por mês' ?></small></div>
    <div class="finance-goal-stat finance-indicator is-neutral"><span>Despesas e compromissos</span><strong><?= $e($money($capacity['essential_expenses_cents'] + $capacity['variable_expenses_cents'] + $capacity['other_commitments_cents'])) ?></strong><small>Essenciais <?= $e($money($capacity['essential_expenses_cents'])) ?> · variáveis <?= $e($money($capacity['variable_expenses_cents'])) ?></small></div>
    <div class="finance-goal-stat finance-indicator <?= $capacity['free_cents'] > 0 ? 'is-positive' : ($capacity['free_cents'] < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Sobra</span><strong><?= $e($money($capacity['free_cents'])) ?></strong><small>Antes das dívidas</small></div>
    <div class="finance-goal-stat finance-indicator <?= $capacity['capacity_cents'] > 0 ? 'is-positive' : 'is-neutral' ?>"><span>Capacidade sustentável</span><strong><?= $e($money($capacity['capacity_cents'])) ?></strong><small>Para dívidas, por mês</small></div>
  </div>
  <?php [$tone, $text] = $situationText[$capacity['situation']]; ?>
  <p class="debt-alert debt-alert-<?= $e($tone) ?>" role="status"><?= $e($text) ?></p>
  <details class="debt-details">
    <summary>Como calculamos</summary>
    <ul class="debt-assumptions">
      <li>Margem para imprevistos: <?= $e($money($capacity['buffer_cents'])) ?>.</li>
      <li>Reserva de emergência: <?= $e($money($capacity['reserve_cents'])) ?> de <?= $e($money($capacity['reserve_target_cents'])) ?> recomendados<?= $capacity['reserve_contribution_cents'] > 0 ? '; ' . $e($money($capacity['reserve_contribution_cents'])) . ' por mês vão para a reserva' : '' ?>.</li>
      <li>Parcelas atuais das dívidas: <?= $e($money($capacity['current_payments_cents'])) ?><?= $capacity['income_commitment'] !== null ? ' (' . $e(number_format($capacity['income_commitment'] * 100, 1, ',', '.')) . '% da renda)' : '' ?>.</li>
      <?php foreach ($capacity['assumptions'] as $assumption): ?><li><?= $e($assumption) ?></li><?php endforeach; ?>
    </ul>
  </details>
  <?php endif; ?>
</section>

<section class="card finance-workspace" aria-labelledby="debt-list-title">
  <div class="finance-toolbar">
    <div><h2 id="debt-list-title">Minhas dívidas</h2><p class="muted"><?= count($open) ?> em aberto · total <?= $e($money($totalDebt)) ?></p></div>
    <button type="button" class="primary" data-finance-open="debt-dialog">+ Cadastrar dívida</button>
  </div>
  <?php if ($debts === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('credit-card') ?></span><h3>Nenhuma dívida cadastrada</h3><p>Cadastre cada dívida com saldo, juros e parcela. Quanto mais exatos os dados, mais confiável a simulação.</p></div>
  <?php else: ?>
  <?php if ($withoutRate !== []): ?><p class="debt-alert debt-alert-amber"><?= count($withoutRate) ?> dívida<?= count($withoutRate) > 1 ? 's estão' : ' está' ?> sem taxa de juros: nas simulações, entra<?= count($withoutRate) > 1 ? 'm' : '' ?> sem juros, então prazo e custo podem ser maiores. Consulte o contrato ou o app do credor; não estimamos taxas.</p><?php endif; ?>
  <ul class="debt-list">
    <?php foreach ($debts as $debt): $debtId = (int) $debt['id']; $closed = in_array($debt['status'], ['paid', 'cancelled'], true); ?>
    <li class="debt-item<?= $closed ? ' debt-item-closed' : '' ?>">
      <div class="debt-head">
        <?= $appView->render('components::bank-badge', ['name' => $debt['institution_name'] ?: $debt['creditor_name'], 'logo' => $debt['institution_logo'], 'slug' => $debt['institution_slug']]) ?>
        <div class="debt-title"><strong><?= $e($debt['creditor_name']) ?></strong><small><?= $debt['description'] ? $e($debt['description']) . ' · ' : '' ?><?= $e(Debts::TYPES[$debt['debt_type']] ?? '') ?><?= $debt['account_name'] ? ' · ' . $e($debt['account_name']) : '' ?></small></div>
        <?php $shownStatus = !$closed && $debt['status'] === 'current' && $debt['next_due_date'] !== null && $debt['next_due_date'] < date('Y-m-d') ? 'overdue' : $debt['status']; ?><span class="debt-status debt-status-<?= $e($shownStatus) ?>"><?= $e(Debts::STATUSES[$shownStatus] ?? '') ?></span>
        <strong class="debt-balance"><?= $e($money($debt['balance_cents'])) ?></strong>
      </div>
      <dl class="debt-facts">
        <div><dt>Juros</dt><dd><?= $e($rate($debt['monthly_rate'])) ?></dd></div>
        <div><dt>CET</dt><dd><?= $debt['cet_annual'] === null ? '—' : $e(number_format($debt['cet_annual'], 2, ',', '.')) . '% a.a.' ?></dd></div>
        <div><dt>Parcela</dt><dd><?= $e($money($debt['monthly_payment_cents'])) ?><?= $debt['installments_remaining'] !== null ? ' · ' . (int) $debt['installments_remaining'] . 'x' : '' ?></dd></div>
        <div><dt>Vencimento</dt><dd><?= $debt['next_due_date'] ? $e((new DateTimeImmutable($debt['next_due_date']))->format('d/m/Y')) : '—' ?></dd></div>
        <div><dt>Garantia</dt><dd><?= $e(Debts::COLLATERALS[$debt['collateral']] ?? '') ?></dd></div>
        <div><dt>Já pago</dt><dd><?= $e($money($debt['paid_cents'])) ?></dd></div>
        <div><dt>Valor original</dt><dd><?= $e($money($debt['original_amount_cents'])) ?></dd></div>
      </dl>
      <?php if ($debt['risk_notes']): ?><p class="muted debt-note">Riscos: <?= $e($debt['risk_notes']) ?></p><?php endif; ?>
      <details class="debt-details">
        <summary>Pagamentos, negociações e edição</summary>
        <div class="debt-panels">
          <?php if (!$closed): ?>
          <form method="post" action="/minha-conta/dividas/pagamento" class="debt-form" data-finance-form>
            <h3>Registrar pagamento</h3>
            <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $debtId ?>"><input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
            <div class="finance-dialog-row">
              <label>Valor (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>
              <label>Data<input name="paid_on" type="date" value="<?= $e(date('Y-m-d')) ?>" required></label>
            </div>
            <label>Lançamento já existente (opcional)<select name="entry_id"><option value="">Não vincular</option><?php foreach ($entries as $entry): ?><option value="<?= (int) $entry['id'] ?>"><?= $e((new DateTimeImmutable($entry['due_date']))->format('d/m') . ' · ' . $entry['description'] . ' · ' . Finance::money((int) $entry['amount_cents'])) ?></option><?php endforeach; ?></select></label>
            <div class="finance-dialog-row">
              <label>Conta do pagamento<select name="account_id"><option value=""><?= $debt['account_name'] ? $e('Conta da dívida (' . $debt['account_name'] . ')') : 'Escolha, se saiu de uma conta' ?></option><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?><option value="none">Pago por fora: não mexer em conta</option></select></label>
              <label>Categoria no Planejamento<select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= $e($category['name']) ?></option><?php endforeach; ?></select></label>
            </div>
            <p class="muted">Ao vincular, o lançamento é baixado na data informada e não se cria outra saída. Sem vínculo, a saída é criada na conta escolhida. O pagamento parcial abate o saldo, mas não avança o vencimento.</p>
            <button type="submit">Registrar</button>
          </form>
          <form method="post" action="/minha-conta/dividas/negociacao" class="debt-form" data-finance-form>
            <h3>Registrar proposta ou acordo</h3>
            <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $debtId ?>">
            <div class="finance-dialog-row">
              <label>Entrada (R$)<input name="down_payment" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
              <label>Parcelas<input name="installments" type="number" min="0" max="420" value="0"></label>
              <label>Valor da parcela (R$)<input name="installment" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
            </div>
            <div class="finance-dialog-row">
              <label>Quem propôs<select name="origin"><option value="user">Eu</option><option value="creditor">Credor</option></select></label>
              <label>Situação<select name="status"><?php foreach (Debts::NEGOTIATION_STATUSES as $value => $label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?></select></label>
              <label>1º vencimento<input name="first_due_date" type="date"></label>
            </div>
            <label>Observações<input name="notes" maxlength="1000"></label>
            <button type="submit">Salvar no histórico</button>
          </form>
          <?php endif; ?>
          <?php $payments = $service->payments($debtId); $negotiations = $service->negotiations($debtId); ?>
          <div class="debt-history">
            <h3>Histórico</h3>
            <?php if ($payments === [] && $negotiations === []): ?><p class="muted">Nenhum pagamento ou negociação registrado.</p><?php endif; ?>
            <?php if ($payments !== []): ?>
            <ul>
              <?php foreach ($payments as $payment): ?>
              <?php $reversed = ($payment['reversed_at'] ?? null) !== null; ?><li<?= $reversed ? ' class="debt-reversed"' : '' ?>><strong><?= $e(Finance::money((int) $payment['amount_cents'])) ?></strong> em <?= $e((new DateTimeImmutable($payment['paid_on']))->format('d/m/Y')) ?> · saldo <?= $e(Finance::money((int) $payment['balance_before_cents'])) ?> → <?= $e(Finance::money((int) $payment['balance_after_cents'])) ?><?= $payment['entry_description'] ? ' · vinculado a “' . $e($payment['entry_description']) . '”' : '' ?><?php if ($reversed): ?> · <strong>Estornado</strong><?php elseif ($controlReady): ?><form method="post" action="/minha-conta/dividas/pagamento-estornar" class="debt-reverse" data-finance-confirm="Estornar este pagamento? O saldo da dívida volta e a saída na conta é desfeita."><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $payment['id'] ?>"><button type="submit" class="finance-link-danger">Estornar</button></form><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php foreach ($negotiations as $negotiation): $eval = DebtPlanner::evaluateProposal(max(1, (int) $negotiation['balance_at_proposal_cents']), (int) $negotiation['down_payment_cents'], (int) $negotiation['installment_cents'] > 0 ? (int) $negotiation['installments'] : 0, (int) $negotiation['installment_cents']); ?>
            <div class="debt-negotiation">
              <?php if (($negotiation['applied_at'] ?? null) !== null): ?><p class="debt-tag">Acordo em vigor: os termos anteriores estão guardados</p><?php endif; ?>
              <p><strong><?= $e(Debts::NEGOTIATION_STATUSES[$negotiation['status']] ?? '') ?></strong> · <?= $negotiation['origin'] === 'creditor' ? 'do credor' : 'minha' ?> · <?= $e((new DateTimeImmutable($negotiation['created_at']))->format('d/m/Y')) ?></p>
              <p class="muted">Entrada <?= $e(Finance::money((int) $negotiation['down_payment_cents'])) ?><?= (int) $negotiation['installment_cents'] > 0 ? ' + ' . (int) $negotiation['installments'] . 'x ' . $e(Finance::money((int) $negotiation['installment_cents'])) : '' ?> = <?= $e(Finance::money($eval['total_cents'])) ?> (<?= $eval['discount_cents'] >= 0 ? 'desconto de ' . $e(Finance::money($eval['discount_cents'])) : 'acréscimo de ' . $e(Finance::money(-$eval['discount_cents'])) ?> sobre <?= $e(Finance::money((int) $negotiation['balance_at_proposal_cents'])) ?>)</p>
              <?php if ($negotiation['notes']): ?><p class="muted"><?= $e($negotiation['notes']) ?></p><?php endif; ?>
              <form method="post" action="/minha-conta/dividas/negociacao-situacao" class="debt-inline-form">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $negotiation['id'] ?>">
                <label>Situação<select name="status"><?php foreach (Debts::NEGOTIATION_STATUSES as $value => $label): ?><option value="<?= $e($value) ?>"<?= $value === $negotiation['status'] ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
                <button type="submit">Atualizar</button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
          <form method="post" action="/minha-conta/dividas/atualizar" class="debt-form" data-finance-form>
            <h3>Editar dados</h3>
            <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $debtId ?>">
            <?php $values = $debt; require __DIR__ . '/../components/debt-fields.php'; ?>
            <button type="submit">Salvar alterações</button>
          </form>
          <form method="post" action="/minha-conta/dividas/remover" data-finance-confirm="Remover “<?= $e($debt['creditor_name']) ?>” da lista? O histórico é mantido.">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= $debtId ?>">
            <button type="submit" class="finance-link-danger">Remover da lista</button>
          </form>
        </div>
      </details>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<?php if ($secured !== []): ?>
<section class="card finance-workspace" aria-labelledby="debt-risk-title">
  <h2 id="debt-risk-title">Dívidas com garantia: atenção</h2>
  <ul class="debt-assumptions">
    <?php foreach ($secured as $debt): ?><li><strong><?= $e($debt['creditor_name']) ?></strong>: <?= $e($collateralRisk[$debt['collateral']]) ?>. Priorize manter essa parcela em dia mesmo que a estratégia indique outra ordem.</li><?php endforeach; ?>
  </ul>
  <p class="muted">Deixar de pagar qualquer dívida gera multa, juros de mora, negativação e cobrança judicial. Se não couber, procure o credor para renegociar antes do atraso.</p>
</section>
<?php endif; ?>

<section class="card finance-workspace" aria-labelledby="debt-strategy-title">
  <div class="finance-toolbar">
    <div><h2 id="debt-strategy-title">Estratégias de quitação</h2><p class="muted">Mesma quantia por mês, ordens diferentes de pagamento.</p></div>
  </div>
  <form method="get" action="/p/sair-das-dividas" class="debt-scenario">
    <label>Valor por mês para dívidas (R$)<input name="orcamento" inputmode="decimal" value="<?= $budgetBase > 0 ? $e(number_format($budgetBase / 100, 2, ',', '.')) : '' ?>" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <label>Pagamento adicional por mês (R$)<input name="extra" inputmode="decimal" value="<?= $extraPayment > 0 ? $e(number_format($extraPayment / 100, 2, ',', '.')) : '' ?>" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <label>Cenário: renda extra (R$)<input name="renda_extra" inputmode="decimal" value="<?= $extraIncome > 0 ? $e(number_format($extraIncome / 100, 2, ',', '.')) : '' ?>" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <label>Cenário: corte em despesas variáveis (R$)<input name="corte" inputmode="decimal" value="<?= $cut > 0 ? $e(number_format($cut / 100, 2, ',', '.')) : '' ?>" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <button type="submit">Simular</button>
  </form>
  <?php if ($capacity !== null && $open !== [] && $budget > $capacity['capacity_cents']): ?>
  <p class="debt-alert debt-alert-amber">Esse valor passa da sua capacidade sustentável (<?= $e($money($capacity['capacity_cents'])) ?>). O plano pode ficar apertado diante de imprevistos.</p>
  <?php endif; ?>
  <?php if ($open === []): ?>
  <p class="muted">Cadastre suas dívidas para comparar as estratégias.</p>
  <?php elseif ($budget <= 0): ?>
  <p class="debt-alert debt-alert-red">Sem valor mensal disponível para dívidas, não há plano de quitação viável hoje. Faça o diagnóstico ou simule um cenário de renda extra ou corte de despesas.</p>
  <?php else: ?>
  <div class="debt-table-wrap">
    <table class="debt-table">
      <caption class="visually-hidden">Comparação das estratégias com <?= $e($money($budget)) ?> por mês</caption>
      <thead><tr><th scope="col">Estratégia</th><th scope="col">Prazo</th><th scope="col">Quitação provável</th><th scope="col">Juros</th><th scope="col">Total pago</th><th scope="col">Economia</th></tr></thead>
      <tbody>
      <?php foreach (['avalanche', 'snowball', 'minimum'] as $key): $run = $comparison[$key]; ?>
        <tr<?= $comparison['recommended'] === $key ? ' class="debt-recommended"' : '' ?>>
          <th scope="row"><?= $e($strategyNames[$key]) ?><?= $comparison['recommended'] === $key ? ' <span class="debt-tag">Menor custo</span>' : '' ?></th>
          <?php if ($run['feasible']): ?>
          <td><?= $e($duration($run['months'])) ?></td><td><?= $e($monthLabel($run['payoff_date'])) ?></td><td><?= $e($money($run['total_interest_cents'])) ?></td><td><?= $e($money($run['total_paid_cents'])) ?></td>
          <td><?= $key === 'minimum' ? '—' : ($run['savings_cents'] === null ? 'Sem base de comparação' : $e($money($run['savings_cents']))) ?></td>
          <?php else: ?>
          <td colspan="5" class="red"><?= $e($reasonText[$run['reason']] ?? 'Não viável.') ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($baseComparison !== null && $bestPlan !== null && $baseComparison['recommended'] !== null && ($baseRun = $baseComparison[$baseComparison['recommended']])['feasible']): ?>
  <p class="debt-alert debt-alert-green" role="status">Com <?= $e($money($extraPayment)) ?> a mais por mês, a estimativa é sair <?= $e($duration(max(0, $baseRun['months'] - $bestPlan['months']))) ?> antes e pagar <?= $e($money(max(0, $baseRun['total_interest_cents'] - $bestPlan['total_interest_cents']))) ?> a menos de juros.</p>
  <?php endif; ?>
  <?php $best = $comparison['recommended'] ? $comparison[$comparison['recommended']] : null; ?>
  <?php if ($best !== null): ?>
  <p class="muted">Ordem sugerida (<?= $e($strategyNames[$comparison['recommended']]) ?>): <?php $order = $best['debts']; usort($order, static fn ($a, $b) => ($a['paid_off_month'] ?? 999) <=> ($b['paid_off_month'] ?? 999)); echo $e(implode(' → ', array_map(static fn ($d) => $d['name'] . ' (' . $monthLabel((new DateTimeImmutable('first day of next month'))->modify('+' . max(0, (int) $d['paid_off_month'] - 1) . ' months')->format('Y-m')) . ')', $order))); ?></p>
  <?php endif; ?>
  <details class="debt-details"><summary>Hipóteses usadas</summary><ul class="debt-assumptions"><?php foreach ($comparison['avalanche']['assumptions'] as $assumption): ?><li><?= $e($assumption) ?></li><?php endforeach; ?><li>A economia compara com pagar só as parcelas atuais.</li><?php if ($withoutRate !== []): ?><li>Dívidas sem taxa informada entram sem juros.</li><?php endif; ?></ul></details>
  <?php endif; ?>
</section>

<section class="card finance-workspace" aria-labelledby="debt-sim-title">
  <h2 id="debt-sim-title">Simulador de negociação</h2>
  <p class="muted">Compare uma proposta de desconto ou de reparcelamento com o saldo atual. Nada é gravado.</p>
  <form method="get" action="/p/sair-das-dividas#debt-sim-title" class="debt-scenario">
    <input type="hidden" name="simular" value="1">
    <label>Dívida<select name="sim_divida"><option value="">Informar saldo</option><?php foreach ($open as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) $d['id'] === $simDebtId ? ' selected' : '' ?>><?= $e($d['creditor_name'] . ' · ' . Finance::money($d['balance_cents'])) ?></option><?php endforeach; ?></select></label>
    <label>Ou saldo (R$)<input name="sim_saldo" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <label>Entrada (R$)<input name="sim_entrada" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <label>Parcelas<input name="sim_parcelas" type="number" min="0" max="420" value="<?= (int) ($_GET['sim_parcelas'] ?? 0) ?>"></label>
    <label>Valor da parcela (R$)<input name="sim_parcela" inputmode="decimal" placeholder="Proposta do credor" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <label>Ou taxa (% a.m.)<input name="sim_taxa" inputmode="decimal" placeholder="Calcula a parcela" pattern="[0-9]+(,[0-9]{1,4})?"></label>
    <button type="submit">Simular proposta</button>
  </form>
  <?php if ($simError !== null): ?><p class="debt-alert debt-alert-red"><?= $e($simError) ?></p><?php endif; ?>
  <?php if ($simulation !== null): ?>
  <div class="finance-goal-summary debt-summary">
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Total da proposta</span><strong><?= $e($money($simulation['total_cents'])) ?></strong><small><?= $simulation['installments'] > 0 ? $simulation['installments'] . 'x ' . $e($money($simulation['installment_cents'])) : 'À vista' ?></small></div>
    <div class="finance-goal-stat finance-indicator <?= $simulation['discount_cents'] >= 0 ? 'is-positive' : 'is-negative' ?>"><span><?= $simulation['discount_cents'] >= 0 ? 'Desconto' : 'Acréscimo' ?></span><strong><?= $e($money(abs($simulation['discount_cents']))) ?></strong><small><?= $e(number_format(abs($simulation['discount_rate']) * 100, 1, ',', '.')) ?>% do saldo</small></div>
    <div class="finance-goal-stat finance-indicator is-forecast"><span>Juros embutidos</span><strong><?= $simulation['implied_monthly_rate'] === null ? '—' : $e(number_format($simulation['implied_monthly_rate'], 2, ',', '.')) . '% a.m.' ?></strong><small>Taxa implícita da proposta</small></div>
    <div class="finance-goal-stat finance-indicator <?= $simulation['fits_capacity'] === null ? 'is-neutral' : ($simulation['fits_capacity'] ? 'is-positive' : 'is-negative') ?>"><span>Cabe no orçamento?</span><strong><?= $simulation['fits_capacity'] === null ? 'Faça o diagnóstico' : ($simulation['fits_capacity'] ? 'Sim' : 'Não') ?></strong><small>Parcela x capacidade</small></div>
  </div>
  <ul class="debt-assumptions"><?php foreach ($simulation['assumptions'] as $assumption): ?><li><?= $e($assumption) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
</section>

<section class="card finance-workspace" aria-labelledby="debt-ai-title">
  <h2 id="debt-ai-title">Análise com IA</h2>
  <?php if (!AiAdvisor::available()): ?>
  <p class="debt-alert debt-alert-amber">Análise por IA indisponível: nenhum provedor está configurado. Os cálculos acima não dependem de IA e continuam funcionando.</p>
  <?php endif; ?>
  <p class="muted">Quando for ativada, a IA só vai explicar os números calculados aqui. Ela não inventa taxas, não garante acordos, não faz pagamentos e só recebe dados sem identificação, com sua autorização.</p>
</section>

<p class="muted debt-disclaimer">Simulações educativas, baseadas nos dados que você informou. Não são oferta de crédito nem garantia de aprovação de acordos.</p>

<dialog class="finance-dialog" id="debt-profile-dialog" aria-labelledby="debt-profile-title">
  <form method="post" action="/minha-conta/dividas/diagnostico" data-finance-form>
    <header><h2 id="debt-profile-title">Diagnóstico financeiro</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <?php if ($suggested['months'] > 0): ?><p class="muted">Pelos seus lançamentos pagos dos últimos 3 meses: média de <?= $e($money($suggested['income_cents'])) ?> de receitas e <?= $e($money($suggested['expense_cents'])) ?> de despesas por mês. Use como referência.</p><?php endif; ?>
    <?php $field = static fn (string $key) => $profile !== null && $profile[$key . '_cents'] > 0 ? number_format($profile[$key . '_cents'] / 100, 2, ',', '.') : ''; ?>
    <div class="finance-dialog-row">
      <label>Renda líquida mensal (R$)<input name="net_income" inputmode="decimal" required placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" value="<?= $e($field('net_income')) ?>"></label>
      <label>Sua renda é<select name="income_stability"><option value="stable">Estável (salário, aposentadoria)</option><option value="variable"<?= ($profile['income_stability'] ?? '') === 'variable' ? ' selected' : '' ?>>Variável (autônomo, comissão)</option></select></label>
    </div>
    <div class="finance-dialog-row">
      <label>Despesas essenciais (R$)<input name="essential_expenses" inputmode="decimal" placeholder="Moradia, comida, saúde" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $e($field('essential_expenses')) ?>"></label>
      <label>Despesas variáveis (R$)<input name="variable_expenses" inputmode="decimal" placeholder="Lazer, compras" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $e($field('variable_expenses')) ?>"></label>
    </div>
    <div class="finance-dialog-row">
      <label>Outros compromissos fixos (R$)<input name="other_commitments" inputmode="decimal" placeholder="Fora as dívidas cadastradas" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $e($field('other_commitments')) ?>"></label>
      <label>Reserva de emergência atual (R$)<input name="emergency_reserve" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $e($field('emergency_reserve')) ?>"></label>
    </div>
    <p class="muted">Não inclua as parcelas das dívidas cadastradas nas despesas: elas são consideradas à parte.</p>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Calcular</button></footer>
  </form>
</dialog>

<dialog class="finance-dialog" id="debt-dialog" aria-labelledby="debt-dialog-title">
  <form method="post" action="/minha-conta/dividas/salvar" data-finance-form>
    <header><h2 id="debt-dialog-title">Cadastrar dívida</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <?php $values = []; require __DIR__ . '/../components/debt-fields.php'; ?>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
<?php endif; ?>
