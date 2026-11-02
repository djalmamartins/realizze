<?php
/**
 * Diálogo de novo lançamento, usado em Contas a receber, Contas a pagar e na Visão geral.
 * Espera $form: id, kind, title, accounts, categories, csrf, month, date, back, extras, tags.
 */
$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formId = $form['id'];
$bankAccounts = array_filter($form['accounts'], static fn (array $account): bool => $account['type'] !== 'credit_card');
$cardAccounts = array_filter($form['accounts'], static fn (array $account): bool => $account['type'] === 'credit_card');
?>
<dialog class="finance-dialog entry-dialog" id="<?= $e($formId) ?>" aria-labelledby="<?= $e($formId) ?>-title">
  <form method="post" action="/minha-conta/financeiro/lancamento" enctype="multipart/form-data" data-finance-form data-entry-form>
    <header><h2 id="<?= $e($formId) ?>-title"><?= $e($form['title']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($form['csrf']) ?>"><input type="hidden" name="kind" value="<?= $e($form['kind']) ?>"><input type="hidden" name="mes" value="<?= $e($form['month']) ?>"><?php if ($form['back'] !== ''): ?><input type="hidden" name="back" value="<?= $e($form['back']) ?>"><?php endif; ?>
    <h3 class="entry-section">Informações gerais</h3>
    <label>Descrição<input name="description" maxlength="160" required autofocus></label>
    <div class="finance-dialog-row">
      <label>Conta ou cartão<select name="account_id" required>
        <?php if ($bankAccounts !== []): ?><optgroup label="Contas"><?php foreach ($bankAccounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
        <?php if ($cardAccounts !== []): ?><optgroup label="Cartões"><?php foreach ($cardAccounts as $account): ?><option value="<?= (int) $account['id'] ?>"><?= $e($account['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
      </select></label>
      <div class="entry-category">
        <label>Categoria<select name="category_id" data-entry-category><option value="">Sem categoria</option><?php foreach ($form['categories'] as $category): ?><option value="<?= (int) $category['id'] ?>" data-color="<?= $e($category['color']) ?>"><?= $e($category['name']) ?></option><?php endforeach; ?></select></label>
      </div>
    </div>
    <h3 class="entry-section"><?= $form['kind'] === 'income' ? 'Recebimento' : 'Pagamento' ?></h3>
    <div class="finance-dialog-row">
      <label>Valor (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required data-entry-amount></label>
      <div class="entry-date">
        <label>Data<input name="due_date" type="date" value="<?= $e($form['date']) ?>" required></label>
        <label class="entry-switch"><input type="checkbox" name="paid" value="1"><span aria-hidden="true"></span><?= $form['kind'] === 'income' ? 'Já foi recebido' : 'Já foi pago' ?></label>
      </div>
    </div>
    <?php if ($form['extras']): $extrasId = $formId; $extrasTags = $form['tags']; require __DIR__ . '/entry-extras.php'; endif; ?>
    <div class="entry-recurring-link">
      <strong>É uma assinatura ou cobrança recorrente?</strong>
      <p class="muted">Cadastre uma vez e acompanhe as próximas cobranças em Assinaturas e Recorrências. O recurso de repetição acima continua disponível para parcelas e séries com quantidade definida.</p>
      <a href="<?= $e(\Moves\Services\AppUrl::section('recorrencias', null, ['novo' => '1', 'tipo' => $form['kind']])) ?>">Cadastrar como recorrência</a>
    </div>

    <p class="entry-error" role="alert" data-entry-error hidden></p>
    <footer><span class="entry-total" data-entry-total-box>Total <strong data-entry-total>R$ 0,00</strong></span><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Salvar</button></footer>
  </form>
</dialog>
