# Auditoria pública de referência — Contas Online

Data: 2026-10-28.

## Limites da análise

A análise utilizou somente o site e a Central de Ajuda publicamente
acessíveis. Nenhuma autenticação, conta de teste, área restrita, código,
imagem ou ativo proprietário foi copiado. As conclusões visuais detalhadas sobre
o painel interno permanecem limitadas ao que a documentação pública descreve.

## Recursos observados

| Recurso | Classificação | Evidência pública | Adaptação Realizze |
|---|---|---|---|
| Visão geral com saldos, receitas, despesas, vencidos e a vencer | Confirmado | Central de Ajuda: Visão Geral | Manter resumo escaneável e valores honestos quando não houver integração. |
| Saldos de contas e faturas de cartões | Confirmado | Configurações iniciais / Visão Geral | Evoluir os cards existentes sem misturar saldo disponível e patrimônio. |
| Categorias hierárquicas, cores e padrões | Confirmado | Cadastrar e organizar categorias | Usar hierarquia progressiva e evitar excesso de informação no primeiro nível. |
| Contas financeiras, cartões, clientes e fornecedores | Confirmado | Configurações iniciais | Preservar separação PF/PJ e controles de permissão. |
| Receitas, despesas, transferências e recorrências | Confirmado | Configurações iniciais | Organizar a navegação por tarefa, com formulários progressivos. |
| Pesquisa, filtros simples e filtros avançados | Confirmado | Filtros de lançamentos | Manter busca imediata e revelar combinações avançadas sob demanda. |
| Importação OFX/TXT/CSV e exportação | Confirmado | Configurações iniciais | Planejar fluxo de revisão antes de qualquer gravação. |
| Fluxo de caixa e saldo detalhado | Confirmado | Relatório de saldo detalhado | Permitir detalhamento dos totais somente quando houver origem auditável. |
| Perfis pessoais e empresariais isolados | Confirmado | Perfis de acesso | Reforça o modelo PF/PJ já protegido no Realizze. |
| Assistente financeiro por WhatsApp | Proposta nova | Não confirmado nas fontes consultadas | Prévia desacoplada, com confirmação humana e sem integração ativa. |
| Modo escuro | Proposta nova | Não confirmado nas fontes consultadas | Evolução do Design System Realizze, respeitando contraste e preferência. |

## Técnicas adaptáveis

1. Resumo financeiro centralizado, com acesso aos detalhes a partir do total.
2. Ações frequentes próximas ao contexto em que o usuário trabalha.
3. Filtros rápidos visíveis e filtros avançados em divulgação progressiva.
4. Hierarquia clara entre navegação, indicadores, exceções e tabelas.
5. Personalização do resumo sem obrigar o usuário a consumir todos os dados.
6. Detalhamento rastreável de indicadores, sem números inventados.

## O que não deve ser copiado

Identidade, paleta, textos, disposição integral das telas, imagens, logotipos,
ícones proprietários ou implementação da referência. O Realizze mantém sua
marca verde, linguagem visual, arquitetura MovesCode e seus contratos de
segurança.

## Fontes públicas

- https://www.contasonline.com.br/ajuda/configuracoes-iniciais
- https://www.contasonline.com.br/ajuda/tudo-sobre-visao-geral
- https://www.contasonline.com.br/ajuda/filtros-de-lancamentos
- https://www.contasonline.com.br/ajuda/como-utilizar-relatorio-de-saldo-detalhado
- https://www.contasonline.com.br/ajuda/cadastrar-organizar-categorias
- https://www.contasonline.com.br/ajuda/perfis-de-acesso
