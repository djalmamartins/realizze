# Realizze 2.0 — cronograma para beta e V1

## Premissas

- um desenvolvedor principal com apoio de agentes para inspeção/testes;
- decisões de produto e infraestrutura em até um dia útil;
- nenhuma mudança fiscal ou integração externa entra no caminho crítico;
- homologação usa cópia isolada e descartável, nunca `realizze_dev`;
- revisão jurídica e provisão de infraestrutura podem ocorrer em paralelo.

## Esforço por frente

| Frente | Otimista | Provável | Conservador |
|---|---:|---:|---:|
| Integração e correções funcionais | 12 h | 24 h | 40 h |
| Banco/migrations/homologação reproduzível | 10 h | 20 h | 32 h |
| Testes funcionais e regressão financeira | 12 h | 24 h | 40 h |
| Segurança, privacidade e e-mail | 10 h | 20 h | 32 h |
| Visual, mobile, acessibilidade e performance | 8 h | 14 h | 24 h |
| Infraestrutura, observabilidade, backup e deploy | 16 h | 28 h | 48 h |
| Aceite final e correções da homologação | 8 h | 16 h | 32 h |
| **Total ativo** | **76 h** | **146 h** | **248 h** |

O total não inclui espera por jurídico, domínio, SMTP, fornecedor ou janela de mudança.

## Cenários

### Otimista

- Branch pendente integra sem regressão relevante.
- Migrations aplicam e restauram na primeira tentativa.
- Infra já existe e apenas precisa de configuração.
- **Beta controlada:** 7–10 dias úteis.
- **V1 pública:** 12–15 dias úteis, após pelo menos alguns dias de observação.

### Provável

- Há resolução de conflitos, ajustes na fixture e uma rodada de correções financeiras/UI.
- Backup, monitoramento e deploy precisam ser montados e ensaiados.
- **Beta controlada:** 2–3 semanas.
- **V1 pública:** 4–5 semanas.

### Conservador

- A integração revela regressões, migrations exigem correções e a infraestrutura ainda não está provisionada.
- Aceite jurídico ou SMTP atrasa.
- **Beta controlada:** 4–5 semanas.
- **V1 pública:** 6–8 semanas.

## Sequência semanal provável

| Etapa | Entrega | Paralelização segura |
|---|---|---|
| 1. Consolidação (2–4 dias) | revisão dos 28 commits, escopo congelado, candidato único | jurídico e provisão de homologação |
| 2. Schema e segurança (3–5 dias) | migrations ensaiadas, fixture, estorno/IDOR/LGPD/e-mail | SMTP, backup e observabilidade |
| 3. Homologação funcional (4–7 dias) | suíte completa PF/PJ, cartões, relatórios e permissões | mobile/a11y em revisão estável |
| 4. Operação (3–5 dias) | deploy/rollback, jobs, alertas, restore | aceite de negócio/jurídico |
| 5. Beta (5–10 dias corridos) | grupo controlado, telemetria e correções | documentação e suporte |
| 6. V1 | go/no-go assinado e promoção da mesma release | — |

## Portões de saída

- **Para beta:** todos os P0 técnicos fechados, 0 migration parcial, fluxos financeiros essenciais aprovados, restore e rollback ensaiados.
- **Para V1:** beta sem incidente crítico aberto, P1 fechado, termos aprovados, monitoramento/backup operantes e aceite formal de produto.
