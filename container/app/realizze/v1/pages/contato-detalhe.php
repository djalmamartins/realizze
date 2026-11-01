<?php
/** Detalhe do cliente ou fornecedor: totais calculados dos recebimentos vinculados a este contato, nesta empresa. */
use Moves\Services\AppUrl;
use Moves\Services\Contacts;
use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = (string) ($csrf ?? '');
$listSection = ($_GET['papel'] ?? '') === 'supplier' ? 'fornecedores' : 'clientes';
$contextId = (int) FinanceContext::currentId();
$row = (new Contacts($contextId))->find((int) ($_GET['contato_id'] ?? 0));
$id = (int) $row['id'];
$name = $row['trade_name'] ?: ($row['social_name'] ?: $row['legal_name']);
$active = $row['status'] === 'active';
$rows = [];
$failed = false;
try {
    $receivables = new Receivables($contextId);
    $page = 1;
    do {
        $result = $receivables->listing(['start' => '2000-01-01', 'end' => '2100-12-31', 'contact_id' => $id, 'page' => $page, 'per_page' => 50]);
        array_push($rows, ...$result['rows']);
    } while ($page++ < $result['pages']);
} catch (Throwable $exception) {
    error_log('Realizze contact detail: ' . $exception->getMessage());
    $failed = true;
}
$billable = array_filter($rows, static fn (array $r): bool => $r['display_status'] !== 'cancelled');
$sum = static fn (array $list, string $field): int => array_sum(array_column($list, $field));
$overdue = array_filter($billable, static fn (array $r): bool => $r['balance_cents'] > 0 && $r['due_date'] < date('Y-m-d'));
$paid = array_filter($billable, static fn (array $r): bool => $r['received_cents'] > 0);
$statuses = ['pending' => 'Pendente', 'partially_received' => 'Parcial', 'received' => 'Recebido', 'cancelled' => 'Cancelado', 'overdue' => 'Vencido'];
$date = static fn (?string $d): string => $d ? (new DateTimeImmutable($d))->format('d/m/Y') : '—';
$document = (string) ($row['document_value'] ?? '');
$masked = $document === '' ? 'Não informado' : (strlen($document) === 11 ? substr($document, 0, 3) . '.***.***-' . substr($document, -2) : substr($document, 0, 2) . '.***.***/****-' . substr($document, -2));
$address = trim(implode(', ', array_filter([$row['street'] ?? null, $row['address_number'] ?? null, $row['district'] ?? null, $row['city'] ?? null, $row['state'] ?? null])));
?>
<p class="contact-back"><a href="<?= $e(AppUrl::section($listSection)) ?>">‹ Voltar para <?= $listSection === 'clientes' ? 'clientes' : 'fornecedores' ?></a></p>
<section class="card contact-head" aria-label="Dados do contato">
  <span class="profile-photo is-square" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr((string) $name, 0, 2))) ?></span>
  <div class="contact-head-main">
    <h2><?= $e($name) ?></h2>
    <p class="muted"><?= $row['person_type'] === 'company' ? 'CNPJ' : 'CPF' ?> <?= $e($masked) ?></p>
    <p class="contact-tags"><span class="finance-badge is-<?= $active ? 'paid' : 'overdue' ?>"><?= $active ? 'Ativo' : 'Inativo' ?></span><?php if ($row['is_customer']): ?> <span class="finance-badge is-open">Cliente</span><?php endif; ?><?php if ($row['is_supplier']): ?> <span class="finance-badge is-open">Fornecedor</span><?php endif; ?></p>
  </div>
  <button type="button" class="primary" data-finance-open="contact-edit">Editar</button>
  <dl class="contact-details contact-head-grid">
    <div><dt>Telefone</dt><dd><?= $e($row['mobile'] ?: ($row['phone'] ?: 'Não informado')) ?></dd></div>
    <div><dt>E-mail</dt><dd><?= $e($row['email'] ?: 'Não informado') ?></dd></div>
    <div><dt>Responsável</dt><dd><?= $e($row['contact_person'] ?: 'Não informado') ?></dd></div>
    <div><dt>Endereço</dt><dd><?= $e($address ?: 'Não informado') ?></dd></div>
  </dl>
</section>
<div class="detail-layout">
  <section class="card detail-main" data-tabs>
    <div class="detail-tabs" role="tablist" aria-label="Seções do contato">
      <button type="button" role="tab" data-tab="charges" aria-selected="true">Cobranças (<?= count($rows) ?>)</button>
      <button type="button" role="tab" data-tab="payments" aria-selected="false">Pagamentos (<?= count($paid) ?>)</button>
      <button type="button" role="tab" data-tab="notes" aria-selected="false">Observações</button>
    </div>
    <div data-tab-panel="charges">
      <?php if ($failed): ?>
      <div class="finance-empty"><h3>Não foi possível carregar as cobranças</h3><p>Atualize a página. Se continuar, tente novamente em alguns minutos.</p></div>
      <?php elseif ($rows === []): ?>
      <div class="finance-empty"><h3>Nenhuma cobrança vinculada</h3><p><?= $listSection === 'fornecedores' ? 'Pagamentos ainda não podem ser vinculados a fornecedores.' : 'Vincule este cliente ao criar um recebimento.' ?></p></div>
      <?php else: ?>
      <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Cobranças do contato">
        <table class="finance-table finance-entries">
          <thead><tr><th scope="col">Descrição</th><th scope="col">Vencimento</th><th scope="col">Situação</th><th scope="col" class="num">Valor</th><th scope="col" class="num">Saldo</th></tr></thead>
          <tbody><?php foreach ($rows as $charge): ?>
          <tr><td><strong><?= $e($charge['description']) ?></strong><?php if ($charge['document_number']): ?><small class="contact-legal"><?= $e($charge['document_number']) ?></small><?php endif; ?></td><td class="nowrap"><?= $e($date($charge['due_date'])) ?></td><td><span class="finance-badge is-<?= $e($charge['display_status']) ?>"><?= $e($statuses[$charge['display_status']] ?? '') ?></span></td><td class="num"><?= $e(Finance::money($charge['amount_cents'])) ?></td><td class="num"><?= $e(Finance::money($charge['balance_cents'])) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <div data-tab-panel="payments" hidden>
      <?php if ($paid === []): ?>
      <div class="finance-empty"><h3>Nenhum pagamento recebido</h3><p>Os recebimentos deste contato aparecerão aqui.</p></div>
      <?php else: ?>
      <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Pagamentos do contato">
        <table class="finance-table finance-entries">
          <thead><tr><th scope="col">Descrição</th><th scope="col">Conta</th><th scope="col" class="num">Recebido</th></tr></thead>
          <tbody><?php foreach ($paid as $charge): ?><tr><td><strong><?= $e($charge['description']) ?></strong></td><td><?= $e($charge['account']) ?></td><td class="num green"><?= $e(Finance::money($charge['received_cents'])) ?></td></tr><?php endforeach; ?></tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <div data-tab-panel="notes" hidden><p class="detail-notes"><?= $e($row['notes'] ?: 'Sem observações.') ?></p></div>
  </section>
  <aside class="card detail-side" aria-label="Resumo financeiro">
    <h2 class="section-title">Resumo financeiro</h2>
    <div class="detail-stats">
      <div><span>A receber</span><strong><?= $e(Finance::money($sum($billable, 'balance_cents'))) ?></strong></div>
      <div><span>Em atraso</span><strong class="<?= $overdue !== [] ? 'red' : '' ?>"><?= $e(Finance::money($sum($overdue, 'balance_cents'))) ?></strong></div>
      <div><span>Recebido</span><strong class="green"><?= $e(Finance::money($sum($billable, 'received_cents'))) ?></strong></div>
      <div><span>Total faturado</span><strong><?= $e(Finance::money($sum($billable, 'amount_cents'))) ?></strong></div>
    </div>
    <p class="muted">Calculado com os recebimentos vinculados a este contato, sem os cancelados.</p>
  </aside>
</div>
<dialog class="finance-dialog finance-dialog-wide" id="contact-edit" aria-labelledby="contact-edit-title">
  <form method="post" action="/minha-conta/contatos/atualizar" data-finance-form>
    <header><h2 id="contact-edit-title">Editar contato</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="<?= $e($listSection) ?>"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="finance-dialog-scroll"><?php $contact = $row; $defaultRole = ''; require __DIR__ . '/../components/contact-fields.php'; ?></div>
    <footer><button type="button" data-finance-close>Cancelar</button><button class="primary" type="submit">Salvar</button></footer>
  </form>
</dialog>
