<?php
/** Contas, carteiras e cartões do contexto financeiro atual, com saldo calculado. */
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\CardBrands;

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
$balances = (new Finance((int) FinanceContext::currentId()))->balanceSummary($accounts);
$available = $balances['available_cents'];
$cards = array_sum(array_map(static fn (array $a): int => $a['type'] === 'credit_card' ? $a['balance_cents'] : 0, $accounts));
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<?= $appView->render('components::reservation-alert', ['shortfalls' => $balances['shortfalls'], 'e' => $e]) ?>
<section class="card finance-workspace" aria-labelledby="accounts-title">
  <div class="finance-toolbar">
    <div><h2 id="accounts-title">Contas e cartões</h2><p class="muted">Saldos calculados com o que já foi pago, recebido e transferido.</p></div>
    <button type="button" class="primary" data-finance-open="account-dialog">+ Adicionar conta</button>
  </div>
  <div class="finance-goal-summary cash-summary" aria-label="Resumo das contas">
    <div class="finance-goal-stat finance-indicator <?= $balances['total_cents'] > 0 ? 'is-positive' : ($balances['total_cents'] < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Saldo total</span><strong><?= $e(Finance::money($balances['total_cents'])) ?></strong><small>Contas e carteiras</small></div>
    <div class="finance-goal-stat finance-indicator is-neutral"><span>Reservado em metas</span><strong><?= $e(Finance::money($balances['reserved_cents'])) ?></strong><small>Continua na conta</small></div>
    <div class="finance-goal-stat finance-indicator <?= $available > 0 ? 'is-positive' : ($available < 0 ? 'is-negative' : 'is-neutral') ?>"><span>Saldo disponível</span><strong><?= $e(Finance::money($available)) ?></strong><small>Total menos reservado</small></div>
    <div class="finance-goal-stat finance-indicator <?= $cards < 0 ? 'is-negative' : 'is-neutral' ?>"><span>Cartões de crédito</span><strong><?= $e(Finance::money($cards)) ?></strong><small>Fora do saldo disponível</small></div>
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
      <?php if ($account['type'] === 'credit_card'): ?>
        <?php $cardBrand = CardBrands::detect((string) $account['name']); $cardBrandLogo = $cardBrand !== null ? CardBrands::logoUrl($cardBrand) : null; ?>
        <?php if ($cardBrand !== null): ?><span class="card-network" title="Bandeira <?= $e(CardBrands::label($cardBrand)) ?>" aria-label="Bandeira <?= $e(CardBrands::label($cardBrand)) ?>">
          <?php if ($cardBrandLogo !== null): ?><img src="<?= $e($cardBrandLogo) ?>" alt="" width="42" height="24" loading="lazy">
          <?php else: ?><?= $e(CardBrands::label($cardBrand)) ?><?php endif; ?>
        </span><?php endif; ?>
      <?php endif; ?>
      <span class="account-balance"><strong class="<?= $account['balance_cents'] < 0 ? 'red' : 'green' ?>"><?= $e(Finance::money($account['balance_cents'])) ?></strong><?php if ($account['reserved_cents'] > 0): ?><small>Reservado <?= $e(Finance::money($account['reserved_cents'])) ?> · Disponível <b class="<?= $account['available_cents'] < 0 ? 'red' : '' ?>"><?= $e(Finance::money($account['available_cents'])) ?></b></small><?php endif; ?><?php if ($account['type'] === 'credit_card'): ?><small><?= $account['closing_day'] ? 'Fecha dia ' . (int) $account['closing_day'] . ' · vence dia ' . (int) $account['due_day'] : 'Sem dia de fechamento: edite o cartão' ?></small><?php endif; ?></span>
      <button type="button" class="finance-button account-edit" data-finance-open="account-edit-<?= (int) $account['id'] ?>" aria-label="Editar <?= $e($account['name']) ?>">Editar</button>
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
    <details class="entry-details"><summary>Dados do cartão de crédito</summary><p class="muted">Só valem quando o tipo é Cartão de crédito. Fechamento e vencimento definem as faturas.</p><div class="finance-dialog-row"><label>Limite (R$)<input name="credit_limit" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?" value=""></label><label>Dia do fechamento<input type="number" name="closing_day" min="1" max="31" value=""></label><label>Dia do vencimento<input type="number" name="due_day" min="1" max="31" value=""></label></div></details>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
<?php foreach ($accounts as $account): $accountId = (int) $account['id']; ?>
<dialog class="finance-dialog" id="account-edit-<?= $accountId ?>" aria-labelledby="account-edit-title-<?= $accountId ?>">
  <form method="post" action="/minha-conta/financeiro/conta-editar" data-finance-form>
    <header><h2 id="account-edit-title-<?= $accountId ?>">Editar conta</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="contas"><input type="hidden" name="id" value="<?= $accountId ?>">
    <label>Nome<input name="name" maxlength="120" required value="<?= $e($account['name']) ?>"></label>
    <div class="finance-dialog-row">
      <label>Tipo<input value="<?= $e($types[$account['type']] ?? '') ?>" disabled></label>
      <label>Banco ou instituição<select name="institution_id"><option value="">Não está na lista</option>
        <?php foreach ($institutions as $institution): ?><option value="<?= (int) $institution['id'] ?>"<?= (int) $institution['id'] === (int) $account['institution_id'] ? ' selected' : '' ?>><?= $e($institution['trade_name'] . ($institution['compe_code'] ? ' (' . $institution['compe_code'] . ')' : '')) ?></option><?php endforeach; ?>
      </select></label>
    </div>
    <label>Outra instituição<input name="institution" maxlength="120" value="<?= $e($account['institution_id'] ? '' : (string) $account['institution']) ?>" placeholder="Só se não estiver na lista"></label>
    <?php if ($account['type'] === 'credit_card'): ?><div class="finance-dialog-row"><label>Limite (R$)<input name="credit_limit" inputmode="decimal" placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $account['credit_limit_cents'] !== null ? $e(number_format((int) $account['credit_limit_cents'] / 100, 2, ',', '.')) : '' ?>"></label><label>Dia do fechamento<input type="number" name="closing_day" min="1" max="31" value="<?= $e($account['closing_day'] ?? '') ?>"></label><label>Dia do vencimento<input type="number" name="due_day" min="1" max="31" value="<?= $e($account['due_day'] ?? '') ?>"></label></div><?php endif; ?>
    <p class="muted">O saldo vem dos lançamentos. Para corrigir, registre uma receita ou despesa de ajuste.</p>
    <footer><button type="submit" class="finance-button is-danger" form="account-archive-<?= $accountId ?>">Arquivar</button><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
  <form method="post" action="/minha-conta/financeiro/conta-arquivar" id="account-archive-<?= $accountId ?>" data-finance-confirm="Arquivar <?= $e($account['name']) ?>? Ela sai das listas, mas o histórico continua."><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="contas"><input type="hidden" name="id" value="<?= $accountId ?>"></form>
</dialog>
<?php endforeach; ?>
