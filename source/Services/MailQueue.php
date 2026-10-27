<?php
declare(strict_types=1);

namespace Moves\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Fila SMTP compartilhada. Entrega ao menos uma vez: uma falha após o aceite
 * SMTP e antes do commit pode provocar reenvio. Não promete exactly-once.
 */
final class MailQueue
{
    public function __construct(
        private readonly PDO $db,
        private readonly Mail $transport = new Mail()
    ) {
    }

    public function enqueue(
        string $recipient,
        string $subject,
        string $html,
        string $text = '',
        string $recipientName = '',
        int $maxAttempts = 5
    ): int {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) ||
            trim($subject) === '' || trim($html) === '' ||
            mb_strlen($subject) > 255 || $maxAttempts < 1 || $maxAttempts > 20) {
            throw new InvalidArgumentException('Dados inválidos para enfileiramento.');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO mail_queue
            (recipient_email, recipient_name, subject, body_html, body_text, max_attempts)
            VALUES (:recipient, :name, :subject, :html, :text, :max_attempts)"
        );
        $stmt->execute([
            'recipient' => $recipient,
            'name' => $recipientName,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'max_attempts' => $maxAttempts,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Processa até $limit mensagens. Um lease vencido pode ser retomado.
     * O timeout SMTP deve ser menor que o lease (atualmente 15s versus 120s).
     */
    public function work(int $limit = 10): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Limite deve estar entre 1 e 100.');
        }

        $stats = ['sent' => 0, 'retried' => 0, 'failed' => 0];
        for ($i = 0; $i < $limit; $i++) {
            $job = $this->claim();
            if ($job === null) {
                break;
            }

            $id = (int) $job['id'];
            $attempt = (int) $job['attempts'];
            $max = (int) $job['max_attempts'];

            try {
                $this->transport->send(
                    (string) $job['recipient_email'],
                    (string) $job['subject'],
                    (string) $job['body_html'],
                    (string) $job['body_text'],
                    (string) $job['recipient_name']
                );
                $stmt = $this->db->prepare(
                    "UPDATE mail_queue SET status='sent', sent_at=UTC_TIMESTAMP(),
                     locked_until=NULL, last_error=NULL
                     WHERE id=:id AND status='processing' AND attempts=:attempt"
                );
                $stmt->execute(['id' => $id, 'attempt' => $attempt]);
                $stats['sent']++;
            } catch (Throwable $exception) {
                $terminal = $attempt >= $max;
                $delay = min(3600, 60 * (2 ** min($attempt - 1, 6)));
                $stmt = $this->db->prepare(
                    "UPDATE mail_queue
                     SET status=:status, locked_until=NULL,
                         available_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL :delay SECOND),
                         last_error='Falha no transporte SMTP'
                     WHERE id=:id AND status='processing' AND attempts=:attempt"
                );
                $stmt->bindValue(':status', $terminal ? 'failed' : 'pending');
                $stmt->bindValue(':delay', $delay, PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->bindValue(':attempt', $attempt, PDO::PARAM_INT);
                $stmt->execute();
                $stats[$terminal ? 'failed' : 'retried']++;
                error_log('Realizze mail queue: SMTP delivery failed for job ' . $id);
            }
        }
        return $stats;
    }

    private function claim(): ?array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->query(
                "SELECT * FROM mail_queue
                 WHERE (status='pending' AND available_at <= UTC_TIMESTAMP())
                    OR (status='processing' AND locked_until < UTC_TIMESTAMP())
                 ORDER BY available_at, id LIMIT 1 FOR UPDATE SKIP LOCKED"
            );
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($job === false) {
                $this->db->commit();
                return null;
            }
            if ((int) $job['attempts'] >= (int) $job['max_attempts']) {
                $stmt = $this->db->prepare(
                    "UPDATE mail_queue SET status='failed', locked_until=NULL WHERE id=:id"
                );
                $stmt->execute(['id' => $job['id']]);
                $this->db->commit();
                return $this->claim();
            }
            $stmt = $this->db->prepare(
                "UPDATE mail_queue SET status='processing', attempts=attempts+1,
                 locked_until=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 120 SECOND)
                 WHERE id=:id"
            );
            $stmt->execute(['id' => $job['id']]);
            $job['attempts'] = (int) $job['attempts'] + 1;
            $this->db->commit();
            return $job;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
