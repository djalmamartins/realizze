<?php
declare(strict_types=1);
/**
 * Indicador exclusivamente visual da área em prévia.
 * Não consulta nem altera o contexto autorizado da sessão.
 */
$isEnterprise = ($contextType ?? 'personal') === 'enterprise';
?>
<span class="app-context-badge <?= $isEnterprise ? 'app-context-badge--enterprise' : 'app-context-badge--personal' ?>" aria-label="<?= $isEnterprise ? 'Área empresarial (prévia)' : 'Área pessoal (prévia)' ?>"><?= $isEnterprise ? 'Empresarial · MEI/PJ' : 'Pessoal · PF' ?></span>
