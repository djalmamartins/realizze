<?php
/** Conciliação: linhas do extrato importado, sugestões de lançamento e revisão item a item. Nada altera saldo. */
use Moves\Services\{AppUrl,BankReconciliation,Finance,FinanceContext};
$e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$csrf=(string)($csrf??'');
$statusLabels=['pending'=>'Pendente','matched'=>'Conciliada','ignored'=>'Ignorada','reversed'=>'Estornada no banco'];
$status=array_key_exists($_GET['situacao']??'',$statusLabels)?$_GET['situacao']:'';
$accountFilter=(int)filter_var($_GET['conta']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'default'=>0]]);
$validDate=static fn(mixed $v):bool=>is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)===1;
$start=$validDate($_GET['inicio']??null)?$_GET['inicio']:'';
$end=$validDate($_GET['fim']??null)?$_GET['fim']:'';
$search=is_string($_GET['busca']??null)?trim(mb_substr($_GET['busca'],0,120)):'';
$scope=['account_id'=>$accountFilter,'start'=>$start,'end'=>$end,'search'=>$search];
$lines=$accounts=$suggestions=[];$page=$pages=1;$counts=array_fill_keys(array_keys($statusLabels),0);$error=null;
try{
    $context=(int)FinanceContext::currentId();$service=new BankReconciliation($context);
    $accounts=array_values(array_filter((new Finance($context))->accounts(),static fn($a)=>$a['type']!=='credit_card'));
    $counts=$service->counts($scope);
    // 50 linhas por página; o total vem da contagem com os mesmos filtros.
    $perPage=50;$total=$status===''?array_sum($counts):(int)($counts[$status]??0);
    $pages=max(1,(int)ceil($total/$perPage));$page=min($pages,max(1,(int)($_GET['pagina_extrato']??1)));
    $lines=$service->lines(['status'=>$status,'limit'=>$perPage,'offset'=>($page-1)*$perPage]+$scope);
    $suggestions=$service->suggestions($lines);
}catch(Throwable $x){error_log('Realizze reconciliation: '.$x->getMessage());$error='A estrutura de conciliação ainda precisa da migration 014.';}
$flash=$_SESSION['finance_flash']??null;unset($_SESSION['finance_flash']);
$filters=array_filter(['situacao'=>$status,'conta'=>$accountFilter,'inicio'=>$start,'fim'=>$end,'busca'=>$search]);
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
    <a class="finance-goal-stat finance-indicator <?= $counts['pending']>0?'is-pending':'is-neutral' ?>" href="<?= $e($link(['situacao'=>'pending']+$filters)) ?>"><span>Pendentes</span><strong><?= (int)$counts['pending'] ?></strong><small>Aguardando revisão</small></a>
    <a class="finance-goal-stat finance-indicator <?= $counts['matched']>0?'is-realized':'is-neutral' ?>" href="<?= $e($link(['situacao'=>'matched']+$filters)) ?>"><span>Conciliadas</span><strong><?= (int)$counts['matched'] ?></strong><small>Ligadas a um lançamento</small></a>
    <a class="finance-goal-stat finance-indicator is-neutral" href="<?= $e($link(['situacao'=>'ignored']+$filters)) ?>"><span>Ignoradas</span><strong><?= (int)$counts['ignored']+(int)$counts['reversed'] ?></strong><small>Sem lançamento correspondente</small></a>
  </div>
  <?php
  // Mesmo padrão de Contas a Receber: abas de situação, modal de filtros, etiquetas e Limpar.
  $without=static fn(string ...$keys):string=>$link(array_diff_key($filters,array_flip($keys)));
  $accountName=static function(int $id)use($accounts):string{foreach($accounts as $a)if((int)$a['id']===$id)return (string)$a['name'];return 'Conta';};
  ?>
  <div class="finance-filter-bar">
    <nav class="finance-status receivables-tabs" aria-label="Situação">
      <a href="<?= $e($link(['situacao'=>'']+$filters)) ?>"<?= $status===''?' aria-current="true"':'' ?>>Todas (<?= array_sum($counts) ?>)</a>
      <?php foreach($statusLabels as $key=>$label): ?><a href="<?= $e($link(['situacao'=>$key]+$filters)) ?>"<?= $status===$key?' aria-current="true"':'' ?>><?= $e(['pending'=>'Pendentes','matched'=>'Conciliadas','ignored'=>'Ignoradas','reversed'=>'Estornadas'][$key]) ?> (<?= (int)($counts[$key]??0) ?>)</a><?php endforeach; ?>
    </nav>
    <?php $filterPanelId='reconciliation-filters'; require dirname(__DIR__).'/components/filter-button.php'; ?>
  </div>
  <?php
  $filterChips=[];
  if($start!==''||$end!=='') $filterChips['Data: '.($start!==''?$date($start):'…').' a '.($end!==''?$date($end):'…')]=$without('inicio','fim');
  if($search!=='') $filterChips['Busca: '.$search]=$without('busca');
  if($accountFilter) $filterChips['Conta: '.$accountName($accountFilter)]=$without('conta');
  $filterClearUrl=$link($status===''?[]:['situacao'=>$status]);
  require dirname(__DIR__).'/components/filter-bar.php';
  ?>
  <dialog class="finance-dialog filter-dialog" id="reconciliation-filters" aria-labelledby="reconciliation-filters-title">
    <form method="get" action="<?= $e(AppUrl::section('conciliacao')) ?>" data-swap-get>
      <header><h2 id="reconciliation-filters-title">Filtros</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
      <?php $periodStart=$start;$periodEnd=$end;require dirname(__DIR__).'/components/filter-period.php'; ?>
      <p class="muted">O período considera a data da linha no extrato. Sem datas, todas aparecem.</p>
      <label>Pesquisar<input type="search" name="busca" value="<?= $e($search) ?>" placeholder="Descrição no extrato" maxlength="120"></label>
      <?php if($status!==''): ?><input type="hidden" name="situacao" value="<?= $e($status) ?>"><?php endif; ?>
      <label>Conta<select name="conta"><option value="">Todas as contas</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $accountFilter===(int)$a['id']?' selected':'' ?>><?= $e($a['name']) ?></option><?php endforeach; ?></select></label>
      <footer><a class="filter-clear" href="<?= $e($filterClearUrl) ?>">Limpar filtros</a><button type="button" data-finance-close>Cancelar</button><button type="submit" class="primary">Aplicar</button></footer>
    </form>
  </dialog>
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
  <?php if($pages>1): ?><nav class="receivables-pagination" aria-label="Páginas do extrato"><?php for($p=1;$p<=$pages;$p++): ?><a href="<?= $e($link(['pagina_extrato'=>$p>1?$p:'']+$filters)) ?>"<?= $p===$page?' aria-current="page"':'' ?>><?= $p ?></a><?php endfor; ?></nav><?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="statement-import" aria-labelledby="statement-import-title"><form method="post" enctype="multipart/form-data" action="/minha-conta/financeiro/conciliacao-importar" data-finance-form><header><h2 id="statement-import-title">Importar extrato</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header>
  <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="conciliacao">
  <label>Conta<select name="account_id" required><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $accountFilter===(int)$a['id']?' selected':'' ?>><?= $e($a['name']) ?></option><?php endforeach; ?></select></label>
  <label>Arquivo CSV ou OFX (até 5 MB)<input type="file" name="statement" accept=".csv,.ofx,text/csv" required></label>
  <p class="muted">No CSV, use o cabeçalho Data; Descrição; Valor (ponto e vírgula ou vírgula), com saídas negativas. O OFX é o arquivo que o banco exporta. O mesmo arquivo não entra duas vezes.</p>
  <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Enviar para revisão</button></footer></form></dialog>
