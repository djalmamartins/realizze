<?php
declare(strict_types=1);

namespace Moves\Services;

use MovesCode\Model\Connection;
use PDO;
use RuntimeException;
use Throwable;

final class RegistrationFlow
{
    public function __construct(
        private readonly Registration $registration = new Registration(),
        private readonly EmailVerification $verification = new EmailVerification()
    ) {
    }

    public function registerAndSend(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        bool $acceptedTerms,
        bool $acceptedPrivacy,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?VerificationMailer $mailer = null
    ): array {
        $result = $this->register(
            $firstName,
            $lastName,
            $email,
            $password,
            $acceptedTerms,
            $acceptedPrivacy,
            $ipAddress,
            $userAgent
        );

        // O cadastro e o token já foram persistidos.
        // Uma falha SMTP não ativa nem remove a conta.
        ($mailer ?? new VerificationMailer())->send(
            $email,
            $firstName,
            $result['verification_token']
        );

        return [
            'user_id' => $result['user_id'],
            'status' => $result['status'],
        ];
    }

    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        bool $acceptedTerms,
        bool $acceptedPrivacy,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $pdo = Connection::get();

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'RegistrationFlow exige uma transação própria.'
            );
        }

        $pdo->beginTransaction();

        try {
            $userId = $this->registration->register(
                $firstName,
                $lastName,
                $email,
                $password,
                $acceptedTerms,
                $acceptedPrivacy,
                'v1',
                'v1',
                $ipAddress,
                $userAgent
            );

            $token = $this->verification->issue($userId);

            $pdo->commit();

            return [
                'user_id' => $userId,
                'verification_token' => $token,
                'status' => 'registered',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
