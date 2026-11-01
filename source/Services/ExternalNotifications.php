<?php
declare(strict_types=1);

namespace Moves\Services;

use RuntimeException;

/**
 * Fila de avisos externos. Só envia pelo processador explícito
 * (scripts/process-notifications.php), nunca durante a requisição do usuário.
 * Credenciais ficam no ambiente: TELEGRAM_BOT_TOKEN e TELEGRAM_CHAT_ID.
 */
final class ExternalNotifications
{
    private const MAX_ATTEMPTS = 5;
    private const PER_RUN_LIMIT = 15;

    public static function enqueue(string $channel, string $key, ?int $ticketId, array $payload): void
    {
        $settingKey = 'notify_' . $channel . '_enabled';
        if (!CmsSettings::enabled($settingKey)) {
            return;
        }
        CmsQuery::run(
            'INSERT IGNORE INTO notification_deliveries (channel,dedupe_key,ticket_id,payload_json,next_attempt_at,created_at) VALUES (?,?,?,?,NOW(),NOW())',
            [$channel, $key, $ticketId, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function telegramConfigured(): bool
    {
        return (string) getenv('TELEGRAM_BOT_TOKEN') !== '' && (string) getenv('TELEGRAM_CHAT_ID') !== '';
    }

    public static function emailConfigured(): bool
    {
        foreach (['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'] as $key) {
            if ((string) getenv($key) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, callable(array):void> $senders substitui transportes reais em testes
     * @return array{sent:int,failed:int,skipped:int}
     */
    public static function process(array $senders = []): array
    {
        $senders += ['telegram' => [self::class, 'sendTelegram'], 'email' => [self::class, 'sendEmail']];
        $totals = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $rows = CmsQuery::all("SELECT * FROM notification_deliveries WHERE status='pending' AND next_attempt_at <= NOW() ORDER BY id LIMIT " . self::PER_RUN_LIMIT);
        foreach ($rows as $row) {
            // Reivindica a linha: dois processadores não enviam o mesmo aviso.
            if (CmsQuery::run("UPDATE notification_deliveries SET attempts=attempts+1, next_attempt_at=NOW() + INTERVAL 5 MINUTE WHERE id=? AND status='pending' AND attempts=?", [$row['id'], $row['attempts']]) === 0) {
                continue;
            }
            $attempt = (int) $row['attempts'] + 1;
            if (!isset($senders[$row['channel']]) || !CmsSettings::enabled('notify_' . $row['channel'] . '_enabled')) {
                CmsQuery::run("UPDATE notification_deliveries SET status='skipped', last_error='Canal desativado' WHERE id=?", [$row['id']]);
                $totals['skipped']++;
                continue;
            }
            try {
                ($senders[$row['channel']])(json_decode($row['payload_json'], true, 8, JSON_THROW_ON_ERROR));
                CmsQuery::run("UPDATE notification_deliveries SET status='sent', sent_at=NOW(), last_error=NULL WHERE id=?", [$row['id']]);
                $totals['sent']++;
            } catch (\Throwable $error) {
                $final = $attempt >= self::MAX_ATTEMPTS;
                CmsQuery::run(
                    'UPDATE notification_deliveries SET status=?, last_error=?, next_attempt_at=NOW() + INTERVAL ? MINUTE WHERE id=?',
                    [$final ? 'failed' : 'pending', mb_substr(self::redact($error->getMessage()), 0, 255), 2 ** $attempt, $row['id']]
                );
                $totals['failed']++;
            }
        }
        return $totals;
    }

    /** O token aparece na URL da Bot API: nunca pode chegar a logs ou ao banco. */
    public static function redact(string $message): string
    {
        $token = (string) getenv('TELEGRAM_BOT_TOKEN');
        return $token === '' ? $message : str_replace($token, '[token]', $message);
    }

    public static function sendTelegram(array $payload): void
    {
        if (!self::telegramConfigured()) {
            throw new RuntimeException('Telegram sem credenciais no ambiente.');
        }
        $curl = curl_init('https://api.telegram.org/bot' . getenv('TELEGRAM_BOT_TOKEN') . '/sendMessage');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id' => getenv('TELEGRAM_CHAT_ID'), 'text' => (string) ($payload['text'] ?? ''), 'disable_web_page_preview' => 'true']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $failure = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status !== 200) {
            throw new RuntimeException('Telegram recusou o envio (HTTP ' . $status . ') ' . $failure);
        }
    }

    public static function sendEmail(array $payload): void
    {
        $text = (string) ($payload['text'] ?? '');
        (new Mail())->send((string) $payload['to'], (string) $payload['subject'], nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')), $text, (string) ($payload['name'] ?? ''));
    }

    public static function recent(int $limit = 30): array
    {
        return CmsQuery::all('SELECT id,channel,dedupe_key,ticket_id,status,attempts,sent_at,last_error,created_at FROM notification_deliveries ORDER BY id DESC LIMIT ' . max(1, min($limit, 100)));
    }
}
