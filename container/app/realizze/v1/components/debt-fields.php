<?php
/**
 * Campos do cadastro/edição de dívida. Espera $values (linha da dívida ou []),
 * $institutionSelect, $accounts e $e definidos pela página sair-das-dividas.
 */
use Moves\Services\Debts;

$v = $values ?? [];
$moneyValue = static fn (?int $cents): string => $cents === null ? '' : number_format($cents / 100, 2, ',', '.');
$rateValue = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 4, ',', ''), '0'), ',');
?>
<div class="finance-dialog-row">
  <label>Instituição<select name="institution_id"><?= $institutionSelect(isset($v['institution_id']) ? (int) $v['institution_id'] : null) ?></select></label>
  <label>Credor<input name="creditor_name" maxlength="160" placeholder="Se a instituição não estiver na lista" value="<?= $e($v['creditor_name'] ?? '') ?>"></label>
</div>
<?php if (!empty($controlReady)): ?><label>Descrição<input name="description" maxlength="160" placeholder="Opcional. Ex.: Empréstimo da reforma" value="<?= $e($v['description'] ?? '') ?>"></label><?php endif; ?>
<div class="finance-dialog-row">
  <label>Tipo<select name="debt_type"><?php foreach (Debts::TYPES as $value => $label): ?><option value="<?= $e($value) ?>"<?= ($v['debt_type'] ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
  <label>Situação<select name="status"><?php foreach (Debts::STATUSES as $value => $label): ?><option value="<?= $e($value) ?>"<?= ($v['status'] ?? 'current') === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
</div>
<div class="finance-dialog-row">
  <label>Saldo devedor hoje (R$)<input name="balance" inputmode="decimal" required placeholder="0,00" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $e($moneyValue($v['balance_cents'] ?? null)) ?>"></label>
  <label>Valor original (R$)<input name="original_amount" inputmode="decimal" placeholder="Opcional" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $e($moneyValue($v['original_amount_cents'] ?? null)) ?>"></label>
</div>
<div class="finance-dialog-row">
  <label>Juros (% ao mês)<input name="monthly_rate" inputmode="decimal" placeholder="Ex.: 2,5" pattern="[0-9]{1,4}(,[0-9]{1,4})?" value="<?= $e($rateValue($v['monthly_rate'] ?? null)) ?>"></label>
  <label>CET (% ao ano)<input name="cet_annual" inputmode="decimal" placeholder="Se souber" pattern="[0-9]{1,4}(,[0-9]{1,4})?" value="<?= $e($rateValue($v['cet_annual'] ?? null)) ?>"></label>
</div>
<div class="finance-dialog-row">
  <label>Valor da parcela (R$)<input name="monthly_payment" inputmode="decimal" placeholder="Ou pagamento mínimo" pattern="[0-9.]*(,[0-9]{1,2})?" value="<?= $e($moneyValue($v['monthly_payment_cents'] ?? null)) ?>"></label>
  <label>Quantidade de parcelas restantes<input name="installments_remaining" type="number" min="0" max="600" value="<?= $e($v['installments_remaining'] ?? '') ?>"></label>
  <label>Próximo vencimento<input name="next_due_date" type="date" value="<?= $e($v['next_due_date'] ?? '') ?>"></label>
</div>
<div class="finance-dialog-row">
  <label>Garantia<select name="collateral"><?php foreach (Debts::COLLATERALS as $value => $label): ?><option value="<?= $e($value) ?>"<?= ($v['collateral'] ?? 'none') === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
  <label>Conta usada para pagar<select name="account_id"><option value="">Nenhum</option><?php foreach ($accounts as $account): ?><option value="<?= (int) $account['id'] ?>"<?= (int) ($v['account_id'] ?? 0) === (int) $account['id'] ? ' selected' : '' ?>><?= $e($account['name']) ?></option><?php endforeach; ?></select></label>
</div>
<label>Riscos relevantes<input name="risk_notes" maxlength="500" placeholder="Ex.: ação judicial, negativação, multa contratual" value="<?= $e($v['risk_notes'] ?? '') ?>"></label>
<label>Observações<input name="notes" maxlength="1000" value="<?= $e($v['notes'] ?? '') ?>"></label>
