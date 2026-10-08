<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Roles;
use App\Core\Shell;
use App\Core\View;

/**
 * Sections de l'espace membre (menu). Contrôle d'accès RBAC côté serveur :
 * une section absente du menu du rôle renvoie 403 (existe ailleurs) ou 404.
 */
final class ModuleController
{
    public static function fallback(string $path): void
    {
        $user = Auth::user();
        $known = Roles::pathExistsElsewhere($path);

        if ($user === null) {
            $known ? redirect('/connexion?next=' . urlencode($path)) : self::error(404);
            return;
        }
        $item = Roles::menuItem($user['role'], $path);
        if ($item === null) {
            self::error($known ? 403 : 404, $user);
            return;
        }
        View::render('module', Shell::build($user, $path, $item['label']) + ['item' => $item], 'app');
    }

    private static function error(int $status, ?array $user = null): void
    {
        View::render('errors/error', [
            'status' => $status,
            'title'  => $status === 403 ? 'Accès refusé' : 'Page introuvable',
            'text'   => $status === 403
                ? "Cette section n'est pas disponible pour votre rôle."
                : "La page demandée n'existe pas ou a été déplacée.",
            'member' => $user,
        ], 'plain', $status);
    }
}
