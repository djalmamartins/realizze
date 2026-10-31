<?php
declare(strict_types=1);

/**
 * Prévia reutilizável de etapas editoriais. Não consulta estado, banco ou permissões.
 * Todos os passos permanecem planejados; nenhuma ação é habilitada.
 */
$workflow = is_array($cmsWorkflow ?? null) ? $cmsWorkflow : [];
$steps = $workflow['steps'] ?? [];
if (!is_array($steps) || $steps === []) {
    throw new InvalidArgumentException('A prévia de fluxo exige etapas.');
}
$escapeWorkflow = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="cms-card cms-workflow-preview" aria-labelledby="cms-workflow-title">
  <div class="cms-module-head">
    <div>
      <h2 id="cms-workflow-title"><?= $escapeWorkflow($workflow['title'] ?? 'Etapas previstas') ?></h2>
      <p>Fluxo ilustrativo, ainda não implementado. Nenhuma etapa foi executada.</p>
    </div>
    <span class="cms-status">Todas as etapas pendentes</span>
  </div>
  <ol class="cms-workflow-steps">
    <?php foreach ($steps as $step):
        if (!is_array($step)) {
            throw new InvalidArgumentException('Etapa inválida na prévia.');
        }
    ?>
    <li>
      <div>
        <strong><?= $escapeWorkflow($step['title'] ?? '') ?></strong>
        <p><?= $escapeWorkflow($step['description'] ?? '') ?></p>
      </div>
      <span class="cms-workflow-state">Pendente</span>
    </li>
    <?php endforeach; ?>
  </ol>
</section>
