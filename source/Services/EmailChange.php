<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;
use Throwable;

/**
 * Troca do e-mail de acesso (migration 022). O pedido exige a senha atual; o e-mail só muda quando o link enviado ao
 * NOVO endereço é confirmado. Ao confirmar, as outras sessões caem (auth_version) e o endereço antigo é avisado.
 */
final class EmailChange
{
    public static function ready(): bool
    {
        static $ready = null;
        return $ready ??= Connection::get()->query("SHOW TABLES LIKE 'user_email_changes'")->fetchColumn() !== false;
    }

    /** @return string token que vai no link (só o hash fica no banco) */
    public static function request(int $userId, string $password, string $newEmail, ?callable $send = null): string
    {
        if (!self::ready()) throw new InvalidArgumentException('A troca de e-mail ainda não está disponível.');
        $pdo = Connection::get();
        $q = $pdo->prepare("SELECT email, password, first_name FROM users WHERE id = ? AND status = 'active'");
        $q->execute([$userId]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        if ($user === false) throw new InvalidArgumentException('Conta não encontrada.');
        if (!password_verify($password, (string) $user['password'])) throw new InvalidArgumentException('Senha incorreta. O e-mail não foi alterado.');
        $email = mb_strtolower(trim($newEmail));
        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('Informe um e-mail válido.');
        if ($email === mb_strtolower((string) $user['email'])) throw new InvalidArgumentException('Este já é o seu e-mail de acesso.');
        // Mesma resposta para endereço livre ou em uso: evita descobrir quem tem conta. O uso é conferido de novo ao confirmar.
        $taken = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $taken->execute([$email]);
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE user_email_changes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
        if ((int) $taken->fetchColumn() === 0) {
            $pdo->prepare('INSERT INTO user_email_changes (user_id, new_email, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')
                ->execute([$userId, $email, hash('sha256', $token)]);
            ($send ?? [self::class, 'sendLink'])($email, (string) $user['first_name'], $token);
        }
        UserActivity::record($userId, 'profile.email_change_requested');
        return $token;
    }

    /** Pedido pendente e válido para mostrar na tela de confirmação. */
    public static function pending(string $token): ?array
    {
        if (!self::ready() || !preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $q = Connection::get()->prepare('SELECT id, user_id, new_email FROM user_email_changes WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
        $q->execute([hash('sha256', $token)]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function confirm(string $token): bool
    {
        if (!self::ready() || !preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT c.id, c.user_id, c.new_email, u.email old_email FROM user_email_changes c INNER JOIN users u ON u.id = c.user_id
                WHERE c.token_hash = ? AND c.used_at IS NULL AND c.expires_at > NOW() FOR UPDATE');
            $q->execute([hash('sha256', $token)]);
            $change = $q->fetch(PDO::FETCH_ASSOC);
            if ($change === false) { $pdo->rollBack(); return false; }
            $pdo->prepare('UPDATE user_email_changes SET used_at = NOW() WHERE id = ?')->execute([(int) $change['id']]);
            $taken = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $taken->execute([$change['new_email'], (int) $change['user_id']]);
            if ((int) $taken->fetchColumn() > 0) { $pdo->commit(); return false; }
            $pdo->prepare('UPDATE users SET email = ?, email_verified_at = NOW(), auth_version = auth_version + 1, updated_at = NOW() WHERE id = ?')
                ->execute([$change['new_email'], (int) $change['user_id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        UserActivity::record((int) $change['user_id'], 'profile.email_changed');
        try {
            (new Mail())->send((string) $change['old_email'], 'Seu e-mail de acesso foi alterado | Realizze',
                '<p>O e-mail de acesso da sua conta Realizze foi alterado para outro endereço.</p><p>Se não foi você, responda a este e-mail ou fale com o suporte imediatamente.</p>',
                "O e-mail de acesso da sua conta Realizze foi alterado para outro endereço.\n\nSe não foi você, fale com o suporte imediatamente.");
        } catch (Throwable $exception) {
            error_log('Realizze email change notice: ' . $exception->getMessage());
        }
        return true;
    }

    public static function sendLink(string $email, string $firstName, string $token): void
    {
        $base = getenv('APP_URL');
        if (!is_string($base) || !filter_var($base, FILTER_VALIDATE_URL)) throw new \RuntimeException('APP_URL inválida.');
        $url = rtrim($base, '/') . '/confirmar-novo-email?token=' . rawurlencode($token);
        $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = htmlspecialchars($firstName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        (new Mail())->send($email, 'Confirme seu novo e-mail | Realizze',
            "<p>Olá, {$name}.</p><p>Confirme este endereço como o novo e-mail de acesso ao Realizze.</p><p><a href=\"{$safe}\">Confirmar novo e-mail</a></p><p>O link expira em 24 horas. Se não pediu a troca, ignore este e-mail.</p>",
            "Olá, {$firstName}.\n\nConfirme este endereço como o novo e-mail de acesso ao Realizze: {$url}\n\nO link expira em 24 horas. Se não pediu a troca, ignore este e-mail.");
    }
}
