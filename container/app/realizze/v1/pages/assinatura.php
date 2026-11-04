<?php
declare(strict_types=1);
use Moves\Services\OrganizationContext; use Moves\Services\PlatformBilling; use Moves\Services\SessionAuth; use Moves\Support\CmsView as V;
require dirname(__DIR__) . '/components/support-shell.php';
$organization = OrganizationContext::current();
$contract = PlatformBilling::currentContract((int) SessionAuth::current()['id'], $organization === null ? null : (int) $organization['id']);
// Valor contratado da empresa é visível ao responsável; demais membros veem plano e limites.
$showAmount = $organization === null || ($organization['role'] ?? '') === 'owner';
$supportOpen('Minha assinatura'); ?>
<section class="sp-card"><?php if ($contract === null): ?><div class="sp-empty"><strong>Sem assinatura registrada<?= $organization !== null ? ' para esta empresa' : '' ?>.</strong><p>Fale com a equipe para conhecer os planos.</p><a class="sp-btn sp-btn-primary" href="/suporte/novo">Falar com a equipe</a></div><?php else: ?>
<h2><?= V::e($contract['plan_name']) ?></h2><dl class="sp-dl"><dt>Situação</dt><dd><?= V::e(V::SUB_STATUS[$contract['status']] ?? $contract['status']) ?></dd><dt>Ciclo</dt><dd><?= $contract['billing_cycle'] === 'annual' ? 'Anual' : 'Mensal' ?></dd><?php if ($showAmount): ?><dt>Valor contratado</dt><dd><?= V::e(V::money($contract['contracted_amount'], $contract['currency'])) ?></dd><?php endif; ?><dt>Renovação</dt><dd><?= V::date($contract['renews_at'], false) ?></dd><dt>Empresas</dt><dd><?= $contract['max_companies'] === null ? 'Sem limite' : (int) $contract['max_companies'] ?></dd><dt>Usuários</dt><dd><?= $contract['max_users'] === null ? 'Sem limite' : (int) $contract['max_users'] ?></dd></dl>
<?php if ($contract['features'] !== []): ?><h3>Recursos habilitados</h3><ul><?php foreach ($contract['features'] as $feature): ?><li><?= V::e($feature) ?></li><?php endforeach; ?></ul><?php endif; ?><?php endif; ?></section>
<?php $supportClose();
