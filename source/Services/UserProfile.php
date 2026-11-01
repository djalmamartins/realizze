<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;
use Moves\Support\Upload;

final class UserProfile
{
    public static function get(int $userId): ?array
    {
        $statement = Connection::get()->prepare('SELECT id,first_name,last_name,email,phone,phone_verified_at,datebirth,document,photo,locale,timezone,biography,status,created_at FROM users WHERE id=?');
        $statement->execute([$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function update(int $userId, array $input): void
    {
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        if ($first === '' || $last === '' || mb_strlen($first) > 100 || mb_strlen($last) > 100) throw new \InvalidArgumentException('Informe nome e sobrenome válidos.');
        $phone = self::phone((string) ($input['phone'] ?? ''));
        $birth = trim((string) ($input['datebirth'] ?? ''));
        if ($birth !== '' && (\DateTimeImmutable::createFromFormat('!Y-m-d', $birth) === false || $birth > date('Y-m-d'))) throw new \InvalidArgumentException('Data de nascimento inválida.');
        $timezone = (string) ($input['timezone'] ?? 'America/Sao_Paulo');
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) throw new \InvalidArgumentException('Fuso horário inválido.');
        $biography = trim((string) ($input['biography'] ?? ''));
        if (mb_strlen($biography) > 500) throw new \InvalidArgumentException('A biografia deve ter no máximo 500 caracteres.');
        $before = self::get($userId);
        $statement = Connection::get()->prepare('UPDATE users SET first_name=?,last_name=?,phone_verified_at=IF(phone<=>?,phone_verified_at,NULL),phone=?,datebirth=?,locale=?,timezone=?,biography=?,updated_at=NOW() WHERE id=?');
        $statement->execute([$first, $last, $phone, $phone, $birth ?: null, 'pt_BR', $timezone, $biography ?: null, $userId]);
        UserActivity::record($userId, 'profile.updated', 'success', ['field' => ($before['phone'] ?? null) === $phone ? 'personal_data' : 'personal_data,phone']);
    }

    public static function changePassword(int $userId, string $current, string $new, string $confirmation): void
    {
        $limiter = new AuthRateLimiter();
        if (!$limiter->hit('profile-password', (string) $userId, 5, 900)) {
            UserActivity::record($userId, 'password.changed', 'blocked');
            throw new \InvalidArgumentException('Muitas tentativas. Aguarde alguns minutos.');
        }
        $statement = Connection::get()->prepare('SELECT password FROM users WHERE id=?');
        $statement->execute([$userId]);
        if (!password_verify($current, (string) $statement->fetchColumn())) {
            UserActivity::record($userId, 'password.changed', 'failure');
            throw new \InvalidArgumentException('A senha atual não confere.');
        }
        if ($new !== $confirmation || strlen($new) < 12 || strlen($new) > 128 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) throw new \InvalidArgumentException('Use ao menos 12 caracteres, com letras e números, e confirme a nova senha.');
        if (password_verify($new, (string) $statement->fetchColumn())) throw new \InvalidArgumentException('A nova senha deve ser diferente da atual.');
        Connection::get()->prepare('UPDATE users SET password=?,auth_version=auth_version+1,updated_at=NOW() WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        $version = (int) Connection::get()->query('SELECT auth_version FROM users WHERE id=' . $userId)->fetchColumn();
        $_SESSION['auth_version'] = $version;
        UserSessions::revokeOthers($userId);
        UserActivity::record($userId, 'password.changed');
        self::securityNotification($userId, 'security.password_changed', 'Senha alterada', 'Sua senha foi alterada. Se não foi você, proteja sua conta imediatamente.');
    }

    public static function updatePhoto(int $userId, array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
        $directory = BASE_PATH . '/public/uploads/profiles';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new \RuntimeException('Diretório de fotos indisponível.');
        $upload = new Upload($directory, 2097152);
        $stored = $upload->image($file, 'perfil-' . $userId . '-' . bin2hex(random_bytes(8)), 640);
        if ($stored === null) throw new \InvalidArgumentException('A foto deve ser JPG, PNG ou WebP válido, com até 2 MB.');
        $profile = self::get($userId);
        Connection::get()->prepare('UPDATE users SET photo=?,updated_at=NOW() WHERE id=?')->execute(['/uploads/profiles/' . $stored, $userId]);
        $old = (string) ($profile['photo'] ?? '');
        if (str_starts_with($old, '/uploads/profiles/')) $upload->remove(substr($old, strlen('/uploads/profiles/')));
        UserActivity::record($userId, 'profile.photo_updated');
    }

    public static function notificationPreferences(int $userId): array
    {
        $categories = ['security','access','invites','permissions','income','expenses','due_dates','goals','system'];
        $saved = Connection::get()->prepare('SELECT category,in_app_enabled,email_enabled FROM user_notification_preferences WHERE user_id=?');
        $saved->execute([$userId]);
        $map = [];
        foreach ($saved->fetchAll(PDO::FETCH_ASSOC) as $row) $map[$row['category']] = ['in_app' => (bool) $row['in_app_enabled'], 'email' => (bool) $row['email_enabled']];
        foreach ($categories as $category) $map[$category] ??= ['in_app' => true, 'email' => $category === 'security'];
        return $map;
    }

    public static function saveNotificationPreferences(int $userId, array $input): void
    {
        $critical = ['security', 'access'];
        $statement = Connection::get()->prepare('INSERT INTO user_notification_preferences (user_id,category,in_app_enabled,email_enabled) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE in_app_enabled=VALUES(in_app_enabled),email_enabled=VALUES(email_enabled)');
        foreach (array_keys(self::notificationPreferences($userId)) as $category) {
            $inApp = in_array($category, $critical, true) || isset($input['in_app'][$category]);
            $statement->execute([$userId, $category, (int) $inApp, (int) isset($input['email'][$category])]);
        }
        UserActivity::record($userId, 'notifications.preferences_updated');
    }

    public static function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) $digits = substr($digits, 2);
        if (!preg_match('/\A[1-9]{2}9?\d{8}\z/', $digits) || preg_match('/^(\d)\1+$/', $digits)) throw new \InvalidArgumentException('Informe um celular brasileiro válido, com DDD.');
        return '+55' . $digits;
    }

    private static function securityNotification(int $userId, string $event, string $title, string $body): void
    {
        Connection::get()->prepare("INSERT INTO notifications (user_id,audience,event_type,title,body,priority,link,dedupe_key,created_at) VALUES (?,'customer',?,?,?,'high','/p/perfil',?,NOW())")
            ->execute([$userId, $event, $title, $body, $event . ':' . $userId . ':' . time()]);
    }
}
