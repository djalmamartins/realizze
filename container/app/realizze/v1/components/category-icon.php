<?php
/**
 * Ícone da categoria, escolhido pelo nome; sem ícone próprio, usa uma etiqueta.
 * Retorna o SVG estático; o nome só é comparado, nunca impresso.
 */
return static function (string $name): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($name, 'UTF-8')) ?: mb_strtolower($name, 'UTF-8');
    $paths = [
        'aliment|mercado|restaurante|comida|refei' => '<path d="M7 3v8m-3-8v5a3 3 0 0 0 6 0V3M7 11v10M17 3c-2 1-3 4-3 7 0 2 1 3 3 3zm0 10v8"/>',
        'morad|aluguel|casa|condomin|estrutura' => '<path d="m3 11 9-8 9 8M5 9.500V21h14V9.500M10 21v-6h4v6"/>',
        'transport|combust|carro|uber|veic' => '<path d="M5 17h14M4 17v-5l2-6h12l2 6v5M4 12h16M7 17v2m10-2v2"/>',
        'saude|farmac|medic|plano de saude' => '<path d="M12 21s-8-5-8-11a4.500 4.500 0 0 1 8-3 4.500 4.500 0 0 1 8 3c0 6-8 11-8 11zM12 9v6m-3-3h6"/>',
        'lazer|viagem|diver|entreten' => '<circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M8 14c1 2 2.500 3 4 3s3-1 4-3"/>',
        'educa|escola|curso|faculdade' => '<path d="m2 9 10-5 10 5-10 5zM6 11.500V17c2 2 10 2 12 0v-5.500"/>',
        'imposto|taxa|tribut' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/>',
        'salario|pessoal|folha' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.500-7 8-7s8 3 8 7"/>',
        'fornecedor' => '<path d="M3 8l9-5 9 5v8l-9 5-9-5zM3 8l9 5 9-5M12 13v8"/>',
        'venda|servico|freela|receita' => '<path d="M4 17l6-6 4 4 6-7M15 8h5v5"/>',
        'investi|poupan|reserva|rendimento' => '<path d="M5 20V10m7 10V4m7 16v-7"/>',
        'conta|energia|agua|internet|telefone|celular' => '<path d="M7 3h10v18l-2.500-1.500L12 21l-2.500-1.500L7 21zM10 8h4m-4 4h4"/>',
    ];
    $path = '<path d="M3 12V4h8l10 10-8 8zM7.500 8.500h.01"/>';
    foreach ($paths as $pattern => $candidate) {
        if (preg_match('/' . $pattern . '/', (string) $text) === 1) {
            $path = $candidate;
            break;
        }
    }
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
};
