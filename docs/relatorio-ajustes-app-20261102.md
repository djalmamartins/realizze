# Relatório: ajustes visuais e funcionais do App

- **Branch:** `feat/realizze-integracao-ui-20261101`
- **Commit:** `6bfd7ec` (sem push)
- **Banco e `.env`:** não alterados. Os testes rodaram numa cópia temporária do banco, já removida.

Os 8 commits de referência já estavam na branch. Por isso, nada de cherry-pick ou merge: só corrigi o que a verificação no navegador mostrou.

## Ajustes concluídos

| Etapa | Situação encontrada | O que foi feito |
|---|---|---|
| Barra superior | A Visão Geral tinha estrutura diferente das outras páginas | Mesma estrutura em todas. O botão do menu no celular fica na mesma posição, e o sino não encosta mais no avatar |
| Títulos | Na Visão Geral o título tinha outro tamanho e posição. No celular, um vazio de 280px empurrava o conteúdo | Título na mesma posição e tamanho em todas as páginas. Cabeçalho no celular caiu de 404–455px para 193–244px. Abaixo de 1100px ele deixa de ser fixo |
| Dashboard | Margem de 20px, 75/25, gráfico de 200px e painel de vencimentos já estavam certos | Sem mudança no gráfico. Conferido em 1440, 1000 e 390px, sem cortes |
| Menu | Na PF já estava certo. Na PJ, Recorrências ficava em Gestão | Na PJ, agora vem logo após Movimentações |
| Recorrências em Pagar | A Netflix, ativa com vencimento em 03/11, não aparecia em Pagar | Novo bloco "Recorrências previstas" em Pagar e Receber. É só leitura, não soma nos totais e não aparece em dobro |
| Logos | Contas antigas sem instituição vinculada não mostravam logo | Logo agora é encontrado também pelo nome da conta. O alinhamento em Recorrências foi corrigido |
| Favicons | O PF voltou a usar o arquivo antigo `favicon.svg` depois de um merge | PF usa `favicon-realizze.svg`, inclusive nas telas de erro e suporte. PJ mantém o da empresa |
| Loading | Piscava em navegação rápida, podia ficar preso e não tinha cor PJ | Só aparece após 120ms e some sozinho em até 12s. Tem cor PJ e respeita movimento reduzido |
| Navegação | Dois cliques rápidos podiam deixar a tela errada | A resposta antiga é descartada |
| Assets | | CSS e JS publicados por `scripts/publish-app-assets.php`. A URL tem `?v=` novo |

## Testes

**Automáticos**

- Sintaxe PHP dos arquivos alterados: sem erros.
- `tests/finance-008-recurring-planned.php` (novo): passou.
- Suíte `tests/`: tudo passou, exceto `core-002-mail-queue`.
  - Essa falha não vem desta tarefa: o teste chama `Email::queue()`, que não existe. O teste veio no commit `c56ebbe`.
  - `core-001-email-smtp` não foi executado, porque enviaria e-mail real.
- Depois da suíte completa, ainda movi o botão do menu para o componente do cabeçalho. Em seguida só repeti `ui-002-view-smoke` (passou) e a verificação no navegador, não a suíte inteira.

**No Chrome headless, logado na cópia do banco**

- Dashboard, Movimentações, Pagar, Receber, Contas e Cartões, Metas, Planejamento, Recorrências e Perfil em 1440, 1200, 1000, 768 e 390px: mesmo alinhamento, sem rolagem horizontal e sem imagens quebradas.
- Sem erros de JavaScript nem de carregamento. O único aviso foi o 404 que provoquei de propósito para testar a tela de erro.
- Menu da conta, notificações e menu do celular, inclusive depois de trocar de página.
- Favicon e loading em PF e PJ, com navegação rápida, rede lenta, resposta travada, dois cliques seguidos e movimento reduzido.
- Tela de erro 404 com o favicon correto.

**Não testado**

- Em celular real, só na emulação de tela do Chrome.
- A atualização de assets no seu navegador, com cache normal. Testei com o cache desligado.
- Logado em `app.realizze.lab` com o `realizze_dev`, porque as migrations seguem pendentes lá. Nesse endereço só confirmei que a página de login responde 200.
- O teste da opção de logos ligada foi na cópia, com o catálogo importado. No `realizze_dev` não verifiquei se o catálogo está importado.

## Pendências reais

1. **Logos de bancos desligados.** O código e os arquivos estão prontos, mas `REALIZZE_BANK_LOGOS_ENABLED=1` não está no `.env`, que não podia ser alterado. O `manifest.json` avisa que os logos não têm licença aberta e pede revisão antes de produção. Enquanto a opção estiver desligada, os bancos mostram as iniciais.
2. **Logos de bandeiras de cartão e de assinaturas não existem no projeto.** Não há esses SVGs no repositório, então Visa, Netflix e outras seguem com o fallback (selo colorido com inicial ou etiqueta com o nome). Não baixei nem desenhei marcas de terceiros.
3. **Recorrências automáticas.** O bloco mostra o que está previsto, mas o lançamento só é criado com a migration 011 aplicada e `scripts/finance-recurring-due.php` agendado.
4. **`tests/core-002-mail-queue.php` falhando**, como descrito acima.
