<?php
declare(strict_types=1);

namespace Moves\Services;

/** Acessos ao site, sem IP nem identificação: o hash muda a cada dia e não é reversível. */
final class CmsAnalytics
{
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|curl|wget|python|headless/i';

    public static function record(string $path): void
    {
        try {
            $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            if ($agent === '' || preg_match(self::BOTS, $agent) === 1 || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                return;
            }
            $secret = (string) getenv('DB_PASSWORD') . (string) getenv('APP_URL');
            $visitor = hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $agent . '|' . date('Y-m-d'), $secret);
            $session = hash('sha256', $visitor . '|' . (int) floor(time() / 1800));
            CmsQuery::run('INSERT INTO cms_page_views (path,visitor_hash,session_hash,viewed_at) VALUES (?,?,?,NOW())', [mb_substr($path, 0, 190), $visitor, $session]);
        } catch (\Throwable $error) {
            error_log('Realizze site: acesso não registrado.');
        }
    }

    public static function report(int $days = 30): array
    {
        $days = max(1, min($days, 365));
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $totals = CmsQuery::one('SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, COUNT(DISTINCT session_hash) AS sessions FROM cms_page_views WHERE viewed_at >= ?', [$since]);
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[date('Y-m-d', strtotime("-{$i} days"))] = 0;
        }
        foreach (CmsQuery::all('SELECT DATE(viewed_at) AS day, COUNT(*) AS total FROM cms_page_views WHERE viewed_at >= ? GROUP BY DATE(viewed_at)', [$since]) as $row) {
            $series[$row['day']] = (int) $row['total'];
        }
        $half = (int) floor($days / 2);
        $values = array_values($series);
        $first = array_sum(array_slice($values, 0, $days - $half));
        $second = array_sum(array_slice($values, $days - $half));
        return [
            'days' => $days,
            'views' => (int) $totals['views'], 'visitors' => (int) $totals['visitors'], 'sessions' => (int) $totals['sessions'],
            'series' => $series,
            'trend' => $first > 0 ? round(($second - $first) / $first * 100, 1) : null,
            'top' => CmsQuery::all('SELECT path, COUNT(*) AS total FROM cms_page_views WHERE viewed_at >= ? GROUP BY path ORDER BY total DESC LIMIT 10', [$since]),
            'online' => CmsQuery::all('SELECT path, MAX(viewed_at) AS last_seen FROM cms_page_views WHERE viewed_at >= NOW() - INTERVAL 10 MINUTE GROUP BY visitor_hash, path ORDER BY last_seen DESC LIMIT 20'),
            'online_count' => (int) CmsQuery::value('SELECT COUNT(DISTINCT visitor_hash) FROM cms_page_views WHERE viewed_at >= NOW() - INTERVAL 10 MINUTE'),
        ];
    }
}
