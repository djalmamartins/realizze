<?php
declare(strict_types=1);
// UI-001: demonstração visual. Nenhum valor financeiro representa dados do usuário.
$user = \Moves\Services\SessionAuth::current();
if ($user === null) {
    http_response_code(403);
    exit('Acesso negado.');
}
$name = htmlspecialchars((string) ($user['first_name'] ?? 'Cliente'), ENT_QUOTES, 'UTF-8');
$csrf = htmlspecialchars(\Moves\Services\Csrf::token(), ENT_QUOTES, 'UTF-8');
$css = '/container/app/realizze/v1/assets/css/dashboard.css';
$js = '/container/app/realizze/v1/assets/js/dashboard.js';
$items = [
    ['house','Visão Geral','#overview'],['arrow-down-left','Receber','#receber'],['arrow-up-right','Pagar','#pagar'],
    ['arrows-left-right','Movimentações','#movimentacoes'],['credit-card','Contas e Cartões','#contas'],
    ['chart-no-axes-column','Relatórios','#relatorios'],['calendar-days','Planejamento','#planejamento'],
    ['target','Metas','#metas'],['list-filter','Categoria','#categorias'],['settings','Configurações','#configuracoes']
];
$iconPaths = [
    'house' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    'arrow-down-left' => '<path d="M17 7 7 17M7 7v10h10"/>',
    'arrow-up-right' => '<path d="M7 17 17 7M7 7h10v10"/>',
    'arrows-left-right' => '<path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4"/>',
    'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'chart-no-axes-column' => '<path d="M5 20v-6M10 20V9M15 20V4M20 20v-9"/>',
    'calendar-days' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    'list-filter' => '<path d="M4 7h16M7 12h10M10 17h4"/>',
    'settings' => '<path d="M12 2v3M12 19v3M4.93 4.93l2.12 2.12m9.9 9.9 2.12 2.12M2 12h3m14 0h3M4.93 19.07l2.12-2.12m9.9-9.9 2.12-2.12"/><circle cx="12" cy="12" r="4"/>',
];
$icon = static function (string $name) use ($iconPaths): string {
    $paths = $iconPaths[$name] ?? '';
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};
$transactions = [
    ['↓','Recebimento de Aluguel','Apartamento 101','+ R$ 2.000,00','Hoje',true],
    ['↗','Pagamento Celular','Vivo Pós','− R$ 150,00','Hoje',false],
    ['↓','Transferência recebida','De: Cliente A','+ R$ 850,00','Ontem',true],
    ['↗','Pagamento Energia','Enel','− R$ 320,00','14/08',false],
    ['↓','Recebimento Salário','Empresa Ltda','+ R$ 8.500,00','13/08',true],
];
$accounts = [
    ['nu','Nubank','Conta Corrente','R$ 2.800,00','#8022bc'],
    ['inter','Inter','Conta Corrente','R$ 4.200,00','#ff7e00'],
    ['▣','Carteira','Dinheiro','R$ 1.150,00','#0a9c59'],
    ['VISA','Visa Final 4242','Cartão de Crédito','− R$ 2.430,00','#1754cf'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#079550">\n<link rel="icon" type="image/svg+xml" href="/assets/realizze/v1/images/favicon.svg">
<title>Visão Geral | Realizze</title>
<style><?= file_get_contents(dirname(__DIR__) . '/assets/css/dashboard.css') ?></style>
<script defer><?= file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js') ?></script>
</head>
<body>
<div class="shell" id="shell">
<aside class="sidebar" aria-label="Navegação principal">
<a href="/minha-conta" class="brand" aria-label="Realizze"><img src="/assets/realizze/v1/images/new-logo-realizze.svg" alt="Realizze" width="152" height="38"></a>
<nav class="nav" aria-label="Menu principal">
<?php foreach ($items as $index => $item): ?>
<a href="<?= $item[2] ?>" <?= $index === 0 ? 'class="active" aria-current="page"' : 'data-demo="true"' ?>><span class="symbol" aria-hidden="true"><?= $icon($item[0]) ?></span><?= htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
</nav>
<div class="sidebar-bottom">
<div class="connect"><strong>Conecte seu banco</strong><p>Importe suas transações e tenha tudo em um só lugar.</p><button type="button" data-demo="true">Conectar agora</button></div>
<div class="profile"><span class="avatar"><?= htmlspecialchars(strtoupper(substr((string)($user['first_name'] ?? 'R'),0,1)),ENT_QUOTES,'UTF-8') ?></span><div><strong><?= $name ?></strong><small>Minha conta</small></div></div>
<div class="sidebar-actions"><button type="button" id="theme-toggle" aria-label="Alternar tema">☾</button><button type="button" data-demo="true" aria-label="Ajuda">ⓘ</button></div>
<form method="post" action="/sair"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" style="border:0;background:transparent;width:100%;padding:10px">⇥ Sair</button></form>
</div>
</aside>
<main class="main" id="overview">
<header class="top">
<div style="display:flex;align-items:center;gap:12px"><button class="sidebar-toggle" id="sidebar-toggle" aria-label="Abrir menu" aria-expanded="false">☰</button><div><h1>Bom dia, <?= $name ?>! 👋</h1><p>Aqui está o resumo de <strong id="month-label">Agosto de 2026</strong>.</p></div></div>
<div class="toolbar">
<div class="months"><button type="button" id="prev-month" aria-label="Mês anterior">‹</button><button type="button" id="previous-month-label">Julho</button><button type="button" class="selected" id="current-month-label">Agosto 2026</button><button type="button" id="next-month" aria-label="Próximo mês">›</button></div>
<div class="period"><button type="button" class="selected" data-period="mensal">Mensal</button><button type="button" data-period="anual">Anual</button></div>
<button class="bell" type="button" data-demo="true" aria-label="Notificações">♧<span class="dot">3</span></button><span class="avatar" aria-label="Perfil"><?= htmlspecialchars(strtoupper(substr((string)($user['first_name'] ?? 'R'),0,1)),ENT_QUOTES,'UTF-8') ?></span>
</div>
</header>
<section aria-label="Indicadores financeiros" class="cards">
<article class="card balance"><span class="label">Saldo disponível ◉</span><div class="value">R$ 17.992,37</div><span class="muted">Em contas e carteiras</span></article>
<article class="card"><div class="stat"><span class="stat-icon">⌁</span><div><span class="label">Resultado de agosto</span><div class="value green">R$ 17.992,37</div><span class="muted">Receitas − Despesas</span><div><span class="pill">↗ 12,3% vs julho</span></div></div></div></article>
<article class="card" id="receber"><div class="stat"><span class="stat-icon">⇩</span><div><span class="label">A receber</span><div class="value green">R$ 15.320,00</div><span class="muted">Previsto até 31/08</span><div><span class="pill">3 títulos</span></div></div></div></article>
<article class="card" id="pagar"><div class="stat"><span class="stat-icon red-icon">↗</span><div><span class="label">A pagar</span><div class="value red">R$ 11.870,00</div><span class="muted">Previsto até 31/08</span><div><span class="pill danger">7 títulos</span></div></div></div></article>
</section>
<section class="card progress-panel" aria-label="Progresso do mês"><strong>Agosto: 58% concluído</strong><div class="track"><div class="fill" style="width:58%">58%</div></div><span class="muted">17 dias restantes</span></section>
<section class="card due" aria-label="Próximos vencimentos"><h2 class="section-title">Próximos vencimentos</h2><div class="due-grid">
<?php foreach ([['Hoje','R$ 470,00','2 títulos'],['Amanhã','R$ 150,00','1 título'],['Próximos 7 dias','R$ 1.525,00','3 títulos'],['Restante do mês','R$ 9.725,00','7 títulos']] as $due): ?>
<div class="due-item"><span class="stat-icon">▣</span><div><small><?= $due[0] ?></small><strong><?= $due[1] ?></strong><span class="muted"><?= $due[2] ?></span></div></div>
<?php endforeach; ?>
<div class="forecast"><span class="green">Fechamento previsto ⓘ</span><p class="muted">Mantendo o previsto, você encerra o mês com</p><strong>R$ 19.050,00</strong></div>
</div></section>
<section class="content-grid">
<article class="card" id="movimentacoes"><div class="section-head"><h2>Últimas movimentações</h2><a href="#movimentacoes" data-demo="true">Ver todas</a></div>
<?php foreach ($transactions as $t): ?><div class="transaction"><span class="mini-icon <?= $t[5]?'':'out' ?>"><?= $t[0] ?></span><div class="details"><strong><?= $t[1] ?></strong><small><?= $t[2] ?></small></div><div class="amount <?= $t[5]?'green':'red' ?>"><?= $t[3] ?><small><?= $t[4] ?></small></div></div><?php endforeach; ?>
</article>
<article class="card" id="categorias"><div class="section-head"><h2>Gastos por categoria</h2><span class="muted">Este mês ▾</span></div><div class="chart-area"><div class="donut" role="img" aria-label="Gráfico demonstrativo de gastos por categoria"></div><div class="legend"><?php foreach ([['Moradia','32%'],['Alimentação','21%'],['Transporte','14%'],['Lazer','10%'],['Saúde','8%'],['Outros','15%']] as $category): ?><div><b><?= $category[0] ?></b><span><?= $category[1] ?></span></div><?php endforeach; ?></div></div><a href="#relatorios" data-demo="true" style="display:block;text-align:center;color:#008f4c;font-size:12px">Ver relatório completo →</a></article>
<article class="card" id="contas"><div class="section-head"><h2>Contas e cartões</h2><a href="#contas" data-demo="true">Ver todas</a></div><?php foreach ($accounts as $a): ?><div class="account"><span class="bank" style="background:<?= $a[4] ?>"><?= $a[0] ?></span><div class="details"><strong><?= $a[1] ?></strong><small><?= $a[2] ?></small></div><span class="amount <?= str_starts_with($a[3],'−')?'red':'green' ?>"><?= $a[3] ?></span></div><?php endforeach; ?></article>
</section>
<section class="card insights"><span class="stat-icon">☼</span><div><strong>Insights do mês</strong><p>Você gastou R$ 680,00 a mais com alimentação neste mês comparado a julho.</p></div><button type="button" data-demo="true">Ver mais insights</button></section>
<p class="muted" style="text-align:center;margin-top:20px">Prévia de interface · Valores ilustrativos · Nenhuma movimentação real é exibida</p>
</main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>