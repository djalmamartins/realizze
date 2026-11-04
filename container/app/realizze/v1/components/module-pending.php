<?php
/** Módulo empresarial ainda sem backend: diz o que virá e o que falta, sem dados simulados. */
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$modules = [
    'dre' => ['Ver o resultado da empresa por grupos de receita e despesa, com critério explícito.', ['Receita, custos, despesas e resultado por período', 'Comparação entre períodos', 'Exportação'], 'Falta definir como cada categoria entra no resultado. A DRE gerencial não substitui a demonstração contábil oficial.'],
    'obrigacoes' => ['Acompanhar compromissos conforme o enquadramento da empresa.', ['Calendário de obrigações', 'Documentos e comprovantes', 'Alertas e histórico'], 'O enquadramento da empresa ainda não é registrado. Não haverá cálculo de tributos nem emissão fiscal.'],
    'ordens-de-servico' => ['Módulo opcional para organizar serviços, materiais e cobranças vinculadas.', ['Lista com situação, responsável e valor', 'Nova ordem em etapas', 'Checklist, anexos e linha do tempo', 'Cobrança gerada em Receber, sem lançamento duplicado'], 'As ordens de serviço e a habilitação do módulo por empresa ainda não existem.'],
];
[$lead, $planned, $missing] = $modules[$section] ?? ['', [], ''];
?>
<section class="card module-pending" aria-labelledby="module-pending-title">
  <div>
    <span class="preview-chip">Em breve</span>
    <h2 id="module-pending-title" class="section-title"><?= $e($title ?? '') ?></h2>
    <p class="muted"><?= $e($lead) ?></p>
  </div>
  <div>
    <strong>O que este módulo vai oferecer</strong>
    <ul><?php foreach ($planned as $item): ?><li><?= $e($item) ?></li><?php endforeach; ?></ul>
  </div>
  <p class="module-pending-note"><?= $e($missing) ?></p>
</section>
