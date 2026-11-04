<?php
/** Gráfico de área de receitas e despesas pagas. O mês em andamento é o último ponto; meses futuros não são desenhados. */
use Moves\Services\Finance;

$chartEscape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$chartMonths = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
$chartSeries = $chartSeries ?? [];
$chartKeys = array_keys($chartSeries);
$chartCount = count($chartKeys);
$chartDrawn = array_values(array_filter($chartKeys, static fn (string $key): bool => $key <= date('Y-m')));
$chartPeak = 0;
foreach ($chartDrawn as $key) {
    $chartPeak = max($chartPeak, $chartSeries[$key]['income'], $chartSeries[$key]['expense']);
}
// Teto arredondado (1, 2, 2,5 ou 5 × 10ⁿ por divisão), para o eixo ter valores redondos.
$chartStep = 100000;
if ($chartPeak > 0) {
    $magnitude = 10 ** floor(log10($chartPeak / 4));
    foreach ([1, 2, 2.5, 5, 10] as $factor) {
        $chartStep = (int) ($factor * $magnitude);
        if ($chartStep * 4 >= $chartPeak) {
            break;
        }
    }
}
$chartTop = $chartStep * 4;
// Coordenadas em % da área do gráfico: a altura é fixa no CSS e a largura acompanha o card.
[$chartLeft, $chartRight, $chartHigh, $chartLow] = [0, 100, 4, 98];
$chartX = static fn (int $index): float => $chartLeft + ($chartCount > 1 ? $index * ($chartRight - $chartLeft) / ($chartCount - 1) : 0);
$chartY = static fn (int $cents): float => $chartLow - ($cents / $chartTop) * ($chartLow - $chartHigh);
$chartPoints = static function (string $kind) use ($chartDrawn, $chartKeys, $chartSeries, $chartX, $chartY): array {
    return array_map(static fn (string $key): array => [round($chartX((int) array_search($key, $chartKeys, true)), 1), round($chartY($chartSeries[$key][$kind]), 1), $key, $chartSeries[$key][$kind]], $chartDrawn);
};
$chartLine = static function (array $points, bool $smooth): string {
    $path = '';
    foreach ($points as $index => [$x, $y]) {
        if ($index === 0) {
            $path = "M{$x} {$y}";
        } elseif ($smooth) {
            [$lastX, $lastY] = $points[$index - 1];
            $middle = round(($lastX + $x) / 2, 1);
            $path .= " C{$middle} {$lastY} {$middle} {$y} {$x} {$y}";
        } else {
            $path .= " L{$x} {$y}";
        }
    }
    return $path;
};
$chartArea = static fn (array $points, string $line): string => $line . ' L' . end($points)[0] . " {$chartLow} L" . $points[0][0] . " {$chartLow} Z";
$chartLabel = static fn (int $cents): string => $cents === 0 ? '0' : ($cents >= 100000 ? rtrim(rtrim(number_format($cents / 100000, 1, ',', ''), '0'), ',') . 'k' : (string) ($cents / 100));
$income = $chartPoints('income');
$expense = $chartPoints('expense');
?>
<?php if ($chartPeak === 0): ?>
<p class="muted">Sem receitas ou despesas pagas neste período.</p>
<?php else: $incomeLine = $chartLine($income, false); $expenseLine = $chartLine($expense, true); ?>
<div class="area-chart" role="img" aria-label="Gráfico de receitas e despesas pagas por mês">
  <div class="area-plot">
    <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
      <defs>
        <linearGradient id="chart-income-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#078b4c" stop-opacity=".22"/><stop offset="1" stop-color="#078b4c" stop-opacity=".02"/></linearGradient>
        <linearGradient id="chart-expense-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e5484d" stop-opacity=".2"/><stop offset="1" stop-color="#e5484d" stop-opacity=".02"/></linearGradient>
      </defs>
      <?php for ($tick = 0; $tick <= 4; $tick++): $tickY = round($chartY($chartStep * $tick), 1); ?>
      <line class="area-grid" x1="0" x2="100" y1="<?= $tickY ?>" y2="<?= $tickY ?>"/>
      <?php endfor; ?>
      <g class="area-reveal">
        <path d="<?= $chartEscape($chartArea($income, $incomeLine)) ?>" fill="url(#chart-income-fill)"/>
        <path d="<?= $chartEscape($chartArea($expense, $expenseLine)) ?>" fill="url(#chart-expense-fill)"/>
        <path class="area-line is-income" d="<?= $chartEscape($incomeLine) ?>"/>
        <path class="area-line is-expense" d="<?= $chartEscape($expenseLine) ?>"/>
      </g>
    </svg>
    <?php for ($tick = 0; $tick <= 4; $tick++): ?>
    <span class="area-tick" style="top:<?= round($chartY($chartStep * $tick), 1) ?>%"><?= $chartEscape($chartLabel($chartStep * $tick)) ?></span>
    <?php endfor; ?>
    <?php foreach ([[$income, 'is-income', 'Receitas'], [$expense, 'is-expense', 'Despesas']] as [$points, $class, $name]): foreach ($points as [$x, $y, $key, $cents]): ?>
    <span class="area-point <?= $class ?>" style="left:<?= $x ?>%;top:<?= $y ?>%" title="<?= $chartEscape($name . ' de ' . $chartMonths[substr($key, 5)] . '/' . substr($key, 0, 4) . ': ' . Finance::money($cents)) ?>"></span>
    <?php endforeach; endforeach; ?>
  </div>
  <div class="area-months<?= $chartCount > 6 ? ' is-year' : '' ?>">
    <?php foreach ($chartKeys as $index => $key): ?>
    <span style="left:<?= round($chartX($index), 1) ?>%"><?= $chartEscape($chartMonths[substr($key, 5)]) ?></span>
    <?php endforeach; ?>
  </div>
</div>
<p class="bars-legend"><span class="finance-dot" style="background:#078b4c"></span>Receitas <span class="finance-dot" style="background:#e5484d;margin-left:12px"></span>Despesas</p>
<?php endif; ?>
