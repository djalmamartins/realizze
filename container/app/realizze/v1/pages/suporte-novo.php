<?php
declare(strict_types=1);
require dirname(__DIR__) . '/components/support-shell.php';
$supportOld = $_SESSION['support_old'] ?? null; unset($_SESSION['support_old']);
$supportOpen('Abrir chamado'); ?>
<section class="sp-card sp-narrow"><?php $formId = 'sp-page'; require dirname(__DIR__) . '/components/support-form.php'; ?></section>
<?php $supportClose();
