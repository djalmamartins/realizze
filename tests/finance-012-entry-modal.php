<?php
declare(strict_types=1);

/** Serviços usados pela modal única: vencimentos, edição, assinatura, saldo e isolamento. Exige 007, 008 e 011; limpa o que cria. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
use Moves\Services\RecurringFinance;
use MovesCode\Model\Connection;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};
$pdo = Connection::get();
$users = [];
$upload = static function (string $name, string $content): array {
    $tmp = tempnam(sys_get_temp_dir(), 'att');
    file_put_contents($tmp, $content);
    return ['name' => $name, 'type' => '', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
};
$dues = static fn (string $due, int $count): array => array_column(Finance::occurrences('X', 10000, $due, ['repeat' => 'fixed', 'repeat_count' => (string) $count, 'repeat_every' => 'monthly']), 'due');
try {
    $check($dues('2026-11-10', 6) === ['2026-11-10', '2026-12-10', '2027-01-10', '2027-02-10', '2027-03-10', '2027-04-10'], 'Seis parcelas em 10/11/2026 geram exatamente as datas pedidas');
    $check($dues('2027-01-31', 4) === ['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'], 'Dia 31 ajusta nos meses curtos e volta depois');
    $check($dues('2027-12-30', 3) === ['2027-12-30', '2028-01-30', '2028-02-29'], 'Dia 30 cai em 29 de fevereiro no ano bissexto');
    $check($dues('2027-01-29', 2)[1] === '2027-02-28', 'Dia 29 cai em 28 de fevereiro no ano comum');

    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'M', ?, 'x', 'active')")
            ->execute(['modal-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid']);
        $users[$suffix] = (int) $pdo->lastInsertId();
    }
    $context = FinanceContext::forUser($users['a']);
    $a = new Finance($context);
    $b = new Finance(FinanceContext::forUser($users['b']));
    $account = $a->createAccount(['name' => 'Conta', 'type' => 'checking', 'opening_balance' => '1.000,00']);
    $other = $a->createAccount(['name' => 'Outra', 'type' => 'checking']);
    $count = static function (string $description) use ($pdo, $context): int {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM finance_entries WHERE financial_context_id = ? AND description LIKE ?');
        $statement->execute([$context, $description]);
        return (int) $statement->fetchColumn();
    };
    $balance = static fn (int $id): int => (int) array_column($a->accountsWithBalance(), 'balance_cents', 'id')[$id];

    $key = bin2hex(random_bytes(16));
    $single = ['description' => 'Unica', 'amount' => '100,00', 'due_date' => date('Y-m-d'), 'account_id' => $account, 'idempotency_key' => $key];
    $id = $a->createEntry('expense', $single, $users['a']);
    $check($a->createEntry('expense', $single, $users['a']) === $id && $count('Unica') === 1, 'Lançamento único reenviado é registrado uma vez');
    $check($balance($account) === 100000, 'Lançamento previsto não altera o saldo');
    $check($a->settle($id) && $balance($account) === 90000, 'Pagar atualiza o saldo uma vez');

    $a->updateExpense($id, ['description' => 'Unica paga', 'amount' => '150,00', 'due_date' => date('Y-m-d'), 'account_id' => $other, 'notes' => 'Nota editada', 'tags' => 'Casa, Lazer'], $users['a'], [$upload('c.txt', 'Comprovante de teste em texto simples.')]);
    $check($balance($account) === 100000 && $balance($other) === -15000, 'Editar lançamento pago move o valor certo entre as contas');
    $data = $a->entryEditData([$id])[$id];
    $check($data['amount'] === '150,00' && $data['notes'] === 'Nota editada' && $data['tags'] === ['Casa', 'Lazer'] && count($data['attachments']) === 1, 'Edição guarda valor, observação, tags e anexo');
    $attachment = $data['attachments'][0]['id'];
    $check($b->attachment($attachment) === null, 'Anexo não abre em outro contexto');
    $stored = $a->attachment($attachment)['stored_path'];
    $a->updateExpense($id, ['description' => 'Unica paga', 'amount' => '150,00', 'due_date' => date('Y-m-d'), 'account_id' => $other, 'tags' => 'Casa', 'remove_attachments' => [$attachment]], $users['a']);
    $data = $a->entryEditData([$id])[$id];
    $check($data['tags'] === ['Casa'] && $data['attachments'] === [] && !is_file(BASE_PATH . '/storage/finance/' . $stored), 'Remover tag e anexo apaga o arquivo');
    try {
        $b->updateExpense($id, ['description' => 'Invasao', 'amount' => '1,00', 'due_date' => date('Y-m-d'), 'account_id' => $other], $users['b']);
        $check(false, 'Outro contexto edita lançamento');
    } catch (InvalidArgumentException) {
        $check($b->entryEditData([$id]) === [] && $count('Unica paga') === 1, 'Outro contexto não lê nem edita o lançamento');
    }

    $receivables = new Receivables($context);
    $legacy = $a->createEntry('income', ['description' => 'Antiga', 'amount' => '10,00', 'due_date' => date('Y-m-d'), 'account_id' => $account], $users['a']);
    $a->settle($legacy);
    $before = $balance($account);
    $receivables->update($legacy, ['description' => 'Antiga 2', 'amount' => '10,00', 'due_date' => date('Y-m-d'), 'competence' => date('Y-m'), 'payment_method' => 'pix', 'account_id' => $account], $users['a']);
    $check($balance($account) === $before, 'Editar receita liquidada sem baixa registrada não muda o saldo');
    $income = $receivables->createWithOptions(['description' => 'Sem categoria', 'amount' => '30,00', 'due_date' => '2027-01-31', 'competence' => '2027-01', 'payment_method' => 'other', 'account_id' => $account, 'idempotency_key' => bin2hex(random_bytes(16)), 'repeat' => 'fixed', 'repeat_count' => '3', 'repeat_every' => 'monthly'], $users['a']);
    $check($income['count'] === 3 && $count('Sem categoria') === 3, 'Receita sem categoria com repetição gera as três ocorrências');

    $service = new RecurringFinance($context);
    $today = new DateTimeImmutable('today');
    $sub = $service->create(['item_type' => 'subscription', 'kind' => 'expense', 'name' => 'Assinatura 31', 'amount' => '20,00', 'frequency' => 'monthly', 'next_due_date' => '2027-01-31', 'account_id' => $account, 'auto_create' => '1'], $users['a']);
    $check($count('Assinatura 31') === 1 && $service->firstEntryId($sub) !== null, 'Assinatura cria só a primeira ocorrência, sem registros futuros');
    $nextDue = static function () use ($pdo, $sub): string {
        $statement = $pdo->prepare('SELECT next_due_date FROM finance_recurring_items WHERE id = ?');
        $statement->execute([$sub]);
        return (string) $statement->fetchColumn();
    };
    $check($nextDue() === '2027-02-28', 'Próxima cobrança ajusta para fevereiro');
    $service->generateDue(new DateTimeImmutable('2027-03-31'), 100);
    $service->generateDue(new DateTimeImmutable('2027-03-31'), 100);
    $check($count('Assinatura 31') === 3 && $nextDue() === '2027-04-30', 'Processar duas vezes não duplica, e o dia 31 volta depois de fevereiro');
    $check(array_column($service->planned('expense', '2027-04-01', '2027-05-31'), 'due_date') === ['2027-04-30', '2027-05-31'], 'A previsão segue o mesmo dia original');
    $service->update($sub, ['item_type' => 'subscription', 'name' => 'Assinatura nova', 'amount' => '25,00', 'frequency' => 'monthly', 'next_due_date' => '2027-05-10', 'account_id' => $account, 'auto_create' => '1']);
    $service->generateDue(new DateTimeImmutable('2027-05-10'), 100);
    $check($count('Assinatura nova') === 1 && $count('Assinatura 31') === 3, 'Editar vale para as próximas cobranças e não muda as geradas');
    $service->changeStatus($sub, 'canceled');
    $service->generateDue(new DateTimeImmutable('2028-01-01'), 100);
    $check($count('Assinatura nova') === 1, 'Assinatura cancelada para de gerar');
    try {
        (new RecurringFinance(FinanceContext::forUser($users['b'])))->update($sub, ['name' => 'X', 'amount' => '1,00', 'next_due_date' => '2027-05-10', 'account_id' => $account]);
        $check(false, 'Outro contexto edita assinatura');
    } catch (InvalidArgumentException) {
        echo "OK - Outro contexto não edita a assinatura\n";
    }
    echo "PASSOU\n";
} finally {
    foreach ($users as $user) {
        // A conta é protegida pela recorrência: a recorrência sai antes do usuário.
        $pdo->prepare('DELETE r FROM finance_recurring_items AS r INNER JOIN financial_contexts AS c ON c.id = r.financial_context_id WHERE c.personal_user_id = ?')->execute([$user]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user]);
    }
}
