# CMS e Central de Atendimento

## Acesso local

O CMS tem dois controles: o portão de ambiente em `routes.php` e o login com permissões.

1. Adicione ao `.env` local: `REALIZZE_CMS_PREVIEW_ALLOWED_IPS=172.18.0.1` (gateway Docker visto pelo Apache).
2. Conceda acesso a um usuário ativo: `docker exec -u www-data dev-apache php /var/www/html/realizze/scripts/cms-grant-admin.php EMAIL superadmin`.
3. Entre em `https://cms.realizze.lab/entrar` com o e-mail e a senha do Realizze.

Fora do ambiente local, o CMS só abre com `REALIZZE_CMS_ENABLED=1`.

## Migrations

`100` administração, `101` conteúdo, `102` planos e cobranças, `103` atendimento, `104` respostas rápidas, `105` equipe, tags e tempo dedicado, `106` destaques, depoimentos, acessos, comunicações, versões e log, `107` conteúdo inicial da base de conhecimento, `108` mensagens do formulário público. São aditivas e usam numeração própria para não colidir com as do App.

## Papéis

Superadministrador tem tudo. Administrador, Editor, Financeiro da plataforma e Suporte têm a matriz editável em Administração > Permissões. Toda ação valida a permissão no backend em `source/App/CmsActions.php`.

## Regras comerciais

- Preços em `DECIMAL(12,2)`. Mudar preço fecha a versão anterior em `plan_prices` e exige motivo.
- A assinatura guarda `plan_price_id` e `contracted_amount`: reajuste não altera contrato existente.
- Assinatura paga nasce pendente e só fica ativa com pagamento confirmado por pessoa com `charges.manage`, usando comprovante único.
- Não há provedor de pagamento integrado e nada é cobrado. Ao integrar um, a confirmação deve vir de webhook assinado e chamar `PlatformBilling::confirmPayment` com `provider` e referência do provedor.
- `platform_charges` é separado de `finance_*`.

## Máquina de estados dos chamados

| De | Para (equipe) |
|---|---|
| Aberto | Aguardando atendimento, Em atendimento, Aguardando cliente, Resolvido |
| Aguardando atendimento | Em atendimento, Aguardando cliente, Resolvido |
| Em atendimento | Aguardando atendimento, Aguardando cliente, Resolvido |
| Aguardando cliente | Aguardando atendimento, Em atendimento, Resolvido |
| Resolvido | Em atendimento (reabertura), Fechado |
| Fechado | Em atendimento (reabertura) |

Cliente: marca como resolvido, confirma (Fechado) ou reabre (Aguardando atendimento). Resposta da equipe leva a Aguardando cliente; resposta do cliente volta para Aguardando atendimento.

## Acesso a chamados de empresa

Quem abriu e o responsável pela empresa sempre veem. Outro membro só vê com autorização em Plataforma > Empresas > detalhe. Ser membro não basta.

## Anexos

Ficam em `storage/helpdesk`, fora de `public/`. Aceita JPG, PNG, GIF, WebP, PDF e TXT, até 10 MB e 5 por mensagem. MIME real conferido, download sempre autorizado por chamado e servido como anexo com `nosniff`.

## Notificações

Internas são gravadas na hora em `notifications`. Externas entram em `notification_deliveries`, com chave única por evento, e só saem por `php scripts/process-notifications.php` (agendar no cron quando ativar). São até 5 tentativas com espera crescente e no máximo 15 envios por execução.

### Telegram

Preparado, sem envio real testado. Para ativar: criar o bot no BotFather, colocar o bot no grupo privado da equipe, definir `TELEGRAM_BOT_TOKEN` e `TELEGRAM_CHAT_ID` no `.env`, marcar a opção no CMS e agendar o processador. A mensagem leva protocolo, assunto, prioridade e situação; sequências que parecem CPF, CNPJ ou cartão são mascaradas. Somente notifica: não há resposta bidirecional.

### E-mail

Usa `Moves\Services\Mail` (PHPMailer) quando a opção está ativa no CMS. Não testado com envio real.

### Avaliação do WhatsApp

Comparação para aviso interno à equipe e aviso ao cliente. Preços e limites mudam: confirmar nas páginas oficiais da Meta antes de decidir.

| Critério | Telegram Bot API | WhatsApp Cloud API (Meta) | Provedor oficial (BSP) |
|---|---|---|---|
| Requisitos | Bot e chat ID | Conta Business verificada e número dedicado | O mesmo, com contrato com o provedor |
| Custo | Sem custo de API | Cobrança da Meta por mensagem de template | Meta mais mensalidade ou margem do provedor |
| Limites | Limite de frequência por chat | Níveis de envio que crescem com a reputação | Iguais aos da Meta |
| Templates | Não precisa | Obrigatórios fora da janela de 24 horas, com aprovação prévia | Obrigatórios |
| Segurança | Token no ambiente | Token no ambiente e webhook assinado | Dados passam também pelo provedor |
| Manutenção | Baixa | Média: templates, número e reputação | Média, com dependência do fornecedor |
| Confiabilidade | Boa para equipe interna | Boa para falar com clientes | Boa, depende também do provedor |

Recomendação: Telegram para a equipe agora. WhatsApp Cloud API direto só quando houver demanda de avisar clientes por esse canal, pois exige conta verificada e custo por mensagem. Bibliotecas não oficiais que simulam o WhatsApp Web ficam descartadas: colocam o número em risco de bloqueio. O botão de WhatsApp da aba lateral é só um link `wa.me`, exibido quando o número é configurado no CMS.

## Base de conhecimento e assinatura no App

Artigos do tipo Ajuda em Conteúdos aparecem em `/suporte/ajuda` no App e em `/ajuda` no CMS, e não entram no blog. `/minha-assinatura` mostra plano, situação e limites do contexto atual. Os limites são apenas exibidos: nenhum módulo do App os aplica ainda.

## SEO automático

Páginas e artigos geram título, descrição, URL canônica e imagem social a partir do conteúdo, em `CmsSite::autoMeta`. Não há tela de SEO. `/sitemap.xml` lista só o que está publicado e indexável; `/robots.txt` aponta o sitemap.

## Módulos do site e comunicação

Destaques e depoimentos publicados aparecem na página inicial. Relatórios contam acessos do site com hash diário, sem guardar IP e sem robôs. Comunicações são entregues a todos, clientes ou equipe, na hora ou agendadas; agendadas saem ao abrir a Central de notificações ou por `php scripts/process-notifications.php`. O log técnico agrupa erros por ocorrência e remove e-mails, documentos e segredos.

## Central de Suporte pública

`/suporte` no site reúne busca e pastas da base de conhecimento, atalhos e formulário. A pasta `ajuda-cms` é interna: não aparece no site nem no App. Mensagens ficam em Atendimento > Mensagens do site. WhatsApp, e-mail, telefone e horário só aparecem quando preenchidos em Configurações.

## Testes

Dentro do `dev-apache`, como `www-data`: `php tests/cms-100-e2e.php`. Cria e remove os próprios dados e substitui os transportes externos.
