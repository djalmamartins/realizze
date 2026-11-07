<?php
// FIN-027: total por categoria ignora lançamentos cancelados (roteiro PF-18). Só no banco de teste.
declare(strict_types=1);
require_once dirname(__DIR__) . '/source/Boot/app.php';
require_once dirname(__DIR__) . '/source/Boot/database.php';
use Moves\Services\Finance;
$pdo = \MovesCode\Model\Connection::get();
if (!str_contains((string) $pdo->query('SELECT DATABASE()')->fetchColumn(), 'test')) { fwrite(STDERR, "Banco de teste obrigatório.\n"); exit(1); }
$context = (int) $pdo->query("SELECT c.id FROM organizations o JOIN financial_contexts c ON c.organization_id=o.id WHERE o.slug='missao-alfa-ltda'")->fetchColumn();
$account = (int) $pdo->query("SELECT id FROM finance_accounts WHERE financial_context_id=$context AND type<>'credit_card' ORDER BY id LIMIT 1")->fetchColumn();
$finance = new Finance($context);
$name = 'Cat FIN-027 ' . bin2hex(random_bytes(3));
$category = $finance->createCategory(['name' => $name, 'category_kind' => 'expense']);
$insert = $pdo->prepare("INSERT INTO finance_entries (financial_context_id, account_id, category_id, kind, description, amount_cents, due_date, status) VALUES (?, ?, ?, 'expense', ?, ?, ?, ?)");
$insert->execute([$context, $account, $category, "$name ativa", 1500, date('Y-m-d'), 'pending']);
$insert->execute([$context, $account, $category, "$name cancelada", 9900, date('Y-m-d'), 'cancelled']);
$row = array_values(array_filter($finance->categoriesWithTotals(date('Y-m-01'), date('Y-m-t')), static fn (array $c): bool => (int) $c['id'] === $category))[0] ?? [];
$okTotal = (int) ($row['total'] ?? -1) === 1500 && (int) ($row['entries'] ?? -1) === 1;
echo ($okTotal ? 'OK' : 'FALHOU') . " - Categoria soma R$ 15,00 em 1 lançamento, sem o cancelado (veio {$row['total']} em {$row['entries']})\n";
$pdo->prepare('DELETE FROM finance_entries WHERE category_id = ?')->execute([$category]);
$finance->deleteCategory($category);
exit($okTotal ? 0 : 1);
