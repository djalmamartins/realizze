<?php
/** Ícones que a pessoa escolhe para a meta. Retorna o SVG estático; a chave só é comparada, nunca impressa. */
return static function (?string $name, int $size = 22): string {
    $paths = \Moves\Services\Finance::GOAL_ICONS;
    $path = [
        'carro' => '<path d="M5 17h14M4 17v-5l2-6h12l2 6v5M4 12h16M7 17v2m10-2v2"/>',
        'aviao' => '<path d="M17.800 19.200 16 11l3.500-3.500a2.100 2.100 0 0 0-3-3L13 8 4.800 6.200 3.500 7.500l6.500 3.500-3 3H4l-1 1 3.500 1.500L8 20l1-1v-3l3-3 3.500 6.500z"/>',
        'casa' => '<path d="m3 11 9-8 9 8M5 9.500V21h14V9.500M10 21v-6h4v6"/>',
        'educacao' => '<path d="m2 9 10-5 10 5-10 5zM6 11.500V17c2 2 10 2 12 0v-5.500"/>',
        'coracao' => '<path d="M12 21s-8-5-8-11a4.500 4.500 0 0 1 8-3 4.500 4.500 0 0 1 8 3c0 6-8 11-8 11z"/>',
        'maleta' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
        'guardar' => '<path d="M12 3v12m-5-5 5 5 5-5M4 21h16"/>',
        'jogo' => '<rect x="2" y="7" width="20" height="11" rx="5"/><path d="M7 11v3m-1.500-1.500h3M15.500 12h.01M18 14h.01"/>',
        'camera' => '<path d="M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="4"/>',
        'carteira' => '<path d="M3 7a2 2 0 0 1 2-2h12v4"/><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 13h2"/>',
        'alvo' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'mais' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8m-4-4h8"/>',
    ][in_array($name, $paths, true) ? $name : 'alvo'];
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
};
