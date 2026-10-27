<?php
declare(strict_types=1);

namespace Moves\Support;

use RuntimeException;

/**
 * Mensagens de interface independentes de temas.
 * O conteúdo é tratado como texto, nunca como HTML confiável.
 */
final class Message
{
    private string $text = '';
    private string $type = 'info';
    private string $before = '';
    private string $after = '';

    public function __toString(): string
    {
        return $this->render();
    }

    public function getText(): string
    {
        return $this->before . $this->text . $this->after;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function before(string $text): self
    {
        $this->before = $this->escape($text);
        return $this;
    }

    public function after(string $text): self
    {
        $this->after = $this->escape($text);
        return $this;
    }

    public function info(string $message): self
    {
        return $this->set('info', $message);
    }

    public function success(string $message): self
    {
        return $this->set('success', $message);
    }

    public function warning(string $message): self
    {
        return $this->set('warning', $message);
    }

    public function error(string $message): self
    {
        return $this->set('error', $message);
    }

    public function render(): string
    {
        return '<div class="message ' . $this->type . '" role="alert">'
            . $this->getText() . '</div>';
    }

    /**
     * Armazena somente dados simples na sessão (sem serializar objetos).
     * A sessão deve ter sido iniciada pelo bootstrap/autenticação.
     */
    public function flash(): void
    {
        $this->requireSession();
        $_SESSION['flash'] = [
            'type' => $this->type,
            'text' => $this->text,
            'before' => $this->before,
            'after' => $this->after,
        ];
    }

    /**
     * Lê e remove a mensagem flash, se existir.
     */
    public static function consumeFlash(): ?self
    {
        self::requireSession();
        $data = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        if (!is_array($data) ||
            !isset($data['type'], $data['text'], $data['before'], $data['after']) ||
            !in_array($data['type'], ['info', 'success', 'warning', 'error'], true)) {
            return null;
        }
        foreach (['text', 'before', 'after'] as $key) {
            if (!is_string($data[$key])) {
                return null;
            }
        }
        $message = new self();
        $message->type = $data['type'];
        $message->text = $data['text'];
        $message->before = $data['before'];
        $message->after = $data['after'];
        return $message;
    }

    private function set(string $type, string $message): self
    {
        $this->type = $type;
        $this->text = $this->escape($message);
        return $this;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A sessão deve estar ativa para utilizar mensagens flash.');
        }
    }
}
