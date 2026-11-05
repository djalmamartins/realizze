<?php
$editingOrder = is_array($row ?? null);
$field = static fn (string $key, mixed $fallback = ''): mixed => $editingOrder ? ($row[$key] ?? $fallback) : $fallback;
$orderItems = $editingOrder ? $row['items'] : [['item_type' => 'service', 'description' => '', 'quantity_milli' => 1000, 'unit_price_cents' => null, 'discount_cents' => null]];
?>
<label>Cliente<select name="customer_id" required><option value="">Selecione</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= (int) $field('customer_contact_id', 0) === (int) $customer['id'] ? ' selected' : '' ?>><?= $e($customer['trade_name'] ?: $customer['legal_name']) ?></option><?php endforeach; ?></select></label>
<label>Descrição<textarea name="description" maxlength="5000"><?= $e($field('description')) ?></textarea></label>
<div class="finance-dialog-row">
  <label>Responsável<select name="responsible_user_id"><option value="">Não definido</option><?php foreach ($members as $member): if ($member['status'] === 'active'): ?><option value="<?= (int) $member['user_id'] ?>"<?= (int) $field('responsible_user_id', 0) === (int) $member['user_id'] ? ' selected' : '' ?>><?= $e(trim($member['first_name'] . ' ' . $member['last_name'])) ?></option><?php endif; endforeach; ?></select></label>
  <label>Início<input type="date" name="start_date" value="<?= $e($field('start_date', date('Y-m-d'))) ?>"></label>
  <label>Prazo<input type="date" name="due_date" value="<?= $e($field('due_date')) ?>"></label>
</div>
<fieldset><legend>Serviços e itens</legend><div data-service-order-items>
  <?php foreach ($orderItems as $item): ?>
    <div class="service-order-item" data-service-order-item>
      <div class="finance-dialog-row"><label>Tipo<select name="item_type[]"><option value="service"<?= $item['item_type'] === 'service' ? ' selected' : '' ?>>Serviço</option><option value="item"<?= $item['item_type'] === 'item' ? ' selected' : '' ?>>Item</option></select></label><label>Descrição<input name="item_description[]" value="<?= $e($item['description']) ?>" required></label></div>
      <div class="finance-dialog-row"><label>Quantidade<input name="item_quantity[]" value="<?= $e(number_format((int) $item['quantity_milli'] / 1000, 3, ',', '')) ?>" inputmode="decimal" required></label><label>Valor unitário<input name="item_unit_price[]" value="<?= $item['unit_price_cents'] !== null ? $e(number_format((int) $item['unit_price_cents'] / 100, 2, ',', '.')) : '' ?>" inputmode="decimal" placeholder="0,00" required></label><label>Desconto do item<input name="item_discount[]" value="<?= $item['discount_cents'] !== null ? $e(number_format((int) $item['discount_cents'] / 100, 2, ',', '.')) : '' ?>" inputmode="decimal" placeholder="0,00"></label><button type="button" data-service-order-remove-item>Remover</button></div>
    </div>
  <?php endforeach; ?>
</div><button type="button" data-service-order-add-item>+ Adicionar item</button></fieldset>
<template data-service-order-item-template><div class="service-order-item" data-service-order-item><div class="finance-dialog-row"><label>Tipo<select name="item_type[]"><option value="service">Serviço</option><option value="item">Item</option></select></label><label>Descrição<input name="item_description[]" required></label></div><div class="finance-dialog-row"><label>Quantidade<input name="item_quantity[]" value="1" inputmode="decimal" required></label><label>Valor unitário<input name="item_unit_price[]" inputmode="decimal" placeholder="0,00" required></label><label>Desconto do item<input name="item_discount[]" inputmode="decimal" placeholder="0,00"></label><button type="button" data-service-order-remove-item>Remover</button></div></div></template>
<label>Desconto geral<input name="discount" value="<?= $field('discount_cents', null) !== null ? $e(number_format((int) $field('discount_cents') / 100, 2, ',', '.')) : '' ?>" inputmode="decimal" placeholder="0,00"></label>
<label>Observações<textarea name="notes" maxlength="5000"><?= $e($field('notes')) ?></textarea></label>
<label>Anexos<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt"><small>Até 5 arquivos, 10 MB cada. Novos arquivos são preservados no histórico.</small></label>
