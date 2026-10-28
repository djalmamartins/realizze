<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/source/Boot/app.php';

use Moves\Support\Pager;
use Moves\Support\Seo;
use Moves\Support\Thumb;
use MovesCode\Pager\Pager as MovesPager;
use MovesCode\Seo\Seo as MovesSeo;
use MovesCode\Cropper\Cropper;

function check(bool $condition, string $message): void
{
    echo ($condition ? 'OK' : 'FALHOU') . ' - ' . $message . PHP_EOL;
    if (!$condition) { exit(1); }
}

$pager = new Pager('/blog?page=');
check($pager->engine() instanceof MovesPager, 'Pager reutiliza MovesCode');
$pager->pager(45, 10, 2);
check($pager->page() === 2 && $pager->limit() === 10 && $pager->offset() === 10, 'Paginação');
check($pager->pages() === 5, 'Total de páginas');

$seo = new Seo('website');
check($seo->optimizer() instanceof MovesSeo, 'SEO reutiliza MovesCode');
$meta = $seo->render('Realizze', 'Gestão de condomínios', 'https://example.com', 'https://example.com/logo.png');
check(is_string($meta) && $meta !== '', 'SEO renderiza metadados');

$base = sys_get_temp_dir() . '/realizze-thumb-' . bin2hex(random_bytes(6));
mkdir($base . '/uploads', 0700, true);
mkdir($base . '/cache', 0700, true);
try {
    $thumb = new Thumb($base . '/uploads', $base . '/cache');
    check($thumb->cropper() instanceof Cropper, 'Thumb reutiliza MovesCode');
    try {
        $thumb->make('../outside.jpg', 100);
        check(false, 'Traversal bloqueado');
    } catch (InvalidArgumentException) {
        check(true, 'Traversal bloqueado');
    }
    try {
        $thumb->make('foto.jpg', 0);
        check(false, 'Dimensão inválida bloqueada');
    } catch (InvalidArgumentException) {
        check(true, 'Dimensão inválida bloqueada');
    }
} finally {
    @rmdir($base . '/uploads');
    @rmdir($base . '/cache');
    @rmdir($base);
}

echo 'TESTE ADAPTADORES: APROVADO' . PHP_EOL;
