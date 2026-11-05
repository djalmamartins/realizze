<?php
$editingObligation = is_array($row ?? null);
$field = static fn (string $key, mixed $fallback = ''): mixed => $editingObligation ? ($row[$key] ?? $fallback) : $fallback;
?>
<label>Nome<input name="title" maxlength="180" value="<?= $e($field('title')) ?>" required></label>
<div class="finance-dialog-row">
  <label>Tipo<select name="type"><?php foreach ($types as $key => $label): ?><option value="<?= $e($key) ?>"<?= $field('obligation_type', 'other') === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
  <?php if (!$editingObligation): ?><label>Periodicidade<select name="frequency"><option value="once">Única</option><option value="monthly">Mensal (12 ocorrências)</option><option value="quarterly">Trimestral (8 ocorrências)</option><option value="yearly">Anual (5 ocorrências)</option></select></label><?php else: ?><input type="hidden" name="frequency" value="once"><?php endif; ?>
</div>
<div class="finance-dialog-row">
  <label>Competência<input type="date" name="competence_date" value="<?= $e($field('competence_date', date('Y-m-01'))) ?>" required></label>
  <label>Vencimento<input type="date" name="due_date" value="<?= $e($field('due_date', date('Y-m-d'))) ?>" required></label>
</div>
<div class="finance-dialog-row">
  <label>Responsável<select name="responsible_user_id"><option value="">Não definido</option><?php foreach ($members as $member): if ($member['status'] === 'active'): ?><option value="<?= (int) $member['user_id'] ?>"<?= (int) $field('responsible_user_id', 0) === (int) $member['user_id'] ? ' selected' : '' ?>><?= $e(trim($member['first_name'] . ' ' . $member['last_name'])) ?></option><?php endif; endforeach; ?></select></label>
  <label>Valor opcional<input name="amount" inputmode="decimal" value="<?= $field('amount_cents', null) !== null ? $e(number_format((int) $field('amount_cents') / 100, 2, ',', '.')) : '' ?>" placeholder="0,00"></label>
</div>
<label>Descrição<textarea name="description" maxlength="5000"><?= $e($field('description')) ?></textarea></label>
<label>Observações<textarea name="notes" maxlength="5000"><?= $e($field('notes')) ?></textarea></label>
<label>Comprovantes<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt"><small>Até 5 arquivos, 10 MB cada. Novos arquivos são acrescentados ao histórico.</small></label>
