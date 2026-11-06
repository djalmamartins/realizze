# Roteiro de validação contábil — relatórios gerenciais

Status: **parecer externo pendente**.

Este roteiro prepara a revisão por profissional contábil habilitado. Ele não é
um parecer e não autoriza apresentar os relatórios do Realizze como documentos
contábeis, fiscais ou tributários oficiais.

## Evidências para a revisão

1. Acessar a Central no contexto de uma empresa de teste.
2. Selecionar um período com receitas, despesas, baixas parciais, cartão e transferências.
3. Exportar a metodologia no bloco “Metodologia e limites”.
4. Exportar Fluxo de Caixa, DRE gerencial, Faturamento, Pagar/Receber, Posição de Contas e Orçamento.
5. Usar as referências `LAN-*`, `OS-*` e `OB-*` para conferir uma amostra nas telas de origem.
6. Registrar abaixo o entendimento, ressalvas e redação aprovada.

Todas as evidências devem vir de banco de teste anonimizado. Não anexar senhas,
tokens, cartões, dados bancários completos ou informações financeiras de produção.

## Decisões que exigem validação profissional

| Tema | Implementação gerencial atual | Decisão esperada |
|---|---|---|
| DRE por competência | Reconhece o valor integral pela competência informada | Confirmar terminologia e limites de uso |
| DRE por caixa | Reconhece baixas na data efetiva, inclusive pagamentos de fatura alocados | Confirmar apresentação e nomenclatura |
| Grupos da DRE | Classificação explícita por categoria, com fallback documentado por prefixo | Aprovar, ajustar ou remover o fallback |
| Faturamento | Receitas de Contas a Receber por competência | Aprovar o termo “faturamento gerencial” ou indicar outro |
| Pagar/Receber | Saldo aberto é o valor menos baixas até a data-base | Confirmar leitura de parciais e vencidos |
| Fluxo de Caixa | Realizado por baixa; aberto por vencimento; transferências internas neutras | Confirmar exposição de realizado e projetado |
| Cartão | Compra na competência; pagamento da fatura no caixa | Confirmar redação e rastreabilidade |
| Retenção | O produto ainda não define prazo legal/contratual para relatórios e exportações | Definir requisitos por tipo de cliente e documento |

## Fora do escopo sem nova regra aprovada

- cálculo ou transmissão de tributos;
- emissão ou validação de documento fiscal;
- DRE, balanço, livro ou escrituração oficial;
- estoque, custo, margem ou indicadores sem fonte transacional válida;
- enquadramento tributário, prazos legais, multas, juros ou guias.

## Registro do parecer

- Profissional e CRC: ______________________________________________
- Organização responsável: ________________________________________
- Data e versão revisada: __________________________________________
- Amostra/banco de teste: __________________________________________
- Terminologia aprovada: ___________________________________________
- Ressalvas obrigatórias: __________________________________________
- Regras que precisam de alteração: _________________________________
- Prazo de retenção aprovado: ______________________________________
- Próxima revisão: _________________________________________________

Resultado: [ ] aprovado  [ ] aprovado com ressalvas  [ ] reprovado

Até que este registro seja preenchido e as eventuais alterações sejam testadas,
o sistema deve continuar usando exclusivamente os rótulos e avisos gerenciais atuais.
