<?php
declare(strict_types=1);

/** Regressão completa do Módulo 01 no MySQL local, com limpeza ao final. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use MovesCode\Model\Connection;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$invalid = static function (callable $operation, string $message) use ($check): void {
    try {
        $operation();
        $check(false, $message);
    } catch (InvalidArgumentException) {
        echo "OK - {$message}\n";
    }
};
$key = static fn (): string => bin2hex(random_bytes(16));
$pdo = Connection::get();
$users = [];
$organizations = [];

try {
    foreach (['a', 'b'] as $suffix) {
        $email = 'receivables-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid';
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('Teste', 'Financeiro', ?, 'x', 'active')")
            ->execute([$email]);
        $users[$suffix] = (int) $pdo->lastInsertId();
    }

    $contextA = FinanceContext::forUser($users['a']);
    $contextB = FinanceContext::forUser($users['b']);
    $financeA = new Finance($contextA);
    $financeB = new Finance($contextB);
    $accountA = $financeA->createAccount(['name' => 'Conta A', 'type' => 'checking']);
    $accountB = $financeB->createAccount(['name' => 'Conta B', 'type' => 'checking']);
    $pdo->prepare("INSERT INTO finance_categories (financial_context_id, name, kind) VALUES (?, 'Serviços A', 'income')")->execute([$contextA]);
    $categoryA = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO finance_categories (financial_context_id, name, kind) VALUES (?, 'Serviços B', 'income')")->execute([$contextB]);
    $categoryB = (int) $pdo->lastInsertId();
    $serviceA = new Receivables($contextA);
    $serviceB = new Receivables($contextB);

    $base = [
        'description' => 'Projeto mensal', 'amount' => '1.000,00', 'due_date' => date('Y-m-15'),
        'competence' => date('Y-m'), 'counterparty_name' => 'Cliente A', 'notes' => 'Contrato mensal',
        'document_number' => 'NF-001', 'payment_method' => 'pix', 'account_id' => $accountA,
        'category_id' => $categoryA,
    ];
    $createKey = $key();
    $created = $serviceA->create(array_merge($base, ['idempotency_key' => $createKey]), $users['a']);
    $check($created['id'] > 0 && !$created['duplicate'], 'Cadastro válido persiste o recebimento');
    $duplicateCreate = $serviceA->create(array_merge($base, ['idempotency_key' => $createKey]), $users['a']);
    $check($duplicateCreate['id'] === $created['id'] && $duplicateCreate['duplicate'], 'Cadastro repetido é idempotente');

    $invalid(fn () => $serviceA->create(array_merge($base, ['description' => '', 'idempotency_key' => $key()]), $users['a']), 'Descrição obrigatória');
    $invalid(fn () => $serviceA->create(array_merge($base, ['amount' => '0', 'idempotency_key' => $key()]), $users['a']), 'Valor deve ser maior que zero');
    $invalid(fn () => $serviceA->create(array_merge($base, ['account_id' => $accountB, 'idempotency_key' => $key()]), $users['a']), 'Conta de outro usuário é recusada');
    $invalid(fn () => $serviceA->create(array_merge($base, ['category_id' => $categoryB, 'idempotency_key' => $key()]), $users['a']), 'Categoria de outro usuário é recusada');

    $listed = $serviceA->listing(['start' => date('Y-m-01'), 'end' => date('Y-m-t'), 'search' => 'Projeto']);
    $check($listed['total'] === 1 && $listed['rows'][0]['balance_cents'] === 100000, 'Listagem, pesquisa e saldo inicial');
    $check($serviceB->listing(['start' => date('Y-m-01'), 'end' => date('Y-m-t')])['total'] === 0, 'Isolamento entre usuários');

    $serviceA->update($created['id'], array_merge($base, ['description' => 'Projeto mensal atualizado', 'amount' => '1.200,00']), $users['a']);
    $updated = $serviceA->listing(['start' => date('Y-m-01'), 'end' => date('Y-m-t'), 'search' => 'atualizado']);
    $check($updated['total'] === 1 && $updated['rows'][0]['amount_cents'] === 120000, 'Edição autorizada');

    $settlementKey = $key();
    $partial = $serviceA->settle($created['id'], [
        'amount' => '400,00', 'received_at' => date('Y-m-d'), 'account_id' => $accountA,
        'payment_method' => 'pix', 'notes' => 'Primeira parcela', 'idempotency_key' => $settlementKey,
    ], $users['a']);
    $check($partial['status'] === 'partially_received' && $partial['balance_cents'] === 80000, 'Baixa parcial calcula saldo');
    $repeated = $serviceA->settle($created['id'], [
        'amount' => '400,00', 'received_at' => date('Y-m-d'), 'account_id' => $accountA,
        'payment_method' => 'pix', 'idempotency_key' => $settlementKey,
    ], $users['a']);
    $check($repeated['duplicate'] && $repeated['balance_cents'] === 80000, 'Baixa repetida não duplica valor');
    $invalid(fn () => $serviceA->settle($created['id'], [
        'amount' => '800,01', 'received_at' => date('Y-m-d'), 'account_id' => $accountA,
        'payment_method' => 'pix', 'idempotency_key' => $key(),
    ], $users['a']), 'Baixa superior ao saldo é impedida');
    $integral = $serviceA->settle($created['id'], [
        'amount' => '800,00', 'received_at' => date('Y-m-d'), 'account_id' => $accountA,
        'payment_method' => 'transfer', 'idempotency_key' => $key(),
    ], $users['a']);
    $check($integral['status'] === 'received' && $integral['balance_cents'] === 0, 'Baixa integral conclui o recebimento');
    $invalid(fn () => $serviceA->update($created['id'], array_merge($base, ['amount' => '999,99']), $users['a']), 'Edição não invalida baixas existentes');
    $invalid(fn () => $serviceA->cancel($created['id'], 'Cancelamento indevido', $users['a']), 'Lançamento com baixa não pode ser cancelado');

    $cancelled = $serviceA->create(array_merge($base, [
        'description' => 'Recebimento cancelável', 'amount' => '50,00', 'idempotency_key' => $key(),
    ]), $users['a']);
    $serviceA->cancel($cancelled['id'], 'Contrato encerrado pelo cliente', $users['a']);
    $cancelledList = $serviceA->listing(['start' => date('Y-m-01'), 'end' => date('Y-m-t'), 'status' => 'cancelled']);
    $check($cancelledList['total'] === 1 && $cancelledList['rows'][0]['cancellation_reason'] !== null, 'Cancelamento lógico preserva justificativa');
    $invalid(fn () => $serviceA->settle($cancelled['id'], [
        'amount' => '10,00', 'received_at' => date('Y-m-d'), 'account_id' => $accountA,
        'payment_method' => 'cash', 'idempotency_key' => $key(),
    ], $users['a']), 'Baixa em lançamento cancelado é bloqueada');

    $summary = $serviceA->summary(date('Y-m-01'), date('Y-m-t'));
    $check($summary['forecast'] === 120000 && $summary['received'] === 120000 && $summary['to_receive'] === 0, 'Indicadores excluem cancelados e não duplicam baixas');
    $history = $serviceA->history($created['id']);
    $check(count($history['settlements']) === 2 && count($history['audit']) >= 4, 'Histórico financeiro e auditoria persistidos');
    $invalid(fn () => $serviceB->history($created['id']), 'Outro contexto não acessa o histórico');

    foreach (['Empresa A', 'Empresa B'] as $name) {
        $pdo->prepare('INSERT INTO organizations (owner_user_id, name) VALUES (?, ?)')
            ->execute([$users['a'], $name . ' ' . bin2hex(random_bytes(3))]);
        $organizations[] = (int) $pdo->lastInsertId();
    }
    $organizationContextA = FinanceContext::forOrganization($organizations[0]);
    $organizationContextB = FinanceContext::forOrganization($organizations[1]);
    $organizationFinanceA = new Finance($organizationContextA);
    $organizationAccountA = $organizationFinanceA->createAccount(['name' => 'Conta PJ A', 'type' => 'checking']);
    $pdo->prepare("INSERT INTO finance_categories (financial_context_id, name, kind) VALUES (?, 'Serviços PJ', 'income')")
        ->execute([$organizationContextA]);
    $organizationCategoryA = (int) $pdo->lastInsertId();
    $organizationReceivablesA = new Receivables($organizationContextA);
    $organizationReceivablesB = new Receivables($organizationContextB);
    $organizationReceivablesA->create([
        'description' => 'Recebimento exclusivo da empresa A',
        'amount' => '350,00',
        'due_date' => date('Y-m-d'),
        'competence' => date('Y-m'),
        'account_id' => $organizationAccountA,
        'category_id' => $organizationCategoryA,
        'payment_method' => 'pix',
        'idempotency_key' => $key(),
    ], $users['a']);
    $check(
        $organizationReceivablesA->listing(['start' => date('Y-m-01'), 'end' => date('Y-m-t')])['total'] === 1
        && $organizationReceivablesB->listing(['start' => date('Y-m-01'), 'end' => date('Y-m-t')])['total'] === 0,
        'Isolamento entre empresas preserva lançamentos de cada contexto'
    );
    $invalid(fn () => new Receivables(0), 'Serviço recusa ausência de contexto autorizado');

    $routeSource = file_get_contents(dirname(__DIR__) . '/container/app/realizze/v1/routes.php');
    $check(is_string($routeSource) && str_contains($routeSource, "AccessPolicy::requireAccess('app')")
        && str_contains($routeSource, 'ReceivableActions'), 'Rotas exigem autenticação antes das actions');
    echo "FINANCE-002: MÓDULO CONTAS A RECEBER APROVADO.\n";
} finally {
    foreach ($organizations as $id) {
        $pdo->prepare('DELETE FROM organizations WHERE id = ?')->execute([$id]);
    }
    foreach ($users as $id) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
