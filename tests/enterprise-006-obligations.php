<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';
use Moves\Services\{ObligationReport, OrganizationObligations, Organizations};
use MovesCode\Model\Connection;

$pdo = Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) throw new RuntimeException('Banco de teste obrigatório.');
$failed = 0;
$check = static function (bool $value, string $message) use (&$failed): void { echo ($value ? 'OK' : 'FALHOU') . " - {$message}\n"; if (!$value) $failed++; };
$user = static function (string $name) use ($pdo): int { $pdo->prepare("INSERT INTO users(first_name,last_name,email,password,status)VALUES(?,'Teste',?,'x','active')")->execute([$name, strtolower($name) . '-' . bin2hex(random_bytes(5)) . '@example.invalid']); return (int) $pdo->lastInsertId(); };
$owner = $user('ObrigDono'); $other = $user('ObrigOutro');
$org = Organizations::create($owner, 'Obrigações Alfa', ''); $alien = Organizations::create($other, 'Obrigações Beta', '');
$service = new OrganizationObligations($org); $otherService = new OrganizationObligations($alien); $key = bin2hex(random_bytes(16));
try {
    $base = ['title'=>'Alvará','type'=>'license','competence_date'=>'2032-01-31','due_date'=>'2032-01-31','frequency'=>'monthly','responsible_user_id'=>$owner,'amount'=>'100,00','notes'=>'Manual','idempotency_key'=>$key];
    $created = $service->create($base, $owner); $check($created['count'] === 12, 'Recorrência mensal cria 12 ocorrências');
    $duplicate = $service->create($base, $owner); $check($duplicate['duplicate'], 'Reenvio não duplica recorrência');
    $list = $service->listing(['start'=>'2032-01-01','end'=>'2032-12-31']);
    $check(count($list['rows']) === 12 && $list['rows'][1]['due_date'] === '2032-02-29', 'Recorrência preserva o dia e respeita ano bissexto');
    $id = (int) $list['rows'][0]['id'];
    $service->update($id, array_replace($base, ['title'=>'Alvará atualizado','frequency'=>'once']), $owner);
    $check($service->find($id)['title'] === 'Alvará atualizado', 'Obrigação individual pode ser editada');
    $service->setStatus($id, 'completed', $owner); $check($service->find($id)['status'] === 'completed', 'Conclusão fica persistida');
    $check(count($service->historyRows($id)) === 3, 'Histórico registra criação, edição e conclusão');
    try { $otherService->find($id); $isolated = false; } catch (InvalidArgumentException) { $isolated = true; }
    $check($isolated, 'Outra empresa não consulta a obrigação');
    $before = $pdo->query('SELECT COUNT(*) FROM finance_entries')->fetchColumn();
    $service->setStatus((int) $list['rows'][1]['id'], 'cancelled', $owner);
    $after = $pdo->query('SELECT COUNT(*) FROM finance_entries')->fetchColumn();
    $check($before === $after, 'Obrigação não gera despesa financeira');
    $pdo->prepare('UPDATE organization_obligations SET completed_at=? WHERE id=?')->execute(['2032-02-10 10:00:00',$id]);
    $pdo->prepare('UPDATE organization_obligations SET cancelled_at=? WHERE id=?')->execute(['2032-03-10 10:00:00',(int)$list['rows'][1]['id']]);
    $beforeCompletion=(new ObligationReport($org))->report('2032-01-01','2032-12-31','2032-02-05');
    $afterCompletion=(new ObligationReport($org))->report('2032-01-01','2032-12-31','2032-02-15');
    $year=(new ObligationReport($org))->report('2032-01-01','2032-12-31','2032-12-31');
    $check($beforeCompletion['status']['completed']===0&&$beforeCompletion['status']['overdue']===1,'Data-base não antecipa conclusão posterior');
    $check($afterCompletion['status']['completed']===1&&$afterCompletion['status']['cancelled']===0,'Conclusão entra na data efetiva e cancelamento futuro não é antecipado');
    $check($year['totals']['obligations']===12&&$year['status']['completed']===1&&$year['status']['cancelled']===1,'Relatório preserva recorrências e situações');
    $check($year['totals']['declared_cents']===110000&&$year['totals']['completed_cents']===10000&&$year['totals']['pending_cents']===100000,'Valores informados separam concluído, pendente e cancelado');
    $check($year['status']['overdue']===9&&$year['status']['upcoming']===1,'Vencidas e próximas usam a data-base do relatório');
    $check((new ObligationReport($alien))->report('2032-01-01','2032-12-31')['totals']['obligations']===0,'Relatório não mistura empresas');
} finally {
    foreach ([$org, $alien] as $id) {
        foreach (['organization_obligation_history','organization_obligation_attachments','organization_obligations'] as $table) $pdo->prepare("DELETE FROM {$table} WHERE organization_id=?")->execute([$id]);
        $pdo->prepare('DELETE FROM organization_members WHERE organization_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM financial_contexts WHERE organization_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$id]);
    }
    $pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$owner,$other]);
}
echo $failed === 0 ? "ENT-006: OBRIGAÇÕES APROVADAS.\n" : "ENT-006: {$failed} FALHA(S).\n";
exit($failed === 0 ? 0 : 1);
