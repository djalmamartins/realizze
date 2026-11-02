<?php
/**
 * Aviso de conta com menos saldo que o reservado em metas. A carteira não muda sozinha: o usuário decide o que resgatar.
 * Parâmetros: $shortfalls (Finance::balanceSummary), $e.
 */
use Moves\Services\AppUrl;
use Moves\Services\Finance;
?>
<?php foreach ($shortfalls as $short): ?>
<p class="finance-flash finance-flash-warning" role="status"><?= $e($short['name']) ?> tem <?= $e(Finance::money($short['cents'])) ?> a menos que o reservado em metas. <a href="<?= $e(AppUrl::section('metas')) ?>">Resgate ou ajuste as reservas</a>.</p>
<?php endforeach; ?>
