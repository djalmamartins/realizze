<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;

/** TOTP RFC 6238 (SHA-1, 30 s, 6 dígitos) com segredo cifrado em repouso. */
final class TwoFactorAuth
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function status(int $userId): array
    {
        $statement = Connection::get()->prepare('SELECT enabled_at,pending_secret_encrypted FROM user_two_factor WHERE user_id=?');
        $statement->execute([$userId]);
        $row = $statement->fetch();
        return ['enabled' => is_array($row) && $row['enabled_at'] !== null, 'pending' => is_array($row) && $row['pending_secret_encrypted'] !== null];
    }

    public static function begin(int $userId, string $password): array
    {
        self::assertPassword($userId, $password);
        $secret = self::base32(random_bytes(20));
        Connection::get()->prepare(
            'INSERT INTO user_two_factor (user_id,pending_secret_encrypted) VALUES (?,?) ON DUPLICATE KEY UPDATE pending_secret_encrypted=VALUES(pending_secret_encrypted),failed_attempts=0,locked_until=NULL'
        )->execute([$userId, self::encrypt($secret)]);
        $user = Connection::get()->prepare('SELECT email FROM users WHERE id=?');
        $user->execute([$userId]);
        $email = (string) $user->fetchColumn();
        $uri = 'otpauth://totp/Realizze:' . rawurlencode($email) . '?secret=' . $secret . '&issuer=Realizze&algorithm=SHA1&digits=6&period=30';
        UserActivity::record($userId, '2fa.setup_started');
        return ['secret' => $secret, 'uri' => $uri];
    }

    public static function confirm(int $userId, string $code): array
    {
        $statement = Connection::get()->prepare('SELECT pending_secret_encrypted FROM user_two_factor WHERE user_id=?');
        $statement->execute([$userId]);
        $encrypted = $statement->fetchColumn();
        $counter = null;
        if (!is_string($encrypted) || !self::verifySecret(self::decrypt($encrypted), $code, $counter)) {
            UserActivity::record($userId, '2fa.enable', 'failure');
            throw new \InvalidArgumentException('Código inválido ou expirado.');
        }
        $codes = [];
        for ($i = 0; $i < 8; $i++) $codes[] = strtoupper(bin2hex(random_bytes(5)));
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE user_two_factor SET secret_encrypted=pending_secret_encrypted,pending_secret_encrypted=NULL,enabled_at=NOW(),last_counter=NULL,failed_attempts=0,locked_until=NULL WHERE user_id=?')->execute([$userId]);
            $pdo->prepare('DELETE FROM user_recovery_codes WHERE user_id=?')->execute([$userId]);
            $insert = $pdo->prepare('INSERT INTO user_recovery_codes (user_id,code_hash) VALUES (?,?)');
            foreach ($codes as $code) $insert->execute([$userId, hash('sha256', $code)]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        UserActivity::record($userId, '2fa.enabled');
        return $codes;
    }

    public static function disable(int $userId, string $password, string $code): void
    {
        self::assertPassword($userId, $password);
        if (!self::verify($userId, $code, false)) throw new \InvalidArgumentException('Segundo fator inválido.');
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM user_recovery_codes WHERE user_id=?')->execute([$userId]);
        $pdo->prepare('DELETE FROM user_two_factor WHERE user_id=?')->execute([$userId]);
        UserActivity::record($userId, '2fa.disabled');
    }

    public static function verify(int $userId, string $code, bool $consume = true): bool
    {
        $pdo = Connection::get();
        $statement = $pdo->prepare('SELECT secret_encrypted,last_counter,failed_attempts,locked_until FROM user_two_factor WHERE user_id=? AND enabled_at IS NOT NULL');
        $statement->execute([$userId]);
        $row = $statement->fetch();
        if (!is_array($row) || ($row['locked_until'] !== null && strtotime($row['locked_until']) > time())) return false;
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $code));
        $recovery = $pdo->prepare('SELECT id FROM user_recovery_codes WHERE user_id=? AND code_hash=? AND used_at IS NULL LIMIT 1');
        $recovery->execute([$userId, hash('sha256', $normalized)]);
        $recoveryId = $recovery->fetchColumn();
        if ($recoveryId !== false) {
            if ($consume) $pdo->prepare('UPDATE user_recovery_codes SET used_at=NOW() WHERE id=? AND used_at IS NULL')->execute([$recoveryId]);
            return true;
        }
        $counter = null;
        $valid = self::verifySecret(self::decrypt((string) $row['secret_encrypted']), $normalized, $counter);
        if (!$valid || ($row['last_counter'] !== null && $counter !== null && $counter <= (int) $row['last_counter'])) {
            $pdo->prepare('UPDATE user_two_factor SET failed_attempts=failed_attempts+1,locked_until=IF(failed_attempts+1>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),locked_until) WHERE user_id=?')->execute([$userId]);
            return false;
        }
        if ($consume) $pdo->prepare('UPDATE user_two_factor SET last_counter=?,failed_attempts=0,locked_until=NULL WHERE user_id=?')->execute([$counter, $userId]);
        return true;
    }

    private static function verifySecret(string $secret, string $code, ?int &$matched): bool
    {
        if (preg_match('/\A\d{6}\z/', $code) !== 1) return false;
        $now = intdiv(time(), 30);
        foreach ([-1, 0, 1] as $offset) {
            $counter = $now + $offset;
            if (hash_equals(self::hotp($secret, $counter), $code)) { $matched = $counter; return true; }
        }
        return false;
    }

    private static function hotp(string $base32, int $counter): string
    {
        $key = self::decode32($base32);
        $binary = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 15;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (unpack('C*', $bytes) as $byte) $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        return $out;
    }

    private static function decode32(string $value): string
    {
        $bits = '';
        foreach (str_split(strtoupper($value)) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) throw new \RuntimeException('Segredo 2FA inválido.');
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
        return $out;
    }

    private static function assertPassword(int $userId, string $password): void
    {
        $statement = Connection::get()->prepare('SELECT password FROM users WHERE id=?');
        $statement->execute([$userId]);
        if (!password_verify($password, (string) $statement->fetchColumn())) throw new \InvalidArgumentException('Senha atual inválida.');
    }

    private static function encryptionKey(): string
    {
        $key = (string) (getenv('APP_KEY') ?: getenv('DB_PASSWORD'));
        if ($key === '') throw new \RuntimeException('Chave da aplicação indisponível.');
        return hash('sha256', $key, true);
    }

    private static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipher)) throw new \RuntimeException('Não foi possível proteger o segundo fator.');
        return base64_encode($iv . $tag . $cipher);
    }

    private static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if (!is_string($raw) || strlen($raw) < 29) throw new \RuntimeException('Segundo fator indisponível.');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if (!is_string($plain)) throw new \RuntimeException('Segundo fator indisponível.');
        return $plain;
    }
}
