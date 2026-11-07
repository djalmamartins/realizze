<?php
/** Campos do cadastro de contato, em seções. Usado no cadastro e na edição. */
$c = $contact ?? [];
$v = static fn (string $k, mixed $d = '') => $c[$k] ?? $d;
$defaultCustomer = $defaultRole ?? 'customer';
?>
<fieldset class="form-section">
  <legend>Identificação</legend>
  <div class="finance-dialog-row">
    <label>Tipo de pessoa<select name="person_type" required data-contact-type><option value="individual"<?= $v('person_type', 'individual') === 'individual' ? ' selected' : '' ?>>Pessoa física</option><option value="company"<?= $v('person_type') === 'company' ? ' selected' : '' ?>>Pessoa jurídica</option></select></label>
    <label>CPF ou CNPJ<input name="document" value="<?= $e($v('document_value')) ?>" maxlength="18" autocapitalize="characters" spellcheck="false"></label>
  </div>
  <label><span data-contact-name-label><?= $v('person_type') === 'company' ? 'Razão social' : 'Nome completo' ?></span><input name="legal_name" value="<?= $e($v('legal_name')) ?>" maxlength="180" required></label>
  <div class="finance-dialog-row">
    <label>Nome fantasia<input name="trade_name" value="<?= $e($v('trade_name')) ?>" maxlength="180"></label>
    <label>Nome social<input name="social_name" value="<?= $e($v('social_name')) ?>" maxlength="180"></label>
  </div>
  <div class="finance-dialog-row" data-contact-company>
    <label>Inscrição estadual<input name="state_registration" value="<?= $e($v('state_registration')) ?>" maxlength="30"></label>
    <label>Inscrição municipal<input name="municipal_registration" value="<?= $e($v('municipal_registration')) ?>" maxlength="30"></label>
  </div>
  <label data-contact-individual>Data de nascimento<input name="birth_date" type="date" value="<?= $e($v('birth_date')) ?>"></label>
</fieldset>
<fieldset class="form-section">
  <legend>Contato</legend>
  <div class="finance-dialog-row">
    <label>E-mail<input name="email" type="email" value="<?= $e($v('email')) ?>" maxlength="254"></label>
    <label>Responsável<input name="contact_person" value="<?= $e($v('contact_person')) ?>" maxlength="180"></label>
  </div>
  <div class="finance-dialog-row">
    <label>Telefone<input name="phone" value="<?= $e($v('phone')) ?>" maxlength="24"></label>
    <label>Celular / WhatsApp<input name="mobile" value="<?= $e($v('mobile')) ?>" maxlength="24"></label>
  </div>
  <label>Site<input name="website" type="url" value="<?= $e($v('website')) ?>" maxlength="255" placeholder="https://"></label>
</fieldset>
<fieldset class="form-section">
  <legend>Endereço</legend>
  <div class="form-row-address">
    <label>CEP<input name="postal_code" value="<?= $e($v('postal_code')) ?>" inputmode="numeric" maxlength="9"></label>
    <label>Logradouro<input name="street" value="<?= $e($v('street')) ?>" maxlength="180"></label>
    <label>Número<input name="address_number" value="<?= $e($v('address_number')) ?>" maxlength="30"></label>
  </div>
  <div class="finance-dialog-row">
    <label>Complemento<input name="complement" value="<?= $e($v('complement')) ?>" maxlength="100"></label>
    <label>Bairro<input name="district" value="<?= $e($v('district')) ?>" maxlength="100"></label>
  </div>
  <div class="form-row-city">
    <label>Cidade<input name="city" value="<?= $e($v('city')) ?>" maxlength="100"></label>
    <label>UF<input name="state" value="<?= $e($v('state')) ?>" maxlength="2"></label>
  </div>
</fieldset>
<fieldset class="form-section">
  <legend>Informações adicionais</legend>
  <div class="form-checks" role="group" aria-label="Papel do contato">
    <label class="finance-check"><input type="checkbox" name="is_customer" value="1"<?= (int) $v('is_customer', $defaultCustomer !== 'supplier' ? 1 : 0) === 1 ? ' checked' : '' ?>> Cliente</label>
    <label class="finance-check"><input type="checkbox" name="is_supplier" value="1"<?= (int) $v('is_supplier', $defaultCustomer === 'supplier' ? 1 : 0) === 1 ? ' checked' : '' ?>> Fornecedor</label>
  </div>
  <label>Situação<select name="status"><option value="active"<?= $v('status', 'active') === 'active' ? ' selected' : '' ?>>Ativo</option><option value="inactive"<?= $v('status') === 'inactive' ? ' selected' : '' ?>>Inativo</option></select></label>
  <label>Observações<textarea name="notes" maxlength="5000" rows="3"><?= $e($v('notes')) ?></textarea></label>
</fieldset>
