<?php
/** Centros de custo da empresa: cadastro, edição, inativação e resultado do mês por centro. */
use Moves\Services\{AppUrl,CostCenters,Finance,FinanceContext,OrganizationContext};
$e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$csrf=(string)($csrf??'');
$month=is_string($_GET['mes']??null)&&preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$_GET['mes'])?$_GET['mes']:date('Y-m');
$start=new DateTimeImmutable($month.'-01');$end=$start->modify('last day of this month');
$monthNames=[1=>'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$rows=[];$error=null;
try{
    if(OrganizationContext::current()===null)throw new InvalidArgumentException('Centros de custo estão disponíveis apenas para empresas.');
    $rows=(new CostCenters((int)FinanceContext::currentId()))->totals($start->format('Y-m-d'),$end->format('Y-m-d'));
}catch(InvalidArgumentException $x){$error=$x->getMessage();}catch(Throwable $x){error_log('Realizze cost centers: '.$x->getMessage());$error='A estrutura de centros de custo ainda precisa da migration 014.';}
$centers=array_values(array_filter($rows,static fn($r)=>$r['id']!==null));
$unassigned=array_values(array_filter($rows,static fn($r)=>$r['id']===null))[0]??null;
$sum=static fn(string $field):int=>array_sum(array_column($rows,$field));
$link=static fn(string $target):string=>AppUrl::section('centros-de-custo',null,['mes'=>$target]);
$flash=$_SESSION['finance_flash']??null;unset($_SESSION['finance_flash']);
$fields=static fn(array $c=[]):string=>'<label>Nome<input name="name" maxlength="100" required value="'.$e($c['name']??'').'"></label><label>Código (opcional)<input name="code" maxlength="40" value="'.$e($c['code']??'').'"></label>';
?>
<?php if(is_array($flash)): ?><p class="finance-flash finance-flash-<?= $e($flash[0]) ?>" role="status"><?= $e($flash[1]) ?></p><?php endif; ?>
<section class="card finance-workspace" aria-labelledby="cost-centers-title">
  <div class="finance-toolbar"><div><h2 id="cost-centers-title">Centros de custo</h2><p class="muted">Separe receitas e despesas por área, projeto ou unidade. Escolha o centro ao lançar em Pagar, Receber ou numa compra no cartão.</p></div><button type="button" class="primary" data-finance-open="cost-center"<?= $error?' disabled':'' ?>>+ Novo centro</button></div>
  <?php if($error): ?>
  <div class="finance-empty"><h3>Indisponível</h3><p><?= $e($error) ?></p></div>
  <?php else: ?>
  <div class="finance-filter-bar"><nav class="finance-month" aria-label="Mês"><a href="<?= $e($link($start->modify('-1 month')->format('Y-m'))) ?>" aria-label="Mês anterior">‹</a><strong><?= $e($monthNames[(int)$start->format('n')].' de '.$start->format('Y')) ?></strong><a href="<?= $e($link($start->modify('+1 month')->format('Y-m'))) ?>" aria-label="Próximo mês">›</a></nav></div>
  <div class="finance-goal-summary">
    <div class="finance-goal-stat finance-indicator is-neutral"><span>Receitas do mês</span><strong><?= $e(Finance::money($sum('income_done'))) ?></strong><small>Recebidas · <?= $e(Finance::money($sum('income_open'))) ?> a receber</small></div>
    <div class="finance-goal-stat finance-indicator is-neutral"><span>Despesas do mês</span><strong><?= $e(Finance::money($sum('expense_done'))) ?></strong><small>Pagas · <?= $e(Finance::money($sum('expense_open'))) ?> a pagar</small></div>
    <div class="finance-goal-stat finance-indicator <?= $unassigned!==null&&($unassigned['expense_done']+$unassigned['expense_open'])>0?'is-pending':'is-neutral' ?>"><span>Sem centro de custo</span><strong><?= $e(Finance::money((int)($unassigned['expense_done']??0)+(int)($unassigned['expense_open']??0))) ?></strong><small>Despesas do mês sem classificação</small></div>
  </div>
  <?php if($centers===[]): ?>
  <div class="finance-empty"><h3>Nenhum centro cadastrado</h3><p>Crie o primeiro centro de custo para a empresa.</p></div>
  <?php else: ?>
  <div class="finance-table-wrap" role="region" tabindex="0" aria-label="Resultado por centro de custo"><table class="finance-table finance-entries"><thead><tr><th scope="col">Centro</th><th scope="col">Situação</th><th scope="col" class="num">Receitas</th><th scope="col" class="num">Despesas</th><th scope="col" class="num">Resultado realizado</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead><tbody>
  <?php foreach($rows as $r): $result=$r['income_done']-$r['expense_done']; ?>
  <tr>
    <td><strong><?php if($r['id']!==null): ?><a href="<?= $e(AppUrl::section('pagar',null,['mes'=>$month,'centro'=>$r['id']])) ?>" title="Ver as despesas deste centro em Pagar"><?= $e($r['name']) ?></a><?php else: ?><?= $e($r['name']) ?><?php endif; ?></strong><?php if($r['code']): ?><small class="contact-legal"><?= $e($r['code']) ?></small><?php endif; ?></td>
    <td><?php if($r['id']!==null): ?><span class="finance-badge <?= $r['status']==='active'?'is-paid':'is-cancelled' ?>"><?= $r['status']==='active'?'Ativo':'Inativo' ?></span><?php else: ?>—<?php endif; ?></td>
    <td class="num green"><?= $e(Finance::money($r['income_done'])) ?><?php if($r['income_open']>0): ?><small class="contact-legal">+ <?= $e(Finance::money($r['income_open'])) ?> previsto</small><?php endif; ?></td>
    <td class="num red"><?= $e(Finance::money($r['expense_done'])) ?><?php if($r['expense_open']>0): ?><small class="contact-legal">+ <?= $e(Finance::money($r['expense_open'])) ?> previsto</small><?php endif; ?></td>
    <td class="num <?= $result<0?'red':'' ?>"><strong><?= $e(Finance::money($result)) ?></strong></td>
    <td class="finance-actions"><?php if($r['id']!==null): ?><button type="button" data-finance-open="cost-center-<?= (int)$r['id'] ?>">Editar</button><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <?php endif; ?>
</section>
<dialog class="finance-dialog" id="cost-center" aria-labelledby="cost-center-title"><form method="post" action="/minha-conta/financeiro/centro-de-custo" data-finance-form><header><h2 id="cost-center-title">Novo centro de custo</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="centros-de-custo"><?= $fields() ?><footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Salvar</button></footer></form></dialog>
<?php foreach($centers as $c): ?>
<dialog class="finance-dialog" id="cost-center-<?= (int)$c['id'] ?>" aria-labelledby="cost-center-title-<?= (int)$c['id'] ?>"><form method="post" action="/minha-conta/financeiro/centro-de-custo" data-finance-form><header><h2 id="cost-center-title-<?= (int)$c['id'] ?>">Editar centro de custo</h2><button type="button" data-finance-close aria-label="Fechar">×</button></header><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="back" value="centros-de-custo"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><?= $fields($c) ?>
  <label>Situação<select name="status"><option value="active"<?= $c['status']==='active'?' selected':'' ?>>Ativo: aparece ao lançar</option><option value="inactive"<?= $c['status']==='inactive'?' selected':'' ?>>Inativo: some dos formulários, o histórico fica</option></select></label>
  <footer><button type="button" data-finance-close>Cancelar</button><button class="primary">Salvar</button></footer></form></dialog>
<?php endforeach; ?>
