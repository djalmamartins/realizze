<?php
/** Contas, carteiras e cartões do contexto financeiro atual, com saldo calculado. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$types = ['checking' => 'Conta corrente', 'savings' => 'Poupança', 'wallet' => 'Carteira', 'credit_card' => 'Cartão de crédito'];
$accounts = [];
$institutions = [];
$loadFailed = false;
try {
    $accounts = (new Finance((int) FinanceContext::currentId()))->accountsWithBalance();
    $institutions = \Moves\Services\FinancialInstitutions::active();
} catch (Throwable $exception) {
    error_log('Realizze finance: ' . $exception->getMessage());
    $loadFailed = true;
}
$available = array_sum(array_map(static fn (array $a): int => $a['type'] === 'credit_card' ? 0 : $a['balance_cents'], $accounts));
$cards = array_sum(array_map(static fn (array $a): int => $a['type'] === 'credit_card' ? $a['balance_cents'] : 0, $accounts));
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="accounts-title">
  <div class="finance-toolbar">
    <div><h2 id="accounts-title">Contas e cartões</h2><p class="muted">Saldos calculados com o que já foi pago, recebido e transferido.</p></div>
    <button type="button" class="primary" data-finance-open="account-dialog">+ Adicionar conta</button>
  </div>
  <div class="finance-goal-summary" aria-label="Resumo das contas">
    <div class="finance-goal-stat"><span>Saldo disponível</span><strong class="<?= $available < 0 ? 'red' : 'green' ?>"><?= $e(Finance::money($available)) ?></strong><small>Contas e carteiras</small></div>
    <div class="finance-goal-stat"><span>Cartões de crédito</span><strong class="<?= $cards < 0 ? 'red' : '' ?>"><?= $e(Finance::money($cards)) ?></strong><small>Fora do saldo disponível</small></div>
    <div class="finance-goal-stat"><span>Contas ativas</span><strong><?= count($accounts) ?></strong><small>Neste contexto</small></div>
  </div>
  <?php if ($loadFailed): ?>
  <div class="finance-empty"><h3>Não foi possível carregar as contas</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
  <?php elseif ($accounts === []): ?>
  <div class="finance-empty"><span class="empty-icon" aria-hidden="true"><?= $icon('credit-card') ?></span><h3>Nenhuma conta cadastrada</h3><p>Adicione sua primeira conta para começar a lançar.</p></div>
  <?php else: ?>
  <ul class="finance-account-list">
    <?php foreach ($accounts as $account): ?>
    <li>
      <?= $appView->render('components::bank-badge', ['name' => $account['institution_name'] ?: ($account['institution'] ?: $account['name']), 'logo' => $account['institution_logo'], 'slug' => $account['institution_slug'], 'type' => $account['type']]) ?>
      <span><strong><?= $e($account['name']) ?></strong><small><?= $e($types[$account['type']] ?? '') ?><?= ($account['institution_name'] ?: $account['institution']) ? ' · ' . $e($account['institution_name'] ?: $account['institution']) : '' ?></small></span>
      <strong class="<?= $account['balance_cents'] < 0 ? 'red' : 'green' ?>"><?= $e(Finance::money($account['balance_cents'])) ?></strong>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="account-dialog" aria-labelledby="account-dialog-title">
  <form method="post" action="/minha-conta/financeiro/conta" data-finance-form>
    <header><h2 id="account-dialog-title">Adicionar conta</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="contas">
    <label>Nome<input name="name" maxlength="120" required autofocus placeholder="Ex.: Conta principal"></label>
    <div class="finance-dialog-row">
      <label>Tipo<select name="type" required><?php foreach ($types as $value => $label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?></select></label>
      <label>Banco ou instituição<select name="institution_id"><option value="">Não está na lista</option>
        <?php foreach (['Principais' => true, 'Todas as instituições' => false] as $groupLabel => $withLogo): ?><optgroup label="<?= $e($groupLabel) ?>">
        <?php foreach ($institutions as $institution): if ((bool) $institution['logo_file'] !== $withLogo) continue; ?><option value="<?= (int) $institution['id'] ?>"><?= $e($institution['trade_name'] . ($institution['compe_code'] ? ' (' . $institution['compe_code'] . ')' : '')) ?></option><?php endforeach; ?>
        </optgroup><?php endforeach; ?>
      </select></label>
    </div>
    <label>Outra instituição<input name="institution" maxlength="120" placeholder="Só se não estiver na lista"></label>
    <label>Saldo inicial (R$)<input name="opening_balance" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?"></label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
