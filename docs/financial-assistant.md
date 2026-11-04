# Assistente Financeiro — limites e contrato proposto

## Estado atual

A tela do Assistente Financeiro é uma prévia local, estática e somente leitura. Ela não chama WhatsApp, modelos de IA ou serviços externos; não persiste mensagens e não cria movimentações financeiras.

## Fluxo futuro

1. `MessageChannel` recebe uma mensagem de um canal previamente autorizado e normaliza remetente, conteúdo, identificador externo e horário.
2. `IntentInterpreter` sugere tipo, valor, data, descrição e categoria, incluindo confiança e campos ausentes.
3. `ConfirmationService` apresenta o resumo e exige confirmação explícita de uma identidade autenticada e autorizada.
4. `LedgerWriter` registra a movimentação uma única vez, apenas depois da confirmação e da validação das permissões da conta ou organização.

## Contratos lógicos

- `MessageChannel::receive(): AuthorizedMessage`
- `IntentInterpreter::interpret(AuthorizedMessage): TransactionDraft`
- `ConfirmationService::confirm(TransactionDraft, AuthorizedIdentity): ConfirmedDraft`
- `LedgerWriter::create(ConfirmedDraft, IdempotencyKey): TransactionId`

Esses nomes descrevem fronteiras; não são interfaces PHP implementadas nesta entrega. A escolha de provedor deve continuar fora da camada de páginas e ser injetada na camada de serviços.

## Requisitos de segurança antes de habilitar

- validar assinatura e repetição de webhooks;
- vincular o remetente a uma identidade por consentimento verificável;
- aplicar permissões PF/PJ e isolamento por organização;
- nunca gravar uma interpretação sem confirmação explícita;
- usar chave de idempotência por mensagem e confirmação;
- manter trilha de auditoria sem registrar conteúdo sensível desnecessário;
- definir retenção, exclusão e mascaramento de mensagens;
- proteger segredos do provedor fora do repositório;
- oferecer revogação do canal e revisão manual de baixa confiança.

## Critério de liberação

A interface somente poderá sair do modo de prévia quando houver provedor homologado, análise de privacidade, testes de autorização e idempotência, registro de consentimento e um ambiente não produtivo isolado. Até lá, envio, conexão e gravação permanecem desabilitados.
