<?php
/** Conciliação: linhas do extrato importado, sugestões de lançamento e revisão item a item. Nada altera saldo. */
use Moves\Services\{AppUrl,BankReconciliation,Finance,FinanceContext};
$e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$csrf=(string)($csrf??'');
$statusLabels=['pending'=>'Pendente','matched'=>'Conciliada','ignored'=>'Ignorada','reversed'=>'Estornada no banco'];
$status=array_key_exists($_GET['situacao']??'',$statusLabels)?$_GET['situacao']:'';
$accountFilter=(int)filter_var($_GET['conta']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'default'=>0]]);
$lines=$accounts=$suggestions=[];$counts=array_fill_keys(array_keys($statusLabels),0);$error=null;
try{
    $context=(int)FinanceContext::currentId();$service=new BankReconciliation($context);
    $accounts=array_values(array_filter((new Finance($context))->accounts(),static fn($a)=>$a['type']!=='credit_card'));
    $lines=$service->lines(['status'=>$status,'account_id'=>$accountFilter]);
    $counts=$service->counts();
    $suggestions=$service->suggestions($lines);
}catch(Throwable $x){error_log('Realizze reconciliation: '.$x->getMessage());$error='A estrutura de conciliação ainda precisa da migration 014.';}
$flash=$_SESSION['finance_flash']??null;unset($_SESSION['finance_flash']);
$filters=array_filter(['situacao'=>$status,'conta'=>$accountFilter]);
$link=static fn(array $q):string=>AppUrl::section('conciliacao',null,array_filter($q));
$date=static fn(?string $d):string=>$d?(new DateTimeImmutable($d))->format('d/m/Y'):'—';
$review=static fn(int $line,string $to,string $label,string $extra=''):string=>'<form method="post" action="/minha-conta/financeiro/conciliacao-revisar"><input type="hidden" name="csrf_token" value="'.$e($csrf).'"><input type="hidden" name="back" value="conciliacao"><input type="hidden" name="line_id" value="'.$line.'"><input type="hidden" name="status" value="'.$e($to).'">'.$extra.'<button type="submit">'.$e($label).'</button></form>';
$kinds=['income'=>'Receita','expense'=>'Despesa','transfer'=>'Transferência'];
?>
<?php if(is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="reconciliation-title">
  <div class="finance-toolbar"><div><h2 id="reconciliation-title">Conciliação bancária</h2><p class="muted">Importe o extrato e confira linha a linha. Conciliar só liga a linha a um lançamento que já existe: nenhum saldo muda e nada é criado sozinho.</p></div><button type="button" class="primary" data-finance-open="statement-import"<?= $accounts===[]?' disabled':'' ?>>Importar extrato</button></div>
  <?php if($error): ?>
  <div class="finance-empty"><h3>Módulo aguardando atualização</h3><p><?= $e($error) ?></p></div>
  <?php else: ?>
  <div class="finance-goal-summary">
    <a class="finance-goal-stat finance-indicator <?= $counts['pending']>0?'is-pending':'is-neutral' ?>" href="<?= $e($link(['situacao'=>'pending','conta'=>$accountFilter])) ?>"><span>Pendentes</span><strong><?= (int)$counts['pending'] ?></strong><small>Aguardando revisão</small></a>
    <a class="finance-goal-stat finance-indicator <?= $counts['matched']>0?'is-realized':'is-neutral' ?>" href="<?= $e($link(['situacao'=>'matched','conta'=>$accountFilter])) ?>"><span>Conciliadas</span><strong><?= (int)$counts['matched'] ?></strong><small>Ligadas a um lançamento</small></a>
    <a class="finance-goal-stat finance-indicator is-neutral" href="<?= $e($link(['situacao'=>'ignored','conta'=>$accountFilter])) ?>"><span>Ignoradas</span><strong><?= (int)$counts['ignored']+(int)$counts['reversed'] ?></strong><small>Sem lançamento correspondente</small></a>
  </div>
  <form method="get" class="finance-filter-bar invoice-card-switch" action="<?= $e(AppUrl::section('conciliacao')) ?>">
    <label><span class="sr-only">Situação</span><select name="situacao"><option value="">Todas as situações</option><?php foreach($statusLabels as $key=>$label): ?><option value="<?= $e($key) ?>"<?= $status===$key?' selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
    <label><span class="sr-only">Conta</span><select name="conta"><option value="">Todas as contas</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $accountFilter===(int)$a['id']?' selected':'' ?>><?= $e($a['name']) ?></option><?php endforeach; ?></select></label>
    <button type="submit" class="finance-button">Filtrar</button><?php if($filters!==[]): ?><a class="filter-clear" href="<?= $e(AppUrl::section('conciliacao')) ?>">Limpar</a><?php endif; ?>
  </form>
  <?php if($counts['pending']>0): ?><p class="muted reconcile-hint">Sugerimos lançamentos da mesma conta, com o mesmo valor e até 7 dias de diferença. Sem sugestão, lance a receita ou despesa em Receber ou Pagar e volte, ou ignore a linha.</p><?php endif; ?>
  <?php if($lines===[]): ?>
  <div class="finance-empty"><h3><?= $filters===[]?'Nenhuma linha para revisar':'Nenhuma linha com estes filtros' ?></h3><p>Importe um CSV com as colunas Data, Descrição e Valor, ou o arquivo OFX do banco.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Linhas do extrato"><table class="finance-table finance-entries"><thead><tr><th scope="col">Data</th><th scope="col">Descrição no extrato</th><th scope="col">Conta</th><th scope="col" class="num">Valor</th><th scope="col">Situação e lançamento</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead><tbody>
  <?php foreach($lines as $line): $id=(int)$line['id'];$options=$suggestions[$id]??[]; ?>
  <tr>
    <td class="nowrap"><?= $e($date($line['occurred_on'])) ?></td>
    <td><strong><?= $e($line['description']) ?></strong></td>
    <td><?= $e($line['account']) ?></td>
    <td class="num <?= (int)$line['amount_cents']<0?'red':'green' ?>"><?= $e(Finance::money((int)$line['amount_cents'])) ?></td>
    <td><span class="finance-badge <?= ['pending'=>'is-open','matched'=>'is-paid','ignored'=>'is-cancelled','reversed'=>'is-overdue'][$line['status']]??'is-open' ?>"><?= $e($statusLabels[$line['status']]??$line['status']) ?></span>
      <?php if($line['status']==='matched'&&$line['entry_description']): ?><small class="contact-legal"><?= $e($line['entry_description']) ?> · <?= $e($date($line['entry_date'])) ?></small><?php elseif($line['status']==='pending'): ?><small class="contact-legal"><?= $options===[]?'Sem lançamento igual':count($options).' sugest'.(count($options)===1?'ão':'ões') ?></small><?php endif; ?>
    </td>
    <td class="finance-actions"><div class="reconcile-actions">
      <?php if($line['status']==='pending'): ?>
        <?php if($options!==[]): ?><form method="post" action="/minha-conta/financeiro/conciliacao-revisar" class="reconcile-match"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="conciliacao"><input type="hidden" name="line_id" value="<?= $id ?>"><input type="hidden" name="status" value="matched"><label><span class="sr-only">Lançamento</span><select name="entry_id"><?php foreach($options as $o): ?><option value="<?= (int)$o['id'] ?>"><?= $e($kinds[$o['kind']]??'').': '.$e($o['description']).' · '.$e($date($o['paid_at']?:$o['due_date'])) ?></option><?php endforeach; ?></select></label><button type="submit">Conciliar</button></form><?php endif; ?>
        <?= $review($id,'ignored','Ignorar') ?>
      <?php else: ?>
        <?= $review($id,'pending','Desfazer') ?>
      <?php endif; ?>
    </div></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="statement-import" aria-labelledby="statement-import-title"><form method="post" enctype="multipart/form-data" action="/minha-conta/financeiro/conciliacao-importar" data-finance-form><header><h2 id="statement-import-title">Importar extrato</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
  <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="conciliacao">
  <label>Conta<select name="account_id" required><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $accountFilter===(int)$a['id']?' selected':'' ?>><?= $e($a['name']) ?></option><?php endforeach; ?></select></label>
  <label>Arquivo CSV ou OFX (até 5 MB)<input type="file" name="statement" accept=".csv,.ofx,text/csv" required></label>
  <p class="muted">No CSV, use o cabeçalho Data; Descrição; Valor (ponto e vírgula ou vírgula), com saídas negativas. O OFX é o arquivo que o banco exporta. O mesmo arquivo não entra duas vezes.</p>
  <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Enviar para revisão</button></footer></form></dialog>
