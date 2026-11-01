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
        'notificacoes' => 'notificacoes', 'integracoes' => 'integracoes',
        'perfil' => 'perfil', 'configuracoes' => 'configuracoes',
        'assistente' => 'assistente', 'empresarial' => 'empresarial',
    ];

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
        $url = $base . ($slug === '' ? '' : '/' . $slug);
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}
