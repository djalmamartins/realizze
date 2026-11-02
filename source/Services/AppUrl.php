<?php
declare(strict_types=1);

namespace Moves\Services;

/** URLs canônicas do App. Nunca usa o slug como autorização. */
final class AppUrl
{
    private const SECTIONS = [
        'overview' => '', 'receber' => 'receber', 'pagar' => 'pagar',
        'movimentacoes' => 'movimentacoes', 'contas' => 'cartoes',
        'relatorios' => 'relatorios', 'planejamento' => 'planejamento',
        'metas' => 'metas', 'categorias' => 'categorias',
        'clientes' => 'clientes', 'fornecedores' => 'fornecedores',
        'contatos' => 'contatos', 'equipe' => 'equipe', 'empresa' => 'empresa',
        'fluxo-de-caixa' => 'fluxo-de-caixa', 'faturas' => 'faturas', 'conciliacao' => 'conciliacao',
        'centros-de-custo' => 'centros-de-custo', 'dre' => 'dre', 'obrigacoes' => 'obrigacoes',
        'recorrencias' => 'recorrencias', 'ordens-de-servico' => 'ordens-de-servico',
        'permissoes' => 'permissoes', 'seguranca' => 'seguranca', 'auditoria' => 'auditoria',
        'notificacoes' => 'notificacoes', 'integracoes' => 'integracoes',
        'perfil' => 'perfil', 'configuracoes' => 'configuracoes',
        'assistente' => 'assistente', 'empresarial' => 'empresarial',
        'sair-das-dividas' => 'sair-das-dividas',
    ];

    /** Formatos de período que cada tela realmente filtra. Metas e contatos não têm período na URL. */
    private const PERIODS = [
        'overview' => ['mes', 'ano', 'datas'], 'receber' => ['mes', 'ano', 'datas'],
        'pagar' => ['mes', 'ano', 'datas'], 'movimentacoes' => ['mes', 'ano', 'datas'],
        'relatorios' => ['mes', 'ano'], 'categorias' => ['mes', 'ano'],
        'planejamento' => ['mes', 'ano'], 'fluxo-de-caixa' => ['mes'],
    ];

    /** Seções com período na URL, para o registro das rotas. */
    public static function periodSections(): array
    {
        return array_keys(self::PERIODS);
    }

    /**
     * Traduz os segmentos da URL para os parâmetros que as telas já leem.
     * Mês e período personalizado têm o mesmo formato no Router: a decisão é daqui.
     * Retorna null se os segmentos não formam um período aceito por esta seção.
     */
    public static function periodFromSegments(string $section, string $first, ?string $second = null): ?array
    {
        $formats = self::PERIODS[$section] ?? [];
        $query = null;
        if ($second === null) {
            if (in_array('ano', $formats, true) && self::validYear($first)) {
                $query = ['visao' => 'anual', 'ano' => $first];
                $range = [$first . '-01-01', $first . '-12-31'];
            }
        } elseif (in_array('mes', $formats, true) && self::validYear($first) && preg_match('/^(0[1-9]|1[0-2])$/D', $second) === 1) {
            $query = ['mes' => $first . '-' . $second];
            $range = [$first . '-' . $second . '-01', date('Y-m-t', strtotime($first . '-' . $second . '-01'))];
        } elseif (in_array('datas', $formats, true) && self::validDate($first) && self::validDate($second) && $first <= $second) {
            return ['inicio' => $first, 'fim' => $second];
        }
        if ($query === null) return null;
        // Receber só lê datas; Pagar e Movimentações, no ano inteiro, também.
        if ($section === 'receber' || (isset($query['ano']) && in_array($section, ['pagar', 'movimentacoes'], true))) {
            $query += ['inicio' => $range[0], 'fim' => $range[1]];
        }
        return $query;
    }

    /** True quando a URL pedida ainda traz o período na query e tem endereço próprio. */
    public static function hasPeriodQuery(string $section, array $query): bool
    {
        return self::periodPath($section, $query)[0] !== '';
    }

    public static function base(?array $organization = null): string
    {
        $organization ??= OrganizationContext::current();
        if ($organization === null) return '/p';
        return '/e/' . rawurlencode(Organizations::slugFor((int) $organization['id'], (string) $organization['name']));
    }

    public static function section(string $section, ?array $organization = null, array $query = []): string
    {
        $base = self::base($organization);
        $slug = self::SECTIONS[$section] ?? '';
        [$period, $query] = self::periodPath($section, $query);
        $url = $base . ($slug === '' ? '' : '/' . $slug) . $period;
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    /** Volta para a tela de origem com os filtros dela, se o Referer for mesmo desta seção. */
    public static function backTo(string $section, string $fallback): string
    {
        $referer = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $path = is_array($referer) ? (string) ($referer['path'] ?? '') : '';
        $own = self::section($section);
        if ($path !== $own && !str_starts_with($path, $own . '/')) return $fallback;
        if (($referer['host'] ?? '') !== ($_SERVER['HTTP_HOST'] ?? '')) return $fallback;
        return $path . (isset($referer['query']) ? '?' . $referer['query'] : '');
    }

    /**
     * Separa o período da query e devolve [segmentos, query restante].
     * Parâmetro inválido fica na query: a tela o ignora, como já faz hoje.
     * @return array{0:string,1:array}
     */
    private static function periodPath(string $section, array $query): array
    {
        $formats = self::PERIODS[$section] ?? [];
        $start = $query['inicio'] ?? null;
        $end = $query['fim'] ?? null;
        if (self::validDate($start) && self::validDate($end) && $start <= $end) {
            $rest = array_diff_key($query, ['inicio' => 1, 'fim' => 1, 'mes' => 1, 'visao' => 1, 'ano' => 1]);
            if (in_array('ano', $formats, true) && substr($start, 5) === '01-01' && substr($end, 5) === '12-31' && substr($start, 0, 4) === substr($end, 0, 4) && self::validYear(substr($start, 0, 4))) {
                return ['/' . substr($start, 0, 4), $rest];
            }
            if (in_array('mes', $formats, true) && substr($start, 8) === '01' && $end === date('Y-m-t', strtotime($start)) && self::validYear(substr($start, 0, 4))) {
                return ['/' . substr($start, 0, 4) . '/' . substr($start, 5, 2), $rest];
            }
            if (in_array('datas', $formats, true)) return ['/' . $start . '/' . $end, $rest];
        }
        if (($query['visao'] ?? '') === 'anual' && in_array('ano', $formats, true) && self::validYear($query['ano'] ?? null)) {
            return ['/' . $query['ano'], array_diff_key($query, ['visao' => 1, 'ano' => 1, 'mes' => 1])];
        }
        $month = $query['mes'] ?? null;
        if (in_array('mes', $formats, true) && is_string($month) && preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $month, $parts) === 1) {
            return ['/' . $parts[1] . '/' . $parts[2], array_diff_key($query, ['mes' => 1])];
        }
        return ['', $query];
    }

    private static function validYear(mixed $value): bool
    {
        // Finance::period entrega o ano seguinte e o anterior como inteiros.
        return (is_string($value) || is_int($value)) && preg_match('/^20\d{2}$/D', (string) $value) === 1;
    }

    private static function validDate(mixed $value): bool
    {
        if (!is_string($value) || preg_match('/^20\d{2}-\d{2}-\d{2}$/D', $value) !== 1) return false;
        return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    /** Mesma tela na área pessoal, seja qual for a empresa aberta na sessão. */
    public static function personal(string $section, array $query = []): string
    {
        $slug = self::SECTIONS[$section] ?? '';
        [$period, $query] = self::periodPath($section, $query);
        $url = '/p' . ($slug === '' ? '' : '/' . $slug) . $period;
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    public static function hasSlug(string $slug): bool
    {
        return $slug !== '' && in_array($slug, self::SECTIONS, true);
    }
}
