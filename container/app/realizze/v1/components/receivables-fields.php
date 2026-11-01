<?php /** Campos compartilhados entre cadastro e edição de recebimentos. */ ?>
<h3 class="entry-section">Informações gerais</h3>
<label>Descrição<input name="description" value="<?= $e($formEntry['description']) ?>" maxlength="160" required></label>
<div class="finance-dialog-row">
  <label>Competência<input name="competence" type="month" value="<?= $e(substr((string) ($formEntry['competency_date'] ?? $formEntry['due_date'] ?? ''), 0, 7)) ?>" required></label>
  <label>Cliente cadastrado<select name="contact_id"><option value="">Usar origem em texto livre</option><?php foreach (($customers ?? []) as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= (int) ($formEntry['contact_id'] ?? 0) === (int) $customer['id'] ? ' selected' : '' ?>><?= $e($customer['trade_name'] ?: $customer['legal_name']) ?></option><?php endforeach; ?></select></label>
</div>
<label>Cliente ou origem em texto livre<input name="counterparty_name" value="<?= $e($formEntry['counterparty_name']) ?>" maxlength="160" placeholder="Compatibilidade com lançamentos antigos"></label>
<div class="finance-dialog-row">
  <div class="entry-category"><label>Categoria<select name="category_id" required data-entry-category><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id'] ?>"<?= (int) $item['id'] === (int) $formEntry['category_id'] ? ' selected' : '' ?>><?= $e($item['name']) ?></option><?php endforeach; ?></select></label></div>
  <label>Número do documento<input name="document_number" value="<?= $e($formEntry['document_number']) ?>" maxlength="80"></label>
</div>
<h3 class="entry-section">Recebimento</h3>
<div class="finance-dialog-row">
  <label>Valor (R$)<input name="amount" inputmode="decimal" value="<?= $formEntry['amount_cents'] ? $e(number_format($formEntry['amount_cents'] / 100, 2, ',', '.')) : '' ?>" placeholder="0,00" required></label>
  <label>Vencimento<input name="due_date" type="date" value="<?= $e($formEntry['due_date']) ?>" required></label>
</div>
<div class="finance-dialog-row">
  <label>Conta de destino<select name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"<?= (int) $account['id'] === (int) $formEntry['account_id'] ? ' selected' : '' ?>><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
  <label>Forma prevista<select name="payment_method" required><?php foreach ($methods as $value => $label): ?><option value="<?= $e($value) ?>"<?= $formEntry['payment_method'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
</div>
<?php if (empty($entryCreateForm)): ?><label>Observações<textarea name="notes" maxlength="5000" rows="3"><?= $e($formEntry['notes']) ?></textarea></label><?php endif; ?>
