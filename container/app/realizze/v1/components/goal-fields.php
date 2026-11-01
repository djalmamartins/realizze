<?php
/**
 * Campos da edição da meta.
 * Parâmetros: $goal, $institutions, $details (migration 009 aplicada), $e, $photoUrl (ou null).
 */
$value = static fn (string $key): string => $e($goal[$key] ?? '');
$amount = number_format((int) $goal['target_cents'] / 100, 2, ',', '');
$monthly = $goal['monthly_contribution_cents'] === null ? '' : number_format((int) $goal['monthly_contribution_cents'] / 100, 2, ',', '');
$iconLabels = ['carro' => 'Carro', 'aviao' => 'Avião', 'casa' => 'Casa', 'educacao' => 'Educação', 'coracao' => 'Coração', 'maleta' => 'Trabalho', 'guardar' => 'Guardar', 'jogo' => 'Lazer', 'camera' => 'Câmera', 'carteira' => 'Carteira', 'alvo' => 'Alvo', 'mais' => 'Outro'];
?>
<?php if ($details): ?>
<div class="goal-photo-field" data-goal-photo>
  <div class="goal-photo-preview<?= $photoUrl ? ' has-photo' : '' ?>" data-goal-photo-preview<?= $photoUrl ? ' style="background-image:url(&quot;' . $e($photoUrl) . '&quot;)"' : '' ?>></div>
  <label>Foto do objetivo<input type="file" name="photo" accept="image/jpeg,image/png" data-goal-photo-input><small class="muted">JPG ou PNG, até 5 MB. Ex.: o carro que você quer.</small></label>
  <p class="goal-photo-error" data-goal-photo-error hidden>Essa foto passa de 5 MB. Escolha outra.</p>
  <?php if ($photoUrl): ?><label class="goal-photo-remove"><input type="checkbox" name="remove_photo" value="1"> Remover a foto atual</label><?php endif; ?>
</div>
<?php endif; ?>
<label>Nome<input name="name" maxlength="120" required value="<?= $value('name') ?>" placeholder="Ex.: Carro novo"></label>
<div class="finance-dialog-row">
  <label>Valor da meta (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required value="<?= $e($amount) ?>"></label>
  <label>Prazo<input name="deadline" type="date" value="<?= $value('deadline') ?>"></label>
</div>
<?php if ($details): ?>
<div class="finance-dialog-row">
  <label>Categoria<select name="category"><?php foreach (\Moves\Services\Finance::GOAL_CATEGORIES as $key => $label): ?><option value="<?= $e($key) ?>"<?= ($goal['category'] ?? 'outra') === $key ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
  <label>Ícone<select name="icon"><?php foreach (\Moves\Services\Finance::GOAL_ICONS as $key): ?><option value="<?= $e($key) ?>"<?= ($goal['icon'] ?? 'alvo') === $key ? ' selected' : '' ?>><?= $e($iconLabels[$key]) ?></option><?php endforeach; ?></select></label>
</div>
<label>Contribuição mensal (R$, opcional)<input name="monthly" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" value="<?= $e($monthly) ?>"></label>
<fieldset class="goal-place-fields">
  <legend>Onde você está juntando</legend>
  <label>Nome da caixinha ou conta<input name="savings_place" maxlength="120" value="<?= $value('savings_place') ?>" placeholder="Ex.: Caixinha Carro"></label>
  <div class="finance-dialog-row">
    <label>Banco<select name="institution_id"><option value="">Não está na lista</option>
      <?php foreach ($institutions as $institution): ?><option value="<?= (int) $institution['id'] ?>"<?= (int) ($goal['institution_id'] ?? 0) === (int) $institution['id'] ? ' selected' : '' ?>><?= $e($institution['trade_name']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Outro banco<input name="institution" maxlength="120" value="<?= $value('institution') ?>" placeholder="Só se não estiver na lista"></label>
  </div>
</fieldset>
<?php endif; ?>
