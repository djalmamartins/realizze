# Modal financeira única

Receitas, despesas, repetições e assinaturas usam um só componente: `container/app/realizze/v1/components/entry-form.php` (com `entry-extras.php`), em Visão geral, Pagar, Receber, Movimentações e Assinaturas. Abre por `data-entry-open="income|expense"` (opcional `data-entry-repeat="subscription"`) ou `data-entry-edit='{...}'`; o `dashboard.js` define título, tipo e o que aparece.

## Formulários antigos substituídos

| Antes | Onde | Agora |
|---|---|---|
| `entry-form.php`, diálogo de lançamento | Pagar, Visão geral (dois diálogos) | Modal única, um diálogo por página |
| `entry-form.php`, segundo diálogo `-recurring` | Pagar, Visão geral | Repetir > Assinatura |
| `receivables-fields.php` + `receivable-create` | Receber | Modal única, com "Dados do recebimento" recolhido |
| `receivable-edit-N`, um por linha | Receber | Modal única em modo de edição |
| `recurring-dialog` | Assinaturas | Modal única, com Repetir já aberto |
| Não existia | Edição de despesa e de assinatura; Nova receita/despesa em Movimentações | Modal única |

Continuam separados, por terem outra finalidade: baixa e cancelamento de recebimento, transferência, pagamento de dívida.

## Destino único

`POST /minha-conta/financeiro/lancamento` e `/lancamento-editar` (`FinanceActions`) repassam para os services existentes: `Finance` (despesa), `Receivables` (receita), `RecurringFinance` (assinatura). A modal não calcula nada no servidor. As rotas `recebimento`, `recebimento-editar` e `recorrencia/criar` saíram.

## Regras

- **Com prazo:** a primeira parcela entra na quantidade. O padrão é o valor informado em cada parcela; o usuário pode escolher dividir o total. A prévia mostra as datas e os valores.
- **Assinatura:** cria só a primeira ocorrência, e as seguintes saem de `generateDue`, sem duplicar, até o cancelamento. Quem vence dia 31 passa por 28 em fevereiro e volta a 31.
- **Edição:** vale só para a ocorrência aberta. Editar assinatura não muda lançamentos já gerados. Pagamento de dívida só muda em Sair das dívidas.
- **Anexos:** remover só marca o arquivo; ele é apagado ao salvar. Tudo é procurado no contexto atual, então PF e PJ não veem os arquivos um do outro.
- **Sem as migrations 008 e 011:** Anexo, Tags, Com prazo e a geração automática ficam desabilitados, e a assinatura fica como prevista.

## Teste

`tests/finance-012-entry-modal.php` exige 007, 008 e 011 e deve rodar em banco temporário. No zsh, passe as variáveis por script bash: `$E` sem aspas não separa as palavras, e o teste cai no `realizze_dev`.
