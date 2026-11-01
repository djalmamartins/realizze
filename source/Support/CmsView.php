<?php
declare(strict_types=1);

namespace Moves\Support;

/** Formatação de apresentação do CMS e da área de suporte. Sempre escapa a saída. */
final class CmsView
{
    public const PRIORITIES = ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
    public const TICKET_STATUS = ['open' => 'Aberto', 'waiting_agent' => 'Aguardando atendimento', 'in_progress' => 'Em atendimento', 'waiting_customer' => 'Aguardando cliente', 'resolved' => 'Resolvido', 'closed' => 'Fechado'];
    public const SUB_STATUS = ['pending' => 'Pendente', 'active' => 'Ativa', 'past_due' => 'Em atraso', 'canceled' => 'Cancelada'];
    public const CHARGE_STATUS = ['pending' => 'Pendente', 'overdue' => 'Vencida', 'paid' => 'Paga', 'failed' => 'Falhou', 'canceled' => 'Cancelada'];
    public const RATINGS = ['satisfied' => 'Satisfeito', 'neutral' => 'Neutro', 'unsatisfied' => 'Insatisfeito'];
    private const TONES = ['active' => 'ok', 'paid' => 'ok', 'published' => 'ok', 'resolved' => 'ok', 'satisfied' => 'ok', 'pending' => 'warn', 'draft' => 'warn', 'waiting_customer' => 'warn', 'waiting_agent' => 'warn', 'high' => 'warn', 'neutral' => 'warn', 'overdue' => 'bad', 'past_due' => 'bad', 'failed' => 'bad', 'urgent' => 'bad', 'suspended' => 'bad', 'unsatisfied' => 'bad', 'open' => 'info', 'in_progress' => 'info'];

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function icon(string $name): string
    {
        static $icon = null;
        $icon ??= require BASE_PATH . '/container/cms/realizze/v1/components/icons.php';
        return $icon($name);
    }

    /** Linha de indicadores: [ícone, rótulo, valor, legenda]. */
    public static function kpis(array $items): string
    {
        $html = '<div class="cms-kpis">';
        foreach ($items as [$icon, $label, $value, $caption]) {
            $html .= '<div class="cms-kpi"><i>' . self::icon($icon) . '</i><div><span>' . self::e($label) . '</span><strong>' . self::e($value) . '</strong><small>' . self::e($caption) . '</small></div></div>';
        }
        return $html . '</div>';
    }

    /** Cabeçalho de lista: título e legenda à esquerda, busca e filtros à direita. */
    public static function listHead(string $title, string $caption, string $controls = ''): string
    {
        return '<header class="cms-list-head"><div><h2>' . self::e($title) . '</h2><small>' . self::e($caption) . '</small></div>' . ($controls !== '' ? '<div class="cms-list-controls">' . $controls . '</div>' : '') . '</header>';
    }

    /** Busca compacta. $selects: nome => [valor atual, opções]. */
    public static function searchForm(string $placeholder, array $selects = []): string
    {
        $html = '<form class="cms-search" method="get"><label class="cms-search-box"><span class="cms-sr">' . self::e($placeholder) . '</span>' . self::icon('search') . '<input type="search" name="q" placeholder="' . self::e($placeholder) . '" value="' . self::e(self::queryString('q')) . '"></label>';
        foreach ($selects as $name => [$current, $choices, $label]) {
            $html .= '<select name="' . self::e($name) . '" aria-label="' . self::e($label) . '">';
            foreach ($choices as $key => $text) {
                $html .= '<option value="' . self::e($key) . '"' . ((string) $key === (string) $current ? ' selected' : '') . '>' . self::e($text) . '</option>';
            }
            $html .= '</select>';
        }
        return $html . '<button class="cms-btn">Buscar</button></form>';
    }

    public static function iconLink(string $href, string $label, string $icon = 'pencil'): string
    {
        return '<a class="cms-icon-btn" href="' . self::e($href) . '" title="' . self::e($label) . '" aria-label="' . self::e($label) . '">' . self::icon($icon) . '</a>';
    }

    public static function iconAction(string $action, string $label, array $fields, ?string $confirm = null, string $icon = 'trash'): string
    {
        $html = '<form method="post" action="/acao/' . self::e($action) . '" class="cms-inline"' . ($confirm !== null ? ' data-confirm="' . self::e($confirm) . '"' : '') . '>' . self::csrf();
        foreach ($fields as $key => $value) {
            $html .= '<input type="hidden" name="' . self::e($key) . '" value="' . self::e($value) . '">';
        }
        return $html . '<button class="cms-icon-btn" title="' . self::e($label) . '" aria-label="' . self::e($label) . '">' . self::icon($icon) . '</button></form>';
    }

    /** Abas segmentadas: [link, rótulo, contagem ou null, ativa]. */
    public static function tabs(array $items, string $label): string
    {
        $html = '<nav class="cms-tabs" aria-label="' . self::e($label) . '">';
        foreach ($items as [$href, $text, $count, $active]) {
            $html .= '<a class="cms-tab' . ($active ? ' selected' : '') . '" href="' . self::e($href) . '"' . ($active ? ' aria-current="true"' : '') . '><span>' . self::e($text) . '</span>' . ($count !== null ? '<b>' . (int) $count . '</b>' : '') . '</a>';
        }
        return $html . '</nav>';
    }

    public static function csrf(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::e(\Moves\Services\Csrf::token()) . '">';
    }

    /** Campo rotulado. $options: type, help, required, rows, choices (valor => rótulo). */
    public static function field(string $label, string $name, mixed $value = '', array $options = []): string
    {
        $id = 'f-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $type = $options['type'] ?? 'text';
        $required = !empty($options['required']) ? ' required' : '';
        $attrs = 'id="' . self::e($id) . '" name="' . self::e($name) . '"' . $required;
        if ($type === 'textarea') {
            $control = '<textarea ' . $attrs . (!empty($options['editor']) ? ' data-editor' : '') . ' rows="' . (int) ($options['rows'] ?? 4) . '">' . self::e($value) . '</textarea>';
        } elseif ($type === 'select') {
            $control = '<select ' . $attrs . '>';
            foreach ($options['choices'] ?? [] as $key => $text) {
                $control .= '<option value="' . self::e($key) . '"' . ((string) $key === (string) $value ? ' selected' : '') . '>' . self::e($text) . '</option>';
            }
            $control .= '</select>';
        } elseif ($type === 'checkbox') {
            return '<label class="cms-check"><input type="hidden" name="' . self::e($name) . '" value="0"><input type="checkbox" ' . $attrs . ' value="1"' . ((string) $value === '1' ? ' checked' : '') . '> <span>' . self::e($label) . '</span></label>';
        } else {
            $control = '<input type="' . self::e($type) . '" ' . $attrs . ' value="' . self::e($value) . '"' . (isset($options['maxlength']) ? ' maxlength="' . (int) $options['maxlength'] . '"' : '') . '>';
        }
        $help = isset($options['help']) ? '<small>' . self::e($options['help']) . '</small>' : '';
        return '<div class="cms-field"><label for="' . self::e($id) . '">' . self::e($label) . '</label>' . $control . $help . '</div>';
    }

    /** Seleção pela biblioteca: somente imagens já enviadas podem ser usadas. */
    public static function mediaField(string $label, string $name, ?string $value): string
    {
        $choices = ['' => 'Sem imagem'];
        foreach (\Moves\Services\CmsQuery::all("SELECT stored_path, original_name, title FROM cms_media WHERE mime_type LIKE 'image/%' ORDER BY id DESC LIMIT 300") as $media) {
            $choices['/uploads/cms/' . $media['stored_path']] = $media['title'] !== '' ? $media['title'] : $media['original_name'];
        }
        if ($value !== null && $value !== '' && !isset($choices[$value])) {
            $choices[$value] = basename($value);
        }
        return self::field($label, $name, $value ?? '', ['type' => 'select', 'choices' => $choices, 'help' => 'Envie novas imagens em Mídia.']);
    }

    public static function empty(string $title, string $text = ''): string
    {
        return '<div class="cms-empty"><strong>' . self::e($title) . '</strong>' . ($text !== '' ? '<p>' . self::e($text) . '</p>' : '') . '</div>';
    }

    /** Formulário de ação curta. $fields são campos ocultos. */
    public static function actionButton(string $action, string $label, array $fields = [], string $class = 'cms-btn', ?string $confirm = null): string
    {
        $html = '<form method="post" action="/acao/' . self::e($action) . '" class="cms-inline"' . ($confirm !== null ? ' data-confirm="' . self::e($confirm) . '"' : '') . '>' . self::csrf();
        foreach ($fields as $key => $value) {
            $html .= '<input type="hidden" name="' . self::e($key) . '" value="' . self::e($value) . '">';
        }
        return $html . '<button type="submit" class="' . self::e($class) . '">' . self::e($label) . '</button></form>';
    }

    public static function money(mixed $value, string $currency = 'BRL'): string
    {
        $prefix = $currency === 'BRL' ? 'R$ ' : $currency . ' ';
        return $prefix . number_format((float) $value, 2, ',', '.');
    }

    public static function date(?string $value, bool $time = true): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $stamp = strtotime($value);
        return $stamp === false ? '—' : date($time ? 'd/m/Y H:i' : 'd/m/Y', $stamp);
    }

    public static function badge(string $key, ?string $label = null): string
    {
        return '<span class="cms-badge cms-badge-' . (self::TONES[$key] ?? 'neutral') . '">' . self::e($label ?? $key) . '</span>';
    }

    public static function minutes(mixed $minutes): string
    {
        if ($minutes === null || $minutes === '') {
            return '—';
        }
        $minutes = (int) round((float) $minutes);
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        return $minutes < 1440 ? round($minutes / 60, 1) . ' h' : round($minutes / 1440, 1) . ' dias';
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /** Links de paginação preservando apenas filtros simples da consulta atual. */
    public static function pager(array $result): string
    {
        if (($result['pages'] ?? 1) <= 1) {
            return '';
        }
        $query = array_filter($_GET, 'is_string');
        $link = static function (int $page, string $label, bool $current = false) use ($query): string {
            $query['pagina'] = $page;
            return '<a href="?' . self::e(http_build_query($query)) . '"' . ($current ? ' aria-current="page" class="selected"' : '') . '>' . self::e($label) . '</a>';
        };
        $current = (int) $result['page'];
        $last = (int) $result['pages'];
        $html = '<nav class="cms-pager" aria-label="Paginação"><span>Página ' . $current . ' de ' . $last . ' · ' . (int) $result['total'] . ' registro(s)</span><div>';
        if ($current > 1) {
            $html .= $link($current - 1, 'Anterior');
        }
        for ($page = max(1, $current - 2); $page <= min($last, $current + 2); $page++) {
            $html .= $link($page, (string) $page, $page === $current);
        }
        if ($current < $last) {
            $html .= $link($current + 1, 'Próxima') . $link($last, 'Última');
        }
        return $html . '</div></nav>';
    }

    public static function queryString(string $key): string
    {
        $value = $_GET[$key] ?? '';
        return is_string($value) ? trim(mb_substr($value, 0, 190)) : '';
    }

    public static function queryPage(): int
    {
        $value = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? 1 : $value;
    }
}
