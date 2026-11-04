<?php
/** Módulo empresarial ainda sem backend: diz o que virá e o que falta, sem dados simulados. */
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$modules = [
    'faturas' => ['Acompanhar limite, fatura aberta e fechada, compras, parcelas e pagamento de cada cartão, sem pagamento bancário automático.', ['Resumo do cartão, com instituição, bandeira, final e limites', 'Fatura atual, lançamentos e parcelas', 'Gastos por categoria', 'Registro de compra e de pagamento da fatura'], 'O cálculo de faturas e parcelas ainda não existe. Por enquanto, cadastre os cartões em Bancos e contas.'],
    'conciliacao' => ['Conferir o extrato do banco com os lançamentos da empresa, item a item.', ['Importação de arquivos OFX e CSV', 'Sugestão de correspondências para revisão', 'Divergências e possíveis duplicidades', 'Histórico de conciliações'], 'A importação de extratos ainda não existe. Nada será conciliado automaticamente.'],
    'centros-de-custo' => ['Separar receitas e despesas por área, projeto ou unidade.', ['Cadastro, edição e ativação', 'Vínculo com os lançamentos', 'Totais por período'], 'O cadastro de centros de custos e o vínculo com os lançamentos ainda não existem.'],
    'dre' => ['Ver o resultado da empresa por grupos de receita e despesa, com critério explícito.', ['Receita, custos, despesas e resultado por período', 'Comparação entre períodos', 'Exportação'], 'Falta definir como cada categoria entra no resultado. A DRE gerencial não substitui a demonstração contábil oficial.'],
    'obrigacoes' => ['Acompanhar compromissos conforme o enquadramento da empresa.', ['Calendário de obrigações', 'Documentos e comprovantes', 'Alertas e histórico'], 'O enquadramento da empresa ainda não é registrado. Não haverá cálculo de tributos nem emissão fiscal.'],
    'recorrencias' => ['Saber quanto a empresa gasta com despesas que se repetem.', ['Assinaturas ativas e canceladas', 'Próximos vencimentos', 'Custo mensal e anual', 'Possíveis duplicidades, só como sugestão de revisão'], 'Lançamentos recorrentes ainda não existem no financeiro.'],
    'ordens-de-servico' => ['Módulo opcional para organizar serviços, materiais e cobranças vinculadas.', ['Lista com situação, responsável e valor', 'Nova ordem em etapas', 'Checklist, anexos e linha do tempo', 'Cobrança gerada em Receber, sem lançamento duplicado'], 'As ordens de serviço e a habilitação do módulo por empresa ainda não existem.'],
    'permissoes' => ['Definir o que cada papel pode ver e fazer na empresa.', ['Proprietário, Administrador, Financeiro, Operacional, Contabilidade e Visualizador', 'Permissões por módulo e operação', 'Proteção contra elevação indevida de acesso'], 'Hoje só existe o papel de Proprietário.'],
    'seguranca' => ['Ver e controlar os acessos à empresa.', ['Senha e acesso', 'Autenticação em duas etapas', 'Sessões e dispositivos', 'Políticas de acesso'], 'Autenticação em duas etapas e controle de sessões ainda não existem.'],
    'auditoria' => ['Consultar quem fez o quê na empresa.', ['Ações administrativas, por pessoa e data', 'Filtros e exportação autorizada'], 'A consulta ao registro de ações da empresa ainda não existe.'],
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
