<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use MovesCode\Model\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class Registration
{
    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        bool $acceptedTerms,
        bool $acceptedPrivacy,
        string $termsVersion,
        string $privacyVersion,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = trim($email);

        if ($firstName === '' || $lastName === '') {
            throw new InvalidArgumentException('Nome e sobrenome são obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail inválido.');
        }

        if (strlen($password) < 12) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 12 caracteres.');
        }

        if (!$acceptedTerms || !$acceptedPrivacy) {
            throw new InvalidArgumentException('Os aceites obrigatórios são necessários.');
        }

        if (trim($termsVersion) === '' || trim($privacyVersion) === '') {
            throw new InvalidArgumentException('Versões dos documentos são obrigatórias.');
        }

        $pdo = Connection::get();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $existing = $pdo->prepare(
            'SELECT id FROM users WHERE email = ? LIMIT 1'
        );
        $existing->execute([$email]);

        if ($existing->fetchColumn() !== false) {
            throw new InvalidArgumentException('E-mail já cadastrado.');
        }

        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO users
                 (first_name, last_name, email, password, status)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $statement->execute([
                $firstName,
                $lastName,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'registered',
            ]);

            $userId = (int) $pdo->lastInsertId();

            $acceptance = $pdo->prepare(
                'INSERT INTO user_legal_acceptances
                 (user_id, document_type, document_version, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $acceptance->execute([
                $userId,
                'terms_of_use',
                $termsVersion,
                $ipAddress,
                $userAgent,
            ]);

            $acceptance->execute([
                $userId,
                'privacy_policy',
                $privacyVersion,
                $ipAddress,
                $userAgent,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $userId;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
