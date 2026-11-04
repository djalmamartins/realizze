<?php
/**
 * Modais da carteira de uma meta: guardar, resgatar, transferir, pagar, programar e histórico.
 * Parâmetros: $goal, $today, $wallet (operações), $legacy (aportes fora das contas), $accounts, $goalAccounts (conta => reservado), $otherGoals, $categories, $schedule, $csrf, $e.
 */
use Moves\Services\AppUrl;
use Moves\Services\Finance;
use Moves\Services\GoalWallets;

$id = (int) $goal['id'];
$money = static fn (int $cents): string => Finance::money($cents);
$hidden = static fn (): string => '<input type="hidden" name="csrf_token" value="' . $e($csrf) . '"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="idempotency_key" value="' . bin2hex(random_bytes(16)) . '">';
$amountField = '<label>Valor (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required></label>';
$dateField = '<label>Data<input name="date" type="date" value="' . $e($today) . '" max="' . $e($today) . '" required></label>';
$noteField = '<label>Observação (opcional)<input name="note" maxlength="255"></label>';
$cash = array_values(array_filter($accounts, static fn (array $a): bool => $a['type'] !== 'credit_card'));
$reservedAccounts = array_values(array_filter($cash, static fn (array $a): bool => isset($goalAccounts[(int) $a['id']])));
$reservedSelect = static function () use ($reservedAccounts, $goalAccounts, $e, $money): string {
    $html = '<label>Conta<select name="account_id" required>';
    foreach ($reservedAccounts as $account) {
        $html .= '<option value="' . (int) $account['id'] . '">' . $e($account['name']) . ' · reservado ' . $e($money($goalAccounts[(int) $account['id']])) . '</option>';
    }
    return $html . '</select></label>';
};
$empty = '<p class="muted">Esta meta ainda não tem dinheiro reservado em uma conta.</p>';
$days = range(1, 31);
?>
<dialog class="finance-dialog" id="goal-add-<?= $id ?>" aria-labelledby="goal-add-title-<?= $id ?>">
  <form method="post" action="/minha-conta/financeiro/meta-guardar" data-finance-form>
    <header><h2 id="goal-add-title-<?= $id ?>">Guardar em <?= $e($goal['name']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?= $hidden() ?>
    <p class="muted">Este valor fica separado na conta. O saldo do banco não muda, e não entra como despesa.</p>
    <?php if ($cash === []): ?>
    <p class="muted">Cadastre uma conta para guardar dinheiro nesta meta. <a href="<?= $e(AppUrl::section('contas')) ?>">Ir para Contas</a></p>
    <footer><button type="button" data-finance-close>Fechar</button></footer>
    <?php else: ?>
    <label>Conta de origem<select name="account_id" required>
      <?php foreach ($cash as $account): ?><option value="<?= (int) $account['id'] ?>"<?= (int) $goal['account_id'] === (int) $account['id'] ? ' selected' : '' ?>><?= $e($account['name']) ?> · disponível <?= $e($money($account['available_cents'])) ?></option><?php endforeach; ?>
    </select></label>
    <div class="finance-dialog-row"><?= $amountField ?><?= $dateField ?></div>
    <?= $noteField ?>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Guardar dinheiro</button></footer>
    <?php endif; ?>
  </form>
</dialog>
<dialog class="finance-dialog" id="goal-release-<?= $id ?>" aria-labelledby="goal-release-title-<?= $id ?>">
  <form method="post" action="/minha-conta/financeiro/meta-resgatar" data-finance-form>
    <header><h2 id="goal-release-title-<?= $id ?>">Resgatar de <?= $e($goal['name']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?= $hidden() ?>
    <?php if ($reservedAccounts === []): ?><?= $empty ?><footer><button type="button" data-finance-close>Fechar</button></footer>
    <?php else: ?>
    <p class="muted">O valor volta a ficar disponível na conta. O saldo do banco não muda.</p>
    <?= $reservedSelect() ?>
    <div class="finance-dialog-row"><?= $amountField ?><?= $dateField ?></div>
    <?= $noteField ?>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Resgatar dinheiro</button></footer>
    <?php endif; ?>
  </form>
</dialog>
<dialog class="finance-dialog" id="goal-transfer-<?= $id ?>" aria-labelledby="goal-transfer-title-<?= $id ?>">
  <form method="post" action="/minha-conta/financeiro/meta-transferir" data-finance-form>
    <header><h2 id="goal-transfer-title-<?= $id ?>">Transferir de <?= $e($goal['name']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?= $hidden() ?>
    <?php if ($reservedAccounts === []): ?><?= $empty ?><footer><button type="button" data-finance-close>Fechar</button></footer>
    <?php elseif ($otherGoals === []): ?><p class="muted">Crie outra meta para receber este valor.</p><footer><button type="button" data-finance-close>Fechar</button></footer>
    <?php else: ?>
    <label>Meta de destino<select name="target_goal_id" required><?php foreach ($otherGoals as $other): ?><option value="<?= (int) $other['id'] ?>"><?= $e($other['name']) ?></option><?php endforeach; ?></select></label>
    <?= $reservedSelect() ?>
    <div class="finance-dialog-row"><?= $amountField ?><?= $dateField ?></div>
    <?= $noteField ?>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Transferir reserva</button></footer>
    <?php endif; ?>
  </form>
</dialog>
<dialog class="finance-dialog" id="goal-pay-<?= $id ?>" aria-labelledby="goal-pay-title-<?= $id ?>">
  <form method="post" action="/minha-conta/financeiro/meta-pagar" data-finance-form>
    <header><h2 id="goal-pay-title-<?= $id ?>">Pagar usando <?= $e($goal['name']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?= $hidden() ?>
    <?php if ($reservedAccounts === []): ?><?= $empty ?><footer><button type="button" data-finance-close>Fechar</button></footer>
    <?php else: ?>
    <p class="muted">A despesa entra uma vez nas movimentações. O saldo da conta e a reserva caem juntos, e o disponível continua igual.</p>
    <label>Descrição<input name="description" maxlength="160" required placeholder="Ex.: Hospedagem"></label>
    <?= $reservedSelect() ?>
    <div class="finance-dialog-row"><?= $amountField ?><?= $dateField ?></div>
    <label>Categoria (opcional)<select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= $e($category['name']) ?></option><?php endforeach; ?></select></label>
    <footer><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Pagar com a meta</button></footer>
    <?php endif; ?>
  </form>
</dialog>
<dialog class="finance-dialog" id="goal-schedule-<?= $id ?>" aria-labelledby="goal-schedule-title-<?= $id ?>">
  <form method="post" action="/minha-conta/financeiro/meta-aporte-programado" data-finance-form>
    <header><h2 id="goal-schedule-title-<?= $id ?>">Aporte programado</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?= $hidden() ?>
    <?php if ($cash === []): ?><p class="muted">Cadastre uma conta para programar aportes.</p><footer><button type="button" data-finance-close>Fechar</button></footer>
    <?php else: ?>
    <?php if ($schedule !== null): ?><p class="muted"><?= $schedule['status'] === 'paused' ? 'Pausado.' : 'Próximo aporte em ' . $e((new DateTimeImmutable($schedule['next_run_on']))->format('d/m/Y')) . '.' ?><?= $schedule['last_result'] === 'skipped' ? ' O último não aconteceu por falta de saldo disponível.' : '' ?></p><?php endif; ?>
    <label>Conta de origem<select name="account_id" required>
      <?php foreach ($cash as $account): ?><option value="<?= (int) $account['id'] ?>"<?= (int) ($schedule['account_id'] ?? $goal['account_id']) === (int) $account['id'] ? ' selected' : '' ?>><?= $e($account['name']) ?></option><?php endforeach; ?>
    </select></label>
    <div class="finance-dialog-row">
      <label>Valor por mês (R$)<input name="amount" inputmode="decimal" placeholder="0,00" pattern="[0-9.]+(,[0-9]{1,2})?" required value="<?= $schedule !== null ? $e(number_format((int) $schedule['amount_cents'] / 100, 2, ',', '')) : '' ?>"></label>
      <label>Todo dia<select name="day" required><?php foreach ($days as $day): ?><option value="<?= $day ?>"<?= (int) ($schedule['day_of_month'] ?? 10) === $day ? ' selected' : '' ?>><?= $day ?></option><?php endforeach; ?></select></label>
    </div>
    <footer>
      <?php if ($schedule !== null): ?>
      <button type="submit" form="goal-schedule-toggle-<?= $id ?>"><?= $schedule['status'] === 'paused' ? 'Retomar' : 'Pausar' ?></button>
      <button type="submit" form="goal-schedule-cancel-<?= $id ?>" class="finance-link-danger">Cancelar programação</button>
      <?php endif; ?>
      <button type="button" data-finance-close>Fechar</button><button type="submit" class="primary">Salvar</button>
    </footer>
    <?php endif; ?>
  </form>
  <?php if ($schedule !== null): ?>
  <form id="goal-schedule-toggle-<?= $id ?>" method="post" action="/minha-conta/financeiro/meta-aporte-programado-situacao" hidden><?= $hidden() ?><input type="hidden" name="status" value="<?= $schedule['status'] === 'paused' ? 'active' : 'paused' ?>"></form>
  <form id="goal-schedule-cancel-<?= $id ?>" method="post" action="/minha-conta/financeiro/meta-aporte-programado-situacao" hidden><?= $hidden() ?><input type="hidden" name="status" value="canceled"></form>
  <?php endif; ?>
</dialog>
<dialog class="finance-dialog" id="goal-history-<?= $id ?>" aria-labelledby="goal-history-title-<?= $id ?>">
  <div class="finance-dialog-body goal-history">
    <header><h2 id="goal-history-title-<?= $id ?>">Histórico de <?= $e($goal['name']) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
    <?php if ($wallet === [] && $legacy === []): ?>
    <p class="muted">Nenhuma movimentação ainda.</p>
    <?php else: ?>
    <ul>
      <?php foreach ($wallet as $item): $positive = $item['delta_cents'] > 0; ?>
      <li>
        <span>
          <strong class="<?= $positive ? 'green' : '' ?>"><?= $positive ? '+ ' : '− ' ?><?= $e($money(abs($item['delta_cents']))) ?></strong>
          <small><?= $e(GoalWallets::TYPES[$item['type']] ?? '') ?> · <?= $e($item['account']) ?> · <?= $e((new DateTimeImmutable($item['occurred_on']))->format('d/m/Y')) ?><?= $item['actor'] !== '' ? ' · ' . $e($item['actor']) : '' ?></small>
          <?php if ($item['note']): ?><small><?= $e($item['note']) ?></small><?php endif; ?>
          <small class="goal-operation-id">Operação <?= $e(substr((string) $item['operation_key'], 0, 8)) ?></small>
        </span>
        <?php if (!$item['reversed'] && $item['type'] !== 'reversal' && $item['type'] !== 'transfer_in'): ?>
        <form method="post" action="/minha-conta/financeiro/meta-estornar" data-finance-confirm="Estornar esta operação?"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit" class="finance-link-danger">Estornar</button></form>
        <?php elseif ($item['reversed']): ?><small>Estornada</small><?php endif; ?>
      </li>
      <?php endforeach; ?>
      <?php foreach ($legacy as $item): ?>
      <li>
        <span><strong><?= $e($money($item['amount_cents'])) ?></strong><small>Registrado fora das contas · <?= $e((new DateTimeImmutable($item['contributed_at']))->format('d/m/Y')) ?></small></span>
        <form method="post" action="/minha-conta/financeiro/meta-aporte-excluir" data-finance-confirm="Remover este registro? O valor guardado da meta será reduzido."><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="metas"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit" class="finance-link-danger">Remover</button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</dialog>
