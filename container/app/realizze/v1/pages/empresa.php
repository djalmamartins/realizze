<?php
/** Dados da empresa ativa: leitura real; edição e logo aguardam o backend. */
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationDirectory;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$active = OrganizationContext::current();
$company = $active ? OrganizationDirectory::profile((int) $active['id']) : null;
if ($company === null): ?>
<section class="card"><div class="finance-empty"><h3>Selecione uma empresa</h3><p>Escolha a empresa no seletor do menu.</p></div></section>
<?php return; endif;
$document = (string) ($company['document_value'] ?? '');
$formatted = strlen($document) === 14 ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $document) : 'Não informado';
?>
<section class="profile-hero card">
  <span class="profile-photo is-square" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr((string) $company['name'], 0, 2))) ?></span>
  <div><h2><?= $e($company['name']) ?></h2><p class="muted"><?= $e($active['role'] === 'owner' ? 'Você é o proprietário' : 'Você é membro') ?></p></div>
  <button type="button" disabled title="Envio de logo ainda não disponível">Alterar logo</button>
</section>
<section class="card" aria-labelledby="company-data">
  <h2 id="company-data" class="section-title">Dados cadastrais</h2>
  <dl class="contact-details">
    <div><dt>Razão social</dt><dd><?= $e($company['name']) ?></dd></div>
    <div><dt>Nome fantasia</dt><dd><?= $e($company['trade_name'] ?: 'Não informado') ?></dd></div>
    <div><dt>CNPJ</dt><dd><?= $e($formatted) ?></dd></div>
    <div><dt>E-mail</dt><dd><?= $e($company['email'] ?: 'Não informado') ?></dd></div>
    <div><dt>Telefone</dt><dd><?= $e($company['phone'] ?: 'Não informado') ?></dd></div>
    <div><dt>Situação</dt><dd><?= $company['status'] === 'active' ? 'Ativa' : 'Inativa' ?></dd></div>
  </dl>
  <p class="profile-pending">A edição dos dados da empresa ainda não está disponível.</p>
</section>
