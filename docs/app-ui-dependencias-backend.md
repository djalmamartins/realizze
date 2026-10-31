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
