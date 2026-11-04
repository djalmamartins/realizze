
# Arquitetura — Realizze 2.0

## Objetivo

Construir uma plataforma modular para gestão de condomínios,
utilizando PHP, Composer e os componentes MovesCode.

## Ambientes

container/
- web/ — site institucional
- app/ — aplicação do cliente
- cms/ — gerenciamento de conteúdo
- erp/ — gestão administrativa
- send/ — comunicação e notificações

Cada ambiente possui isolamento de responsabilidades.

No desenvolvimento local, `www.realizze.lab`, `app.realizze.lab` e
`cms.realizze.lab` usam o mesmo front controller e são selecionados somente pelo
`EnvironmentRouter`, a partir do hostname configurado. Não existe um segundo
mecanismo de roteamento para os subdomínios.

## Organização dos temas

container/{ambiente}/{tema}/{versao}/

Na raiz de cada versão existem apenas:

- default.php — layout HTML e composição de componentes.
- routes.php — registro de rotas usando MovesCode Router.

Demais diretórios:

- assets/ — CSS, JavaScript, imagens e outros recursos.
- components/ — elementos reutilizáveis.
- pages/ — páginas específicas do ambiente.

## Regras

1. Não misturar páginas institucionais com a aplicação.
2. Reutilizar os recursos existentes do MovesCode.
3. Não armazenar credenciais no repositório.
4. Preservar componentes e contratos existentes.
5. Documentar alterações arquiteturais.
6. Testar antes de integrar mudanças à branch principal.
7. Manter App e CMS indisponíveis por padrão; prévias exigem `APP_ENV=local` e
   flags locais explícitas.

## Estado atual

A estrutura inicial do ambiente Web está implementada.
As interfaces de autenticação e contato ainda são demonstrativas.

O layout atual é uma base técnica, não um design aprovado.
