<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;

final class Authentication
{
    public function attempt(string $email, string $password): ?array
    {
        $email = trim($email);

        if ($email === '' || $password === '') {
            return null;
        }

        $statement = Connection::get()->prepare(
            'SELECT id, first_name, last_name, email,
                    password, status, auth_version
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $statement->execute(['email' => $email]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        if ($user['status'] !== 'active') {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'status' => $user['status'],
            'auth_version' => (int) $user['auth_version'],
        ];
    }
}
