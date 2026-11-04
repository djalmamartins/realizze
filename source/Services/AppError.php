<?php
declare(strict_types=1);

namespace Moves\Services;

/** Resposta de erro segura e consistente exclusiva do App. */
final class AppError
{
    public static function render(int $status): void
    {
        $status = in_array($status, [403, 404, 500], true) ? $status : 500;
        http_response_code($status);
        header('Cache-Control: no-store, private');
        require BASE_PATH . '/container/app/realizze/v1/pages/error.php';
    }
}
