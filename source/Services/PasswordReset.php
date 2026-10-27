<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;
use Throwable;

final class PasswordReset
{
    public function issue(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            return null;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([trim($email)]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE auth_tokens SET used_at = NOW() WHERE user_id = ? AND type = 'password_reset' AND used_at IS NULL");
            $stmt->execute([(int) $id]);
            $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, type, token_hash, expires_at) VALUES (?, 'password_reset', ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
            $stmt->execute([(int) $id, hash('sha256', $token)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return $token;
    }

    public function valid(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }
        $stmt = Connection::get()->prepare(
            "SELECT 1 FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.type = 'password_reset' AND t.used_at IS NULL AND t.expires_at > NOW() AND u.status = 'active' LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);
        return $stmt->fetchColumn() !== false;
    }

    public function consume(string $token, string $password): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || strlen($password) < 12 || strlen($password) > 4096) {
            return false;
        }
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT t.id, t.user_id FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.type = 'password_reset' AND t.used_at IS NULL AND t.expires_at > NOW() AND u.status = 'active' LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([hash('sha256', $token)]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record) {
                $pdo->rollBack();
                return false;
            }
            $stmt = $pdo->prepare('UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $record['user_id']]);
            $stmt = $pdo->prepare("UPDATE auth_tokens SET used_at = NOW() WHERE user_id = ? AND type = 'password_reset' AND used_at IS NULL");
            $stmt->execute([$record['user_id']]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
