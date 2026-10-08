<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

// Autoloader minimal : App\Core\Router => app/Core/Router.php
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/Core/helpers.php';
