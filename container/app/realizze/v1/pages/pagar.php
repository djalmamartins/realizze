<?php /** Contas a pagar: dados reais do contexto financeiro atual. */ ?>
<?= $appView->render('components::finance-entries', ['kind' => 'expense', 'icon' => $icon, 'csrf' => $csrf ?? '']) ?>
