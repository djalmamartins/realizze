# Realizze 2.0

Plataforma em desenvolvimento para modernizar a experiência de gestão de condomínios, com arquitetura modular, ambientes independentes e interface responsiva.

## Tecnologias

- PHP 8.2+
- Composer
- MovesCode Router
- MovesCode View
- MovesCode Model
- MovesCode Middleware
- MovesCode SEO
- MySQL
- Apache e Docker
- HTML, CSS e JavaScript

## Arquitetura

O Realizze utiliza ambientes separados dentro de `container/`:

| Ambiente | Responsabilidade |
|---|---|
| `web` | Site institucional e páginas públicas |
| `app` | Aplicação dos clientes |
| `cms` | Gerenciamento de conteúdo |
| `erp` | Gestão administrativa e operacional |
| `send` | Templates e recursos de comunicação |

Cada tema é organizado por nome e versão.

Exemplo: `container/web/realizze/v1/`.

Na raiz de cada versão de tema existem apenas dois arquivos PHP:

- `default.php`: layout principal e composição visual.
- `routes.php`: definição e registro das rotas.

As páginas ficam em `pages/`, os componentes em `components/` e os recursos estáticos em `assets/`.

## Estado atual

A estrutura inicial do ambiente Web está implementada, com roteamento, renderização de templates, componentes reutilizáveis e páginas institucionais demonstrativas.

As interfaces de autenticação e contato ainda não estão integradas aos serviços de backend.

## Desenvolvimento local

O projeto utiliza Docker e Apache, com o endereço:

`https://www.realizze.lab`

O DocumentRoot aponta para `public/`.

## Diretrizes

- Reutilizar os pacotes oficiais MovesCode.
- Preservar o isolamento entre ambientes.
- Não armazenar credenciais no código-fonte.
- Manter componentes reutilizáveis.
- Documentar decisões arquiteturais.
- Implementar funcionalidades em etapas testáveis.

## Próximas etapas

1. Consolidar Git e documentação técnica.
2. Definir o Design System do Realizze.
3. Reformular a interface institucional.
4. Integrar autenticação e serviços reais.
5. Desenvolver os ambientes App, CMS e ERP.

## Status

**Em desenvolvimento — versão 2.0.**

As páginas atuais representam a fundação técnica do projeto e não constituem uma versão pronta para produção.