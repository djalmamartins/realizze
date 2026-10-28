# Auditoria da pasta `scripts/`

Data da auditoria: 2026-10-28.

## Inventário

Existe um único arquivo: `scripts/publish-app-assets.php`.

O script publica os assets do App financeiro. Ele lê
`container/app/realizze/v1/assets/css/dashboard.css` e
`container/app/realizze/v1/assets/js/dashboard.js`, utiliza as classes oficiais
`MovesCode\Compress\CSS` e `MovesCode\Compress\JS` instaladas pelo Composer e
gera, respectivamente:

- `public/assets/realizze-app/v1/css/dashboard.min.css`;
- `public/assets/realizze-app/v1/js/dashboard.min.js`.

O destino isolado `realizze-app/v1` evita colisão com os assets institucionais
publicados em `public/assets/realizze/v1`.

## Dependências e chamadas

- Projeto: utilizado em tempo de build/publicação; em tempo de execução,
  `container/app/realizze/v1/pages/dashboard.php` prioriza os arquivos
  publicados e usa os fontes apenas como fallback local.
- Docker/Apache: não é chamado pelo `docker-compose.yml`, pelo Dockerfile ou
  pelo Apache da infraestrutura local.
- Composer: depende do autoloader `vendor/autoload.php` e do pacote
  `movescode/compress`, mas não está registrado como um comando em
  `composer.json`.
- CI: chamado diretamente por `.github/workflows/app-ui-checks.yml`, que também
  verifica a existência dos dois artefatos e valida o JavaScript minificado.
- Publicação manual: documentada pelo próprio arquivo e por
  `docs/enterprise-security.md` com `php scripts/publish-app-assets.php`.

## Obsolescência e duplicidade

Não foram encontrados scripts duplicados. O arquivo é ativo, tem chamador em
CI e corresponde ao mecanismo de publicação atualmente consumido pelo App.
Ele não parece obsoleto. Os assets fonte e minificados não são duplicatas
acidentais: representam, respectivamente, entrada versionada e saída de build.

## Recomendação

A pasta deve permanecer na raiz do projeto. O script é uma ferramenta de
build transversal ao tema e não um asset servido nem uma página da arquitetura
`container/`. Não há justificativa para apagar, mover ou renomear o arquivo.
Uma futura conveniência opcional seria expor o mesmo comando em
`composer.json`, mantendo `scripts/publish-app-assets.php` como implementação
canônica.
