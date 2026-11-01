<?php
/** Formulário de abertura, usado na página e na aba lateral. Sem identificadores de usuário ou empresa. */
use Moves\Services\Csrf; use Moves\Services\Helpdesk; use Moves\Services\OrganizationContext; use Moves\Support\CmsView as V;
$old = is_array($supportOld ?? null) ? $supportOld : [];
$formId = $formId ?? 'sp';
$organization = OrganizationContext::current();
?>
<form method="post" action="/suporte/abrir" enctype="multipart/form-data" class="sp-form">
<input type="hidden" name="csrf_token" value="<?= V::e(Csrf::token()) ?>"><input type="hidden" name="idempotency_key" value="<?= V::e(bin2hex(random_bytes(16))) ?>"><input type="hidden" name="support_type" value="<?= V::e($old['support_type'] ?? 'technical') ?>" data-support-type><input type="hidden" name="module_hint" value="<?= V::e($old['module_hint'] ?? '') ?>" data-support-module>
<label for="<?= $formId ?>-subject">Assunto</label><input id="<?= $formId ?>-subject" name="subject" required maxlength="190" value="<?= V::e($old['subject'] ?? '') ?>">
<label for="<?= $formId ?>-category">Categoria</label><select id="<?= $formId ?>-category" name="category_id" required><option value="">Selecione</option><?php foreach (Helpdesk::categories(true) as $category): ?><option value="<?= (int) $category['id'] ?>"<?= (string) ($old['category_id'] ?? '') === (string) $category['id'] ? ' selected' : '' ?>><?= V::e($category['name']) ?></option><?php endforeach; ?></select>
<label for="<?= $formId ?>-priority">Prioridade sugerida</label><select id="<?= $formId ?>-priority" name="priority"><?php foreach (V::PRIORITIES as $key => $label): ?><option value="<?= V::e($key) ?>"<?= ($old['priority'] ?? 'normal') === $key ? ' selected' : '' ?>><?= V::e($label) ?></option><?php endforeach; ?></select>
<?php if ($organization !== null): ?><label for="<?= $formId ?>-context">Contexto</label><select id="<?= $formId ?>-context" name="context"><option value="organization"><?= V::e($organization['name'] ?? 'Empresa atual') ?></option><option value="personal">Pessoal</option></select><?php endif; ?>
<label for="<?= $formId ?>-body">Mensagem</label><textarea id="<?= $formId ?>-body" name="body" rows="5" required maxlength="10000"><?= V::e($old['body'] ?? '') ?></textarea>
<label for="<?= $formId ?>-files">Anexar arquivos</label><input id="<?= $formId ?>-files" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt"><small>Imagem, PDF ou texto, até 10 MB cada. Não envie senhas nem dados completos de cartão.</small>
<button class="sp-btn sp-btn-primary" type="submit">Enviar</button>
</form>
