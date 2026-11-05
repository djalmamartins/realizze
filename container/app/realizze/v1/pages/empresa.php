<?php
/** Dados cadastrais da empresa ativa. Edita quem tem team.manage; os demais membros só consultam. */
use Moves\Services\AppUrl;
use Moves\Services\OrganizationAudit;
use Moves\Services\OrganizationContext;
use Moves\Services\OrganizationDirectory;
use Moves\Services\OrganizationTeam;
use Moves\Services\OrganizationLogos;
use Moves\Services\SessionAuth;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$active = OrganizationContext::current();
$company = $active ? OrganizationDirectory::profile((int) $active['id']) : null;
if ($company === null): ?>
<section class="card"><div class="finance-empty"><h3>Selecione uma empresa</h3><p>Escolha a empresa no seletor do menu.</p></div></section>
<?php return; endif;
$userId = (int) (SessionAuth::current()['id'] ?? 0);
$canEdit = OrganizationTeam::can($userId, (int) $company['id'], 'team.manage');
$flash = $_SESSION['finance_flash'] ?? null;
unset($_SESSION['finance_flash']);
// Depois de um erro, o formulário volta com o que a pessoa digitou.
$typed = is_array($_SESSION['company_form'] ?? null) ? $_SESSION['company_form'] : [];
unset($_SESSION['company_form']);
$value = static fn (string $field, mixed $stored): string => (string) ($typed[$field] ?? $stored ?? '');
$document = (string) ($company['document_value'] ?? '');
$cnpj = strlen($document) === 14 ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $document) : '';
$phoneDigits = (string) ($company['phone'] ?? '');
$phone = preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $phoneDigits, $p) ? "({$p[1]}) {$p[2]}-{$p[3]}" : $phoneDigits;
$history = [];
if ($canEdit) {
    try {
        $history = OrganizationAudit::listing((int) $company['id'], ['tipo' => 'company.updated'], 1, 5)['rows'];
    } catch (Throwable $exception) {
        error_log('Realizze company history: ' . $exception->getMessage());
    }
}
$labels = ['name' => 'Razão social', 'trade_name' => 'Nome fantasia', 'document_value' => 'CNPJ', 'email' => 'E-mail', 'phone' => 'Telefone'];
$logoUrl = OrganizationLogos::url($company);
?>
<?php if (is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="profile-hero card">
  <span class="profile-photo is-square company-logo-preview" aria-hidden="true" data-company-logo-preview><?php if ($logoUrl !== null): ?><img src="<?= $e($logoUrl) ?>" alt=""><?php else: ?><?= $e(mb_strtoupper(mb_substr((string) ($company['trade_name'] ?: $company['name']), 0, 2))) ?><?php endif; ?></span>
  <div><h2><?= $e($company['trade_name'] ?: $company['name']) ?></h2><p class="muted"><?= $e($active['role'] === 'owner' ? 'Você é o proprietário' : 'Você é membro') ?> · cadastrada em <?= $e((new DateTimeImmutable((string) $company['created_at']))->format('d/m/Y')) ?></p></div>
</section>
<section class="card" aria-labelledby="company-data">
  <h2 id="company-data" class="section-title">Dados cadastrais</h2>
  <?php if ($canEdit): ?>
  <form class="user-form" method="post" action="/minha-conta/empresa/dados">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <label>Razão social<input name="name" maxlength="180" required value="<?= $e($value('name', $company['name'])) ?>"></label>
    <label>Nome fantasia<input name="trade_name" maxlength="180" value="<?= $e($value('trade_name', $company['trade_name'])) ?>"></label>
    <label>CNPJ<input name="cnpj" inputmode="numeric" maxlength="18" placeholder="00.000.000/0000-00" value="<?= $e($value('cnpj', $cnpj)) ?>"><small>Opcional. Conferimos os dígitos verificadores.</small></label>
    <label>E-mail da empresa<input type="email" name="email" maxlength="255" value="<?= $e($value('email', $company['email'])) ?>"></label>
    <label>Telefone<input name="phone" inputmode="tel" maxlength="20" placeholder="(31) 3333-4444" value="<?= $e($value('phone', $phone)) ?>"></label>
    <label>Endereço no App<input value="<?= $e(AppUrl::base($active)) ?>" disabled><small>Não muda quando o nome muda, para manter os links salvos.</small></label>
    <div class="is-wide profile-actions"><button class="finance-button is-primary" type="submit">Salvar dados</button></div>
  </form>
  <?php else: ?>
  <dl class="contact-details">
    <div><dt>Razão social</dt><dd><?= $e($company['name']) ?></dd></div>
    <div><dt>Nome fantasia</dt><dd><?= $e($company['trade_name'] ?: 'Não informado') ?></dd></div>
    <div><dt>CNPJ</dt><dd><?= $e($cnpj ?: 'Não informado') ?></dd></div>
    <div><dt>E-mail</dt><dd><?= $e($company['email'] ?: 'Não informado') ?></dd></div>
    <div><dt>Telefone</dt><dd><?= $e($phone ?: 'Não informado') ?></dd></div>
    <div><dt>Situação</dt><dd><?= $company['status'] === 'active' ? 'Ativa' : 'Inativa' ?></dd></div>
  </dl>
  <p class="profile-pending">Somente o proprietário e administradores alteram estes dados.</p>
  <?php endif; ?>
</section>
<section class="card" aria-labelledby="company-identity">
  <div class="profile-heading"><div><h2 id="company-identity" class="section-title">Identidade empresarial</h2><p class="muted">O logotipo aparece somente para pessoas autorizadas nesta empresa. Na ausência dele, o Realizze usa a identidade padrão.</p></div></div>
  <?php if ($canEdit && OrganizationLogos::ready()): ?>
  <form class="user-form" method="post" action="/minha-conta/empresa/logo" enctype="multipart/form-data" data-company-logo-form>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <label class="is-wide">Logotipo JPG ou PNG<input type="file" name="logo" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required data-company-logo-input><small>Até 5 MB, mínimo 40×40 px. A imagem é redimensionada e regravada sem metadados.</small></label>
    <div class="is-wide profile-actions"><button class="finance-button is-primary" type="submit"><?= $logoUrl === null ? 'Enviar logotipo' : 'Substituir logotipo' ?></button></div>
  </form>
  <?php if ($logoUrl !== null): ?><form method="post" action="/minha-conta/empresa/logo-remover" data-finance-confirm="Remover o logotipo desta empresa?"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><button type="submit" class="finance-button is-danger">Remover logotipo</button></form><?php endif; ?>
  <?php elseif (!$canEdit): ?><p class="profile-pending">Somente o proprietário e administradores alteram a identidade.</p><?php else: ?><p class="profile-pending">A migration de identidade empresarial precisa ser aplicada antes do upload.</p><?php endif; ?>
</section>
<?php if ($canEdit): ?>
<section class="card" aria-labelledby="company-history">
  <div class="profile-heading"><div><h2 id="company-history" class="section-title">Últimas alterações</h2><p class="muted">Quem mudou os dados cadastrais, e quando.</p></div><a class="finance-button" href="<?= $e(AppUrl::section('auditoria')) ?>">Ver auditoria</a></div>
  <?php if ($history === []): ?>
  <p class="muted">Nenhuma alteração registrada.</p>
  <?php else: ?>
  <ul class="profile-rows">
    <?php foreach ($history as $row): ?>
    <li><span><strong><?= $e(implode(', ', array_map(static fn (string $field): string => $labels[$field] ?? $field, array_keys(array_intersect_key($labels, $row['changes']))))) ?: 'Dados alterados' ?></strong><small><?= $e($row['actor_name'] ?: 'Conta removida') ?> · <?= $e((new DateTimeImmutable((string) $row['created_at']))->format('d/m/Y H:i')) ?></small></span></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endif; ?>
