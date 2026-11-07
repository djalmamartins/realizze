# Conformidade brasileira (2026 e 2027)

Verificado em 07/11/2026. Regras fiscais só entram no Realizze com fonte oficial e, quando indicado, validação de contador. O DRE do Realizze é gerencial e não substitui demonstração contábil (a tela já diz isso).

| Tema | Situação no Realizze | Fonte e vigência | Precisa de contador? |
|---|---|---|---|
| CNPJ alfanumérico | **Implementado** (07/11): 12 posições com letras ou números e 2 dígitos verificadores numéricos; módulo 11 com o valor ASCII − 48 de cada caractere. Vale para empresa e clientes/fornecedores; busca e máscara aceitam letras. Teste `enterprise-012-cnpj-alphanumeric` (exemplo oficial 00.000.000/E08G-12) e `tests/http/cnpj-alphanumeric-flow.php`. | IN RFB nº 2.229, de 15/10/2024 ([texto](http://normas.receita.fazenda.gov.br/sijut2consulta/link.action?idAto=141102)); vigência a partir de julho de 2026, só para novas inscrições ([Serpro](https://serpro.gov.br/menu/noticias/noticias-2024/cnpj-alfanumerico), com [PDF do cálculo](https://www.serpro.gov.br/menu/noticias/videos/calculodvcnpjalfanaumerico.pdf)). | Não |
| CNPJ numérico | Compatível: o mesmo algoritmo dá o resultado de antes para CNPJ só com números; dados já gravados não mudam. | Mesma IN: os números atuais continuam válidos. | Não |
| Enquadramento (MEI, Simples, Presumido) | **Não existe campo.** Não há onde registrar o regime da empresa. | | Sim, para regras derivadas |
| Limite de faturamento do MEI | Não implementado. As fontes consultadas falam em R$ 81 mil/ano para 2026; uma fonte comercial cita R$ 130 mil, sem norma publicada. Não usar até haver lei complementar publicada. | LC 123/2006, art. 18-A (a conferir no Planalto antes de implementar) | Sim |
| DAS e DASN-SIMEI | Não há cálculo. Obrigações é uma agenda manual (`/obrigacoes`), sem imposto presumido, o que é coerente com esta regra. Prazo da DASN-SIMEI citado pelas fontes: 31 de maio. | Portal do Simples Nacional (a confirmar) | Sim |
| NFS-e nacional | Não há emissão nem integração. As fontes divergem sobre a data de obrigatoriedade para MEI. | Portal NFS-e / CGSN (a confirmar) | Sim |
| Integrações oficiais | Nenhuma. Possíveis no futuro: API da NFS-e nacional e consulta de CNPJ. | | Não para consulta; sim para emissão |

## Recomendação para a v1.0

- Lançar com o CNPJ alfanumérico (feito) e com Obrigações como agenda manual, sem cálculo de imposto.
- P1: campo de enquadramento da empresa (MEI, Simples Nacional, outro) só como informação, sem regra derivada.
- P2 (depende de contador): alerta de limite do MEI, lembrete de DAS e DASN-SIMEI a partir do enquadramento, NFS-e.

Fontes de apoio consultadas (secundárias, só para conferência): [Contábeis](https://www.contabeis.com.br/noticias/73792/cnpj-tera-novo-formato-a-partir-de-julho-de-2026/), [Tecnoblog](https://tecnoblog.net/noticias/e-oficial-receita-federal-ja-emite-cnpj-com-letras-e-numeros/), [Contmatic](https://simplifique.contmatic.com.br/blogs/mei-2026-limite-faturamento-o-que-muda), [Jornal Contábil](https://www.jornalcontabil.com.br/noticia/atencao-nfs-e-nacional-para-mei-sera-obrigatoria-apenas-em-setembro/).
