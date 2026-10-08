<?php
declare(strict_types=1);

// Serveur PHP intégré : laisse passer les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\{ActionController, AuthController, CalendarController, DashboardController, HomeController, ImportController, InscriptionController};
use App\Core\{Router, Session};

Session::start();

// En-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; frame-ancestors 'none'; form-action 'self'");

$router = new Router();

// Public
$router->get('/', [HomeController::class, 'index']);
$router->get('/calendrier.ics', [CalendarController::class, 'ics']);
$router->post('/inscription', [InscriptionController::class, 'store']);

// Authentification
$router->get('/connexion', [AuthController::class, 'showLogin']);
$router->post('/connexion', [AuthController::class, 'login']);
$router->post('/deconnexion', [AuthController::class, 'logout']);

// Espace membre
$router->get('/tableau-de-bord', [DashboardController::class, 'index']);
$router->post('/api/actions', [ActionController::class, 'handle']);
$router->post('/licencies/import', [ImportController::class, 'store']);

// Sections du menu (RBAC) puis 404
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], [HomeController::class, 'notFound']);
