<?php
declare(strict_types=1);

/** Progresso das metas: percentual, falta e ritmo mensal. Não acessa o banco. */
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Services\Finance;

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException('FALHOU: ' . $message);
    }
    echo "OK - {$message}\n";
};

$open = Finance::goalProgress(100000, 25000, '2026-12-31', '2026-11-01');
$check($open['percent'] === 25 && $open['remaining_cents'] === 75000, 'percentual e quanto falta');
$check($open['months_left'] === 2 && $open['monthly_cents'] === 37500, 'ritmo mensal conta o mês atual');
$check(Finance::goalProgress(100000, 99999, null, '2026-11-01')['percent'] === 99, 'só mostra 100% quando completa');
$check(Finance::goalProgress(100000, 0, null, '2026-11-01')['monthly_cents'] === null, 'sem prazo não sugere ritmo');
$done = Finance::goalProgress(100000, 120000, '2020-01-01', '2026-11-01');
$check($done['done'] && !$done['late'] && $done['percent'] === 100 && $done['remaining_cents'] === 0, 'meta alcançada não fica atrasada');
$late = Finance::goalProgress(100000, 1000, '2026-10-31', '2026-11-01');
$check($late['late'] && $late['monthly_cents'] === null, 'prazo vencido não sugere ritmo');
$check(Finance::goalProgress(100000, 0, '2026-11-01', '2026-11-01')['monthly_cents'] === 100000, 'prazo no mês atual pede o valor todo');

$check(Finance::goalTerm('2027-11-01', '2026-11-01') === 'curto' && Finance::goalTerm('2027-11-02', '2026-11-01') === 'medio', 'curto prazo vai até 1 ano');
$check(Finance::goalTerm('2029-11-01', '2026-11-01') === 'medio' && Finance::goalTerm('2029-11-02', '2026-11-01') === 'longo', 'médio prazo vai até 3 anos');
$check(Finance::goalTerm(null, '2026-11-01') === null && Finance::goalTerm('2020-01-01', '2026-11-01') === 'curto', 'sem prazo e prazo vencido');

$aporte = static fn (int $cents, string $date): array => ['amount_cents' => $cents, 'contributed_at' => $date];
$forecast = static fn (int $target, int $saved, ?string $deadline, ?int $plan, array $list = [], int $free = 0): array => Finance::goalForecast($target, $saved, $deadline, $plan, $list, $free, '2026-11-01');

$f = $forecast(100000, 0, '2027-06-30', 10000);
$check($f['pace_cents'] === 10000 && $f['months'] === 10 && $f['finish'] === '2027-08-01' && $f['title'] === 'Acelere um pouco', 'contribuição planejada que não chega no prazo');
$check(str_contains($f['text'], '25,00'), 'quanto falta aumentar por mês: 100000/8 - 10000');
$f = $forecast(120000, 0, '2027-12-31', null, [$aporte(90000, '2026-10-10')]);
$check($f['pace_cents'] === 30000 && $f['months'] === 4 && $f['title'] === 'No ritmo certo!' && str_contains($f['text'], '10 meses'), 'aportes recentes têm prioridade e dão meses de folga');
$f = $forecast(120000, 0, '2027-12-31', 5000, [$aporte(90000, '2026-01-10')]);
$check($f['pace_cents'] === 5000, 'aporte antigo não conta no ritmo');
$f = $forecast(120000, 0, null, 10000, [], 10000);
$check($f['title'] === 'Você pode antecipar!' && str_contains($f['text'], '6 meses'), 'sobra real permite antecipar');
$check($forecast(120000, 0, null, 10000)['title'] === 'No caminho certo', 'sem sobra, não sugere antecipar');
$check($forecast(120000, 0, '2027-12-31', null)['title'] === 'Comece agora', 'sem ritmo e sem aportes');
$check($forecast(120000, 100, null, null)['title'] === 'Planeje o futuro' && $forecast(120000, 100, null, null)['months'] === null, 'sem ritmo não inventa previsão');
$check($forecast(1000, 1000, '2020-01-01', null)['title'] === 'Meta alcançada!', 'alcançada vale mesmo com prazo vencido');
$check($forecast(1000, 0, '2026-10-31', 100)['title'] === 'Prazo encerrado', 'prazo encerrado');
$check(Finance::goalTrend(30000, [$aporte(10000, '2026-10-05'), $aporte(5000, '2026-11-01')], '2026-11-01', 3) === [15000, 25000, 30000], 'evolução parte dos aportes reais');
