<?php
/** Faturas de cartão do contexto atual: compras do ciclo, pagamento total ou parcial e histórico com estorno. */
use Moves\Services\{AppUrl,CardInvoices,Finance,FinanceContext};
$e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$csrf=(string)($csrf??'');
$month=is_string($_GET['mes']??null)&&preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$_GET['mes'])?$_GET['mes']:date('Y-m');
$cards=$cash=$payments=$categories=$centers=[];$invoice=null;$error=null;$selected=0;
try{
    $context=(int)FinanceContext::currentId();$finance=new Finance($context);$service=new CardInvoices($context);
    $cards=$service->cards();
    $cash=array_values(array_filter($finance->accounts(),static fn($a)=>$a['type']!=='credit_card'));
    $categories=$finance->categories('expense');$centers=$finance->costCenters();
    if($cards!==[]){
        $ids=array_map('intval',array_column($cards,'id'));
        $selected=in_array((int)($_GET['cartao']??0),$ids,true)?(int)$_GET['cartao']:$ids[0];
        $invoice=$service->invoice($selected,$month);
        $payments=$service->payments($selected,10);
    }
}catch(Throwable $x){error_log('Realizze cards: '.$x->getMessage());$error='A estrutura de cartões ainda precisa das migrations 014 e 015.';}
$card=null;foreach($cards as $c){if((int)$c['id']===$selected)$card=$c;}
$date=static fn(string $d):string=>(new DateTimeImmutable($d))->format('d/m/Y');
$monthNames=[1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$monthStart=new DateTimeImmutable($month.'-01');
$monthLabel=$monthNames[(int)$monthStart->format('n')].' de '.$monthStart->format('Y');
$link=static fn(string $target):string=>AppUrl::section('faturas',null,['cartao'=>$selected,'mes'=>$target]);
$flash=$_SESSION['finance_flash']??null;unset($_SESSION['finance_flash']);
$hidden=static fn():string=>'<input type="hidden" name="csrf_token" value="'.$e($csrf).'"><input type="hidden" name="back" value="faturas"><input type="hidden" name="card_account_id" value="'.$selected.'"><input type="hidden" name="month" value="'.$e($month).'">';
?>
<?php if(is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="invoice-title">
  <div class="finance-toolbar"><div><h2 id="invoice-title">Cartões e faturas</h2><p class="muted">A compra entra na fatura do ciclo. Pagar a fatura transfere o valor da conta para o cartão: a despesa conta uma vez só.</p></div><button class="primary" type="button" data-finance-open="card-purchase"<?= $cards===[]?' disabled':'' ?>>+ Nova compra</button></div>
  <?php if($error!==null): ?>
  <div class="finance-empty"><h3>Módulo aguardando atualização</h3><p><?= $e($error) ?></p></div>
  <?php elseif($cards===[]): ?>
  <div class="finance-empty"><h3>Nenhum cartão cadastrado</h3><p>Cadastre um cartão de crédito em <a href="<?= $e(AppUrl::section('contas')) ?>">Bancos e contas</a>, com dia de fechamento e vencimento.</p></div>
  <?php else: ?>
  <div class="finance-filter-bar">
    <nav class="finance-month" aria-label="Fatura"><a href="<?= $e($link($monthStart->modify('-1 month')->format('Y-m'))) ?>" aria-label="Fatura anterior">‹</a><strong><?= $e(ucfirst($monthLabel)) ?></strong><a href="<?= $e($link($monthStart->modify('+1 month')->format('Y-m'))) ?>" aria-label="Próxima fatura">›</a></nav>
    <?php if(count($cards)>1): ?><form method="get" class="invoice-card-switch" action="<?= $e(AppUrl::section('faturas')) ?>"><input type="hidden" name="mes" value="<?= $e($month) ?>"><label><span class="sr-only">Cartão</span><select name="cartao"><?php foreach($cards as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id']===$selected?' selected':'' ?>><?= $e($c['name']) ?></option><?php endforeach; ?></select></label><button type="submit" class="finance-button">Ver cartão</button></form><?php else: ?><strong><?= $e($card['name']) ?></strong><?php endif; ?>
  </div>
  <?php if(!$card['closing_day']||!$card['due_day']): ?><p class="finance-flash finance-flash-error" role="status">Este cartão não tem dia de fechamento ou vencimento. Edite o cartão em Bancos e contas para as faturas saírem certas.</p><?php endif; ?>
  <div class="finance-goal-summary">
    <div class="finance-goal-stat finance-indicator <?= $invoice['total_cents']>0?'is-pending':'is-neutral' ?>"><span>Em aberto nesta fatura</span><strong><?= $e(Finance::money((int)$invoice['total_cents'])) ?></strong><small>Vence em <?= $e($date($invoice['due_on'])) ?></small></div>
    <div class="finance-goal-stat finance-indicator is-neutral"><span>Ciclo</span><strong><?= $e((new DateTimeImmutable($invoice['start']))->format('d/m')) ?> a <?= $e((new DateTimeImmutable($invoice['end']))->format('d/m')) ?></strong><small><?= count($invoice['rows']) ?> compra<?= count($invoice['rows'])===1?'':'s' ?> em aberto</small></div>
    <?php if($card['credit_limit_cents']!==null): ?><div class="finance-goal-stat finance-indicator is-neutral"><span>Limite do cartão</span><strong><?= $e(Finance::money((int)$card['credit_limit_cents'])) ?></strong><small>Cadastrado em Bancos e contas</small></div><?php endif; ?>
  </div>
  <?php if($invoice['rows']===[]): ?>
  <div class="finance-empty"><h3>Nada em aberto nesta fatura</h3><p>Compras e parcelas aparecem na fatura do ciclo em que caem.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Compras da fatura"><table class="finance-table finance-entries"><thead><tr><th scope="col">Descrição</th><th scope="col">Categoria</th><th scope="col" class="num">Valor</th><th scope="col" class="num">Em aberto</th></tr></thead><tbody>
  <?php foreach($invoice['rows'] as $row): ?><tr><td><strong><?= $e($row['description']) ?></strong></td><td><?= $e($row['category']??'—') ?></td><td class="num"><?= $e(Finance::money((int)$row['amount_cents'])) ?></td><td class="num red"><?= $e(Finance::money((int)$row['open_cents'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="profile-actions invoice-pay"><button type="button" class="primary" data-finance-open="invoice-pay"<?= $cash===[]?' disabled title="Cadastre uma conta para pagar a fatura"':'' ?>>Pagar fatura</button></div>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php if($cards!==[]&&$error===null): ?>
<section class="card finance-workspace" aria-labelledby="invoice-payments">
  <div class="finance-toolbar"><div><h2 id="invoice-payments">Pagamentos do cartão</h2><p class="muted">Estornar devolve o valor à conta de origem e reabre as compras pagas. O registro continua aqui.</p></div></div>
  <?php if($payments===[]): ?>
  <div class="finance-empty"><h3>Nenhum pagamento registrado</h3><p>Os pagamentos de fatura deste cartão aparecem aqui.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Pagamentos do cartão"><table class="finance-table finance-entries"><thead><tr><th scope="col">Pago em</th><th scope="col">Ciclo</th><th scope="col">Conta</th><th scope="col">Situação</th><th scope="col" class="num">Valor</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead><tbody>
  <?php foreach($payments as $payment): ?><tr><td><?= $e($date($payment['paid_on'])) ?></td><td><?= $e((new DateTimeImmutable($payment['period_start']))->format('d/m')) ?> a <?= $e((new DateTimeImmutable($payment['period_end']))->format('d/m/Y')) ?></td><td><?= $e($payment['source_account']) ?></td><td><span class="finance-badge <?= $payment['reversed_at']?'is-overdue':'is-paid' ?>"><?= $payment['reversed_at']?'Estornado':'Pago' ?></span></td><td class="num"><?= $e(Finance::money((int)$payment['amount_cents'])) ?></td><td class="finance-actions"><?php if(!$payment['reversed_at']): ?><form method="post" action="/minha-conta/financeiro/cartao-estornar-pagamento" data-finance-confirm="Estornar este pagamento? O valor volta para <?= $e($payment['source_account']) ?>."><?= $hidden() ?><input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>"><button type="submit">Estornar</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php if($invoice!==null&&$invoice['total_cents']>0): ?>
<dialog class="finance-dialog" id="invoice-pay" aria-labelledby="invoice-pay-title"><form method="post" action="/minha-conta/financeiro/cartao-pagar-fatura" data-finance-form data-finance-confirm="Confirmar o pagamento desta fatura?"><header><h2 id="invoice-pay-title">Pagar fatura de <?= $e($monthLabel) ?></h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
  <?= $hidden() ?><input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(32)) ?>">
  <p class="muted">Em aberto: <strong><?= $e(Finance::money((int)$invoice['total_cents'])) ?></strong>, vence em <?= $e($date($invoice['due_on'])) ?>.</p>
  <label>Conta de pagamento<select name="source_account_id" required><?php foreach($cash as $a): ?><option value="<?= (int)$a['id'] ?>"><?= $e($a['name']) ?></option><?php endforeach; ?></select></label>
  <div class="finance-dialog-row"><label>Valor (R$)<input name="amount" inputmode="decimal" pattern="[0-9.]*(,[0-9]{1,2})?" placeholder="<?= $e(number_format((int)$invoice['total_cents']/100,2,',','.')) ?>"></label><label>Data do pagamento<input type="date" name="paid_on" value="<?= date('Y-m-d') ?>" required></label></div>
  <p class="muted">Deixe o valor vazio para pagar o total. Um valor menor é pagamento parcial e abate das compras mais antigas.</p>
  <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Pagar</button></footer></form></dialog>
<?php endif; ?>
<dialog class="finance-dialog" id="card-purchase" aria-labelledby="card-purchase-title"><form method="post" action="/minha-conta/financeiro/cartao-compra" data-finance-form><header><h2 id="card-purchase-title">Nova compra no cartão</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
  <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="faturas"><input type="hidden" name="month" value="<?= $e($month) ?>"><input type="hidden" name="idempotency_key" value="<?= bin2hex(random_bytes(32)) ?>">
  <label>Cartão<select name="card_account_id" required><?php foreach($cards as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id']===$selected?' selected':'' ?>><?= $e($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>Descrição<input name="description" maxlength="160" required></label>
  <div class="finance-dialog-row"><label>Valor total (R$)<input name="amount" inputmode="decimal" pattern="[0-9.]+(,[0-9]{1,2})?" required></label><label>Data da compra<input type="date" name="purchased_on" value="<?= date('Y-m-d') ?>" required></label></div>
  <div class="finance-dialog-row"><label>Parcelas<input name="installments" type="number" min="1" max="120" value="1" required></label><label>Categoria<select name="category_id"><option value="">Sem categoria</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= $e($category['name']) ?></option><?php endforeach; ?></select></label></div>
  <?php if($centers!==[]): ?><label>Centro de custo<select name="cost_center_id"><option value="">Sem centro de custo</option><?php foreach($centers as $center): ?><option value="<?= (int)$center['id'] ?>"><?= $e($center['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
  <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Registrar compra</button></footer></form></dialog>
