<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class EmailVerification
{
    public function issue(int $userId): string
    {
        $pdo = Connection::get();

        $statement = $pdo->prepare(
            "SELECT status FROM users WHERE id = ? LIMIT 1"
        );
        $statement->execute([$userId]);

        if ($statement->fetchColumn() !== 'registered') {
            throw new RuntimeException('Usuário não está aguardando confirmação.');
        }

        if (!$this->hasLegalAcceptances($userId)) {
            throw new RuntimeException('Aceites legais obrigatórios não encontrados.');
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $statement = $pdo->prepare(
            "INSERT INTO auth_tokens
             (user_id, type, token_hash, expires_at)
             VALUES (?, 'email_verification', ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
        );

        $statement->execute([$userId, $hash]);

        return $token;
    }

    public function confirm(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }

        $pdo = Connection::get();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $statement = $pdo->prepare(
                "SELECT tokens.id, tokens.user_id
                 FROM auth_tokens AS tokens
                 INNER JOIN users AS users ON users.id = tokens.user_id
                 WHERE tokens.token_hash = ?
                   AND tokens.type = 'email_verification'
                   AND tokens.used_at IS NULL
                   AND tokens.expires_at > NOW()
                   AND users.status = 'registered'
                 LIMIT 1
                 FOR UPDATE"
            );

            $statement->execute([hash('sha256', $token)]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }

                return false;
            }

            if (!$this->hasLegalAcceptances((int) $record['user_id'])) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }

                return false;
            }

            $statement = $pdo->prepare(
                "UPDATE users
                 SET status = 'active'
                 WHERE id = ? AND status = 'registered'"
            );
            $statement->execute([(int) $record['user_id']]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Não foi possível ativar o usuário.');
            }

            $statement = $pdo->prepare(
                "UPDATE auth_tokens
                 SET used_at = NOW()
                 WHERE id = ? AND used_at IS NULL"
            );
            $statement->execute([(int) $record['id']]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Não foi possível consumir o token.');
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return true;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function hasLegalAcceptances(int $userId): bool
    {
        $pdo = Connection::get();

        $statement = $pdo->prepare(
            "SELECT COUNT(DISTINCT document_type)
             FROM user_legal_acceptances
             WHERE user_id = ?
               AND document_type IN ('terms_of_use', 'privacy_policy')
               AND document_version <> ''"
        );

        $statement->execute([$userId]);

        return (int) $statement->fetchColumn() === 2;
    }

}
