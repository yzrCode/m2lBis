<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

require_once BASE_PATH . '/vendor/autoload.php';

if (is_file(BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

//Autoloader minimal : App\Core\Routeur => app/Core/Routeur.php
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $fichier = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});

require __DIR__ . '/Core/helpers.php';
