<?php
/**
 * Modal única de receitas, despesas, repetições e assinaturas, para criar e editar.
 * Abre por [data-entry-open="income|expense"] ou [data-entry-edit='{...}'] (ver dashboard.js).
 * Espera $form: month, date, back e, opcional, state (campos que a tela quer de volta ao salvar).
 */
use Moves\Services\Contacts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use Moves\Services\RecurringFinance;
use Moves\Services\SubscriptionBrands;

$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
try {
    $contextId = (int) FinanceContext::currentId();
    $entryFinance = new Finance($contextId);
    $entryAccounts = $entryFinance->accounts();
    $entryCategories = ['income' => $entryFinance->categories('income'), 'expense' => $entryFinance->categories('expense')];
    $entryCostCenters = $entryFinance->costCenters();
    $entryCustomers = (new Contacts($contextId))->selectableCustomers();
    $extrasReady = $entryFinance->supportsEntryExtras();
    $extrasTags = $entryFinance->tags();
    $autoReady = (new RecurringFinance($contextId))->occurrencesReady();
} catch (Throwable $exception) {
    error_log('Realizze entry form: ' . $exception->getMessage());
    return;
}
$bankAccounts = array_filter($entryAccounts, static fn (array $account): bool => $account['type'] !== 'credit_card');
$cardAccounts = array_filter($entryAccounts, static fn (array $account): bool => $account['type'] === 'credit_card');
$methods = ['pix' => 'PIX', 'transfer' => 'Transferência', 'cash' => 'Dinheiro', 'boleto' => 'Boleto', 'card' => 'Cartão', 'other' => 'Outra'];
?>
<dialog class="finance-dialog entry-dialog" id="entry-dialog" aria-labelledby="entry-dialog-title">
  <form method="post" action="/minha-conta/financeiro/lancamento" enctype="multipart/form-data" data-finance-form data-entry-form>
    <header><h2 id="entry-dialog-title" data-entry-title>Novo lançamento</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($form['csrf']) ?>"><input type="hidden" name="kind" value="expense"><input type="hidden" name="id" value=""><input type="hidden" name="source" value="entry"><input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>"><input type="hidden" name="mes" value="<?= $e($form['month']) ?>"><input type="hidden" name="back" value="<?= $e($form['back']) ?>">
    <?php foreach (($form['state'] ?? []) as $name => $value): if ($value !== ''): ?><input type="hidden" name="<?= $e($name) ?>" value="<?= $e($value) ?>"><?php endif; endforeach; ?>
    <label>Descrição<input name="description" maxlength="160" required autofocus></label>
    <datalist id="entry-brand-suggestions"><?php foreach (SubscriptionBrands::suggestions() as $suggestion): ?><option value="<?= $e($suggestion) ?>"></option><?php endforeach; ?></datalist>
    <div class="finance-dialog-row">
      <label>Valor (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required data-entry-amount></label>
      <div class="entry-date">
        <label>Data<input name="due_date" type="date" value="<?= $e($form['date']) ?>" required></label>
        <label class="entry-switch" data-entry-paid><input type="checkbox" name="paid" value="1"><span aria-hidden="true"></span><b data-entry-paid-label>Já foi pago</b></label>
      </div>
    </div>
    <div class="finance-dialog-row">
      <label>Conta ou cartão<select name="account_id" required>
        <?php if ($bankAccounts !== []): ?><optgroup label="Contas"><?php foreach ($bankAccounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
        <?php if ($cardAccounts !== []): ?><optgroup label="Cartões"><?php foreach ($cardAccounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
      </select></label>
      <div class="entry-category">
        <label>Categoria<select name="category_id" data-entry-category><option value="">Sem categoria</option><?php foreach ($entryCategories as $categoryKind => $list): foreach ($list as $category): ?><option value="<?= (int) $category['id'] ?>" data-kind="<?= $e($categoryKind) ?>" data-color="<?= $e($category['color']) ?>"><?= $e($category['name']) ?></option><?php endforeach; endforeach; ?></select></label>
      </div>
    </div>
    <?php if ($entryCostCenters !== []): ?><label>Centro de custo<select name="cost_center_id"><option value="">Sem centro de custo</option><?php foreach ($entryCostCenters as $center): ?><option value="<?= (int) $center['id'] ?>"><?= $e($center['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <details class="entry-details" data-entry-income hidden>
      <summary>Dados do recebimento</summary>
      <div class="finance-dialog-row">
        <label>Competência<input name="competence" type="month"></label>
        <label>Forma prevista<select name="payment_method"><?php foreach ($methods as $value => $label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="finance-dialog-row">
        <label>Cliente cadastrado<select name="contact_id"><option value="">Usar origem em texto livre</option><?php foreach ($entryCustomers as $customer): ?><option value="<?= (int) $customer['id'] ?>"><?= $e($customer['trade_name'] ?: $customer['legal_name']) ?></option><?php endforeach; ?></select></label>
        <label>Número do documento<input name="document_number" maxlength="80"></label>
      </div>
      <label>Cliente ou origem em texto livre<input name="counterparty_name" maxlength="160"></label>
    </details>
    <p class="entry-hint" data-entry-edit-note hidden></p>
    <?php require __DIR__ . '/entry-extras.php'; ?>
    <p class="entry-error" role="alert" data-entry-error hidden></p>
    <footer><span class="entry-total" data-entry-total-box>Total <strong data-entry-total>R$ 0,00</strong></span><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary" data-entry-submit>Salvar</button></footer>
  </form>
</dialog>
