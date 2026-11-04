<?php
declare(strict_types=1);

/** Repetição, tags, anexos e baixa imediata nos lançamentos. Exige a migration 008; limpa o que cria. */
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';

use Moves\Services\Finance;
use Moves\Services\FinanceContext;
use Moves\Services\Receivables;
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
try {
    foreach (['a', 'b'] as $suffix) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES ('T', 'F', ?, 'x', 'active')")
            ->execute(['entry-' . bin2hex(random_bytes(6)) . $suffix . '@example.invalid']);
        $users[$suffix] = (int) $pdo->lastInsertId();
    }
    $contextA = FinanceContext::forUser($users['a']);
    $a = new Finance($contextA);
    $b = new Finance(FinanceContext::forUser($users['b']));
    $check($a->supportsEntryExtras(), 'Migration 008 aplicada');
    $account = $a->createAccount(['name' => 'Conta', 'type' => 'checking']);
    $rows = static function (string $where, array $params) use ($pdo): array {
        $statement = $pdo->prepare("SELECT * FROM finance_entries WHERE {$where} ORDER BY id");
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    };
    $base = ['amount' => '100,00', 'due_date' => '2027-01-31', 'account_id' => $account];

    $first = $a->createEntry('expense', $base + ['description' => 'Curso', 'repeat' => 'installment', 'repeat_count' => '3', 'repeat_every' => 'monthly', 'paid' => '1', 'tags' => 'Estudo, estudo,  Casa ', 'notes' => 'Nota'], $users['a']);
    $series = $rows('id >= ? AND financial_context_id = ?', [$first, $contextA]);
    $check(array_column($series, 'amount_cents') === [3334, 3333, 3333], 'Parcelas somam o total, com a sobra na primeira');
    $check(array_column($series, 'due_date') === ['2027-01-31', '2027-02-28', '2027-03-31'], 'Dia 31 ajusta nos meses curtos e volta depois');
    $check($series[1]['description'] === 'Curso (2/3)', 'Parcela identificada na descrição');
    $check($series[0]['paid_at'] !== null && $series[1]['paid_at'] === null, 'Só a primeira ocorrência fica paga');
    $details = $a->entryDetails(array_map('intval', array_column($series, 'id')));
    $check($details[$first]['tags'] === ['Casa', 'Estudo'] && $details[$first]['notes'] === 'Nota', 'Tags sem repetição e observação gravadas');
    $check($a->tags() === ['Casa', 'Estudo'] && $b->tags() === [], 'Tags ficam no próprio contexto');

    $fixed = $a->createEntry('expense', $base + ['description' => 'Aluguel', 'repeat' => 'fixed', 'repeat_count' => '12', 'repeat_every' => 'yearly'], $users['a']);
    $list = $rows('id >= ? AND financial_context_id = ?', [$fixed, $contextA]);
    $check(count($list) === 12 && (int) $list[11]['amount_cents'] === 10000 && $list[11]['due_date'] === '2038-01-31', 'Valor fixo repete o valor inteiro');

    foreach ([['repeat_count' => '1'], ['repeat_count' => '121'], ['repeat_count' => '2', 'repeat_every' => 'x']] as $bad) {
        try {
            $a->createEntry('expense', $bad + $base + ['description' => 'X', 'repeat' => 'fixed', 'repeat_every' => 'monthly'], $users['a']);
            $check(false, 'Repetição inválida aceita');
        } catch (InvalidArgumentException) {
        }
    }
    echo "OK - Repetição inválida recusada\n";

    $withFile = $a->createEntry('expense', $base + ['description' => 'Com anexo'], $users['a'], [$upload('nota.txt', 'conteudo')]);
    $detail = $a->entryDetails([$withFile])[$withFile];
    $attachmentId = $detail['attachments'][0]['id'];
    $found = $a->attachment($attachmentId);
    $check($detail['attachments'][0]['name'] === 'nota.txt' && $found !== null, 'Anexo gravado');
    $check($b->attachment($attachmentId) === null, 'Outro contexto não acessa o anexo');
    $path = BASE_PATH . '/storage/finance/' . $found['stored_path'];
    $check(is_file($path), 'Arquivo no armazenamento privado');
    try {
        $a->createEntry('expense', $base + ['description' => 'Ruim'], $users['a'], [$upload('virus.php', '<?php echo 1;')]);
        $check(false, 'Formato proibido aceito');
    } catch (InvalidArgumentException) {
        echo "OK - Formato proibido recusado\n";
    }
    $check($rows('description = ? AND financial_context_id = ?', ['Ruim', $contextA]) === [], 'Falha no anexo não deixa lançamento');
    $a->delete($withFile);
    $check(!is_file($path), 'Excluir o lançamento apaga o arquivo');

    $income = $a->createEntry('income', $base + ['description' => 'Venda', 'due_date' => date('Y-m-d'), 'paid' => '1'], $users['a']);
    $receivables = new Receivables($contextA);
    $category = $a->createCategory(['name' => 'Vendas', 'category_kind' => 'income', 'color' => '#078b4c']);
    $find = static fn (int $id): ?array => array_values(array_filter($receivables->listing(['start' => '2020-01-01', 'end' => '2040-12-31', 'page' => 1])['rows'], static fn (array $row): bool => (int) $row['id'] === $id))[0] ?? null;
    $check($find($income)['display_status'] === 'received', 'Receita já recebida aparece baixada em Contas a receber');

    $form = ['description' => 'Mensalidade', 'amount' => '90,00', 'due_date' => date('Y-m-d'), 'competence' => date('Y-m'), 'payment_method' => 'pix', 'account_id' => $account, 'category_id' => $category, 'idempotency_key' => bin2hex(random_bytes(16)), 'repeat' => 'installment', 'repeat_count' => '3', 'repeat_every' => 'monthly', 'paid' => '1', 'tags' => 'Plano', 'notes' => 'Obs'];
    $created = $receivables->createWithOptions($form, $users['a'], [$upload('contrato.pdf', "%PDF-1.4\n%%EOF\n")]);
    $check($created['count'] === 3 && !$created['duplicate'], 'Recebimento parcelado criado');
    $check($find($created['id'])['display_status'] === 'received' && (int) $find($created['id'])['amount_cents'] === 3000, 'Primeira parcela baixada');
    $check($find($created['id'] + 1)['display_status'] === 'pending', 'Demais parcelas em aberto');
    $receivableDetail = $a->entryDetails([$created['id'], $created['id'] + 1]);
    $check($receivableDetail[$created['id']]['attachments'] !== [] && $receivableDetail[$created['id'] + 1]['tags'] === ['Plano'], 'Anexo na primeira e tag em todas');
    $check($receivables->createWithOptions($form, $users['a'])['duplicate'], 'Reenvio do formulário não duplica');
    $storedReceivable = $a->attachment($receivableDetail[$created['id']]['attachments'][0]['id']);
    $a->delete($created['id']);
    $check(!is_file(BASE_PATH . '/storage/finance/' . $storedReceivable['stored_path']), 'Limpeza do anexo do recebimento');
} finally {
    foreach ($users as $id) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
