<?php
declare(strict_types=1);

namespace Moves\Support;

use InvalidArgumentException;
use Moves\Services\Mail;
use Moves\Services\MailQueue;
use PDO;
use RuntimeException;

/**
 * Mensagem reutilizável para os produtos Moves.
 * O transporte SMTP fica isolado em Services/Mail.
 */
final class Email
{
    private string $subject = '';
    private string $html = '';
    private string $text = '';
    private string $recipient = '';
    private string $recipientName = '';
    /** @var array<int, array{path:string,name:string}> */
    private array $attachments = [];

    public function __construct(private readonly Mail $transport = new Mail())
    {
    }

    public function bootstrap(string $subject, string $body, string $recipient, string $recipientName = ''): self
    {
        if (trim($subject) === '' || trim($body) === '') {
            throw new InvalidArgumentException('Assunto e conteúdo são obrigatórios.');
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Destinatário inválido.');
        }
        $this->subject = $subject;
        $this->html = $body;
        $this->text = '';
        $this->recipient = $recipient;
        $this->recipientName = $recipientName;
        $this->attachments = [];
        return $this;
    }

    public function text(string $plainText): self
    {
        $this->text = $plainText;
        return $this;
    }

    public function attach(string $filePath, string $fileName = ''): self
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException('Anexo inexistente ou inacessível.');
        }
        $this->attachments[] = [
            'path' => $filePath,
            'name' => $fileName !== '' ? basename($fileName) : basename($filePath),
        ];
        return $this;
    }

    /**
     * Enfileira a mensagem para processamento posterior.
     * Anexos exigem armazenamento privado persistente (CORE-003).
     */
    public function queue(PDO $db): int
    {
        if ($this->recipient === '') {
            throw new RuntimeException('Prepare a mensagem antes de enfileirar.');
        }
        if ($this->attachments !== []) {
            throw new RuntimeException('Fila com anexos ainda não suportada. Use envio imediato.');
        }
        return (new MailQueue($db))->enqueue(
            $this->recipient,
            $this->subject,
            $this->html,
            $this->text,
            $this->recipientName
        );
    }

    public function send(): void
    {
        if ($this->recipient === '') {
            throw new RuntimeException('Prepare a mensagem antes do envio.');
        }
        $this->transport->send(
            $this->recipient,
            $this->subject,
            $this->html,
            $this->text,
            $this->recipientName,
            $this->attachments
        );
    }
}
