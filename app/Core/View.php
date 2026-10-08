<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = null, int $status = 200): void
    {
        http_response_code($status);
        $content = self::capture($view, $data);
        echo $layout !== null ? self::capture('layouts/' . $layout, $data + ['content' => $content]) : $content;
    }

    private static function capture(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/app/Views/' . $view . '.php';
        return (string) ob_get_clean();
    }
}
