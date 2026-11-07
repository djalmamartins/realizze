<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;

/**
 * Direitos do titular (LGPD, art. 18): cópia dos dados pessoais e exclusão da conta.
 * Só a área pessoal (PF). Dados de empresa pertencem à empresa e ficam com ela.
 */
final class PersonalData
{
    /** Nunca saem na exportação: segredos, hashes e caminhos internos de arquivo. */
    private const HIDDEN_COLUMNS = ['password', 'forget', 'secret', 'secret_encrypted', 'token_hash', 'code_hash', 'email_hash',
        'session_hash', 'stored_path', 'image_path', 'idempotency_key', 'operation_key', 'auth_version'];

    /** Tabelas do usuário (por user_id) que vão na cópia. */
    private const USER_TABLES = ['user_legal_acceptances' => 'user_id', 'user_notification_preferences' => 'user_id', 'notification_preferences' => 'user_id',
        'user_sessions' => 'user_id', 'user_activity_logs' => 'owner_user_id', 'notifications' => 'user_id', 'organization_members' => 'user_id'];

    /** Tabelas sem valor para o titular ou só com segredos. */
    private const SKIPPED = ['user_recovery_codes', 'user_two_factor', 'auth_tokens', 'notification_devices'];

    /** @return array<string,mixed> */
    public static function export(int $userId): array
    {
        $pdo = Connection::get();
        $user = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$userId]);
        $profile = $user->fetch(PDO::FETCH_ASSOC);
        if ($profile === false) throw new InvalidArgumentException('Conta não encontrada.');
        $data = ['gerado_em' => date(DATE_ATOM), 'aviso' => 'Cópia dos dados pessoais da conta no Realizze. Dados de empresas ficam com as empresas.',
            'perfil' => self::clean([$profile])[0], 'tabelas' => []];
        foreach (self::USER_TABLES as $table => $column) {
            if (!self::columnExists($table, $column)) continue;
            $q = $pdo->prepare("SELECT * FROM `$table` WHERE `$column` = ?");
            $q->execute([$userId]);
            $data['tabelas'][$table] = self::clean($q->fetchAll(PDO::FETCH_ASSOC));
        }
        $context = self::personalContext($userId);
        if ($context !== null) {
            foreach (self::contextTables() as $table) {
                if (in_array($table, self::SKIPPED, true) || $table === 'notifications') continue;
                $q = $pdo->prepare("SELECT * FROM `$table` WHERE financial_context_id = ?");
                $q->execute([$context]);
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);
                if ($rows !== []) $data['tabelas'][$table] = self::clean($rows);
            }
        }
        UserActivity::record($userId, 'privacy.data_exported');
        return $data;
    }

    /**
     * Exclui a conta e a área pessoal. Recusa com motivo quando há dados que pertencem a outras pessoas:
     * empresas das quais é proprietário, registros criados em empresas, chamados de suporte e assinaturas.
     */
    public static function delete(int $userId, string $password, string $confirmation): void
    {
        $pdo = Connection::get();
        $q = $pdo->prepare('SELECT password, photo FROM users WHERE id = ?');
        $q->execute([$userId]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        if ($user === false) throw new InvalidArgumentException('Conta não encontrada.');
        if (!password_verify($password, (string) $user['password'])) throw new InvalidArgumentException('Senha incorreta. Nada foi excluído.');
        if (mb_strtoupper(trim($confirmation)) !== 'EXCLUIR') throw new InvalidArgumentException('Digite EXCLUIR para confirmar. Nada foi excluído.');
        $blockers = self::blockers($userId);
        if ($blockers !== []) {
            throw new InvalidArgumentException('Ainda não dá para excluir a conta: ' . implode('; ', $blockers) . '. Nada foi excluído.');
        }
        $context = self::personalContext($userId);
        $files = $photos = [];
        if ($context !== null) {
            if (self::tableExists('finance_entry_attachments')) {
                $f = $pdo->prepare('SELECT stored_path FROM finance_entry_attachments WHERE financial_context_id = ?');
                $f->execute([$context]);
                $files = $f->fetchAll(PDO::FETCH_COLUMN);
            }
            if (self::columnExists('finance_goals', 'image_path')) {
                $g = $pdo->prepare('SELECT image_path FROM finance_goals WHERE financial_context_id = ? AND image_path IS NOT NULL');
                $g->execute([$context]);
                $photos = $g->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        $pdo->beginTransaction();
        try {
            if ($context !== null) {
                // FKs RESTRICT para contas e contatos: apagar primeiro quem aponta para eles.
                foreach (['finance_recurring_occurrences', 'finance_recurring_items', 'finance_entry_settlements', 'finance_entries'] as $table) {
                    if (self::tableExists($table)) $pdo->prepare("DELETE FROM `$table` WHERE financial_context_id = ?")->execute([$context]);
                }
                $pdo->prepare('DELETE FROM financial_contexts WHERE id = ?')->execute([$context]);
            }
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        array_walk($files, static fn (string $path) => FinanceAttachments::remove($path));
        array_walk($photos, static fn (string $name) => GoalPhotos::remove($name));
        $photo = (string) ($user['photo'] ?? '');
        if (str_starts_with($photo, '/uploads/profiles/')) (new \Moves\Support\Upload(BASE_PATH . '/public/uploads/profiles', 2097152))->remove(substr($photo, strlen('/uploads/profiles/')));
    }

    /** @return list<string> motivos que impedem a exclusão automática */
    public static function blockers(int $userId): array
    {
        $count = static function (string $table, string $column) use ($userId): int {
            if (!self::columnExists($table, $column)) return 0;
            $q = Connection::get()->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
            $q->execute([$userId]);
            return (int) $q->fetchColumn();
        };
        $reasons = [];
        if ($count('organizations', 'owner_user_id') > 0) $reasons[] = 'você é proprietário de uma empresa (exclua a empresa ou transfira a propriedade)';
        if ($count('organization_obligations', 'created_by') + $count('organization_service_orders', 'created_by')
            + $count('organization_invitations', 'invited_by_user_id') + $count('organization_invitations', 'accepted_by_user_id') > 0) {
            $reasons[] = 'há registros criados por você em empresas, como obrigações, ordens de serviço ou convites (peça a exclusão pelo suporte)';
        }
        if ($count('helpdesk_tickets', 'requester_user_id') > 0) $reasons[] = 'há chamados de suporte no seu nome (peça a exclusão pelo suporte)';
        if ($count('subscriptions', 'user_id') > 0) $reasons[] = 'há uma assinatura no seu nome (cancele antes)';
        return $reasons;
    }

    private static function personalContext(int $userId): ?int
    {
        $q = Connection::get()->prepare("SELECT id FROM financial_contexts WHERE context_type = 'personal' AND personal_user_id = ?");
        $q->execute([$userId]);
        $id = $q->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return list<string> */
    private static function contextTables(): array
    {
        return Connection::get()->query("SELECT table_name FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'financial_context_id' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function tableExists(string $table): bool
    {
        $q = Connection::get()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $q->execute([$table]);
        return (int) $q->fetchColumn() === 1;
    }

    private static function columnExists(string $table, string $column): bool
    {
        $q = Connection::get()->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $q->execute([$table, $column]);
        return (int) $q->fetchColumn() === 1;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function clean(array $rows): array
    {
        return array_map(static fn (array $row): array => array_diff_key($row, array_flip(self::HIDDEN_COLUMNS)), $rows);
    }
}
