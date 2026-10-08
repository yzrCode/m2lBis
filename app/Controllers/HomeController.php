<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\ClubRepository;

final class HomeController
{
    /** Accueil public : vie sportive et informations pratiques. */
    public static function index(): void
    {
        View::render('home', ClubRepository::home() + [
            'member' => Auth::user(),
            'flash'  => Session::pullFlash(),
            'old'    => Session::pullOld(),
        ], 'site');
    }

    public static function notFound(string $path): void
    {
        // Une section de l'espace membre est gérée par ModuleController ; le reste est une 404.
        ModuleController::fallback($path);
    }
}
