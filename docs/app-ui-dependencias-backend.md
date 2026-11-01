# App: dependências de backend para a interface

Telas prontas só para leitura, aguardando as ações abaixo. Não simulam sucesso.

| Tela | Arquivo | Falta no backend |
| --- | --- | --- |
| Perfil | `pages/perfil.php` | Atualizar nome e e-mail; enviar foto; dados completos em `SessionAuth::current()` |
| Perfil | `pages/perfil.php` | Dois fatores: tabela, ativação com QR, códigos de recuperação e etapa no login |
| Empresa | `pages/empresa.php` | Editar dados; enviar logo; permissão específica |
| Equipe | `pages/equipe.php` | Convidar por e-mail, convites pendentes, trocar perfil, suspender e remover |
| Equipe | `pages/equipe.php` | Perfis Administrador, Financeiro e Contabilidade, com permissões |
| URLs | `routes.php` | Identificador estável por empresa em `/pj/...`, hoje fixo em `minha-empresa` |
| Clientes | `source/App/ContactActions.php` | Interface alterou o redirecionamento para respeitar `back`; revisar |
| Fornecedores | `Contacts::financialSummary` | Hoje conta só recebimentos; incluir despesas ao vincular contato em Pagar |
| Migrations | `database/migrations` | Há dois arquivos `003`; renumerar `003_create_finance_planning_tables.sql` |
| Integrações | `pages/integracoes.php` | Open Finance, cartões, PIX, WhatsApp oficial e APIs de banco; hoje só aparecem como indisponíveis |
| Notificações | `pages/notificacoes.php` | Tabela de avisos, lida/não lida, preferências e envio; hoje só calcula vencimentos |
| Equipe | `pages/equipe.php` | Último acesso e convites pendentes com expiração |
| Contas e logos | `finance_accounts` | Campo do banco de cada conta, para ligar a `storage/bank-logos/` |
| Cobrança | sem tela | PIX, boleto e cartão, itens, anexos, lembretes e histórico da cobrança |
| Metas | `finance_goals` | Imagem, ícone e categoria da meta; conquistas |
| Empresa | `pages/empresa.php` | Endereço, contas padrão, preferências, numeração, etiquetas, impostos, exportação e exclusão |
