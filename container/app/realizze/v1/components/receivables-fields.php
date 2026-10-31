<?php /** Campos compartilhados entre cadastro e edição de recebimentos. */ ?>
<label>Descrição<input name="description" value="<?= $e($formEntry['description']) ?>" maxlength="160" required></label>
<div class="finance-dialog-row">
  <label>Valor (R$)<input name="amount" inputmode="decimal" value="<?= $formEntry['amount_cents'] ? $e(number_format($formEntry['amount_cents'] / 100, 2, ',', '.')) : '' ?>" placeholder="0,00" required></label>
  <label>Vencimento<input name="due_date" type="date" value="<?= $e($formEntry['due_date']) ?>" required></label>
</div>
<div class="finance-dialog-row">
  <label>Competência<input name="competence" type="month" value="<?= $e(substr((string) ($formEntry['competency_date'] ?? $formEntry['due_date'] ?? ''), 0, 7)) ?>" required></label>
  <label>Cliente ou origem<input name="counterparty_name" value="<?= $e($formEntry['counterparty_name']) ?>" maxlength="160"></label>
</div>
<div class="finance-dialog-row">
  <label>Conta de destino<select name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"<?= (int) $account['id'] === (int) $formEntry['account_id'] ? ' selected' : '' ?>><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
  <label>Categoria<select name="category_id" required><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id'] ?>"<?= (int) $item['id'] === (int) $formEntry['category_id'] ? ' selected' : '' ?>><?= $e($item['name']) ?></option><?php endforeach; ?></select></label>
</div>
<div class="finance-dialog-row">
  <label>Número do documento<input name="document_number" value="<?= $e($formEntry['document_number']) ?>" maxlength="80"></label>
  <label>Forma prevista<select name="payment_method" required><?php foreach ($methods as $value => $label): ?><option value="<?= $e($value) ?>"<?= $formEntry['payment_method'] === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
</div>
<label>Observações<textarea name="notes" maxlength="5000" rows="3"><?= $e($formEntry['notes']) ?></textarea></label>
