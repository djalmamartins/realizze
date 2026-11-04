<?php
/**
 * Selo da instituição: logo aprovado quando disponível, senão iniciais coloridas.
 * Parâmetros: $name (texto exibido), $logo (logo_file do catálogo ou null), $slug (opcional),
 * $type (opcional; 'wallet' mostra o ícone de carteira quando não há logo).
 */
use Moves\Services\FinancialInstitutions;

$badgeName = trim((string) ($name ?? ''));
$badgeUrl = FinancialInstitutions::logoUrl(!empty($logo) ? (string) $logo : FinancialInstitutions::logoFileForName($badgeName));
$badgeHue = FinancialInstitutions::hue((string) (($slug ?? '') ?: $badgeName));
$badgeEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if ($badgeUrl !== null): ?>
<span class="bank-badge bank-badge-logo"><img src="<?= $badgeEscape($badgeUrl) ?>" alt="<?= $badgeEscape($badgeName) ?>" width="28" height="28" loading="lazy" decoding="async"></span>
<?php elseif (($type ?? null) === 'wallet'): ?>
<span class="bank-badge bank-badge-wallet" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h12v4"/><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 13h2"/></svg></span>
<?php else: ?>
<span class="bank-badge bank-badge-fallback" style="--bank-hue: <?= (int) $badgeHue ?>" aria-hidden="true"><?= $badgeEscape(FinancialInstitutions::initials($badgeName !== '' ? $badgeName : '?')) ?></span>
<?php endif; ?>
