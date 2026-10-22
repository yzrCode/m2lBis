<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\RegistreRoles;
use App\Core\PreparationEspaceMembre;
use App\Core\Vue;

//Sections de l'espace membre (menu). Contrôle d'accès RBAC côté serveur :
//une section absente du menu du rôle renvoie 403 (existe ailleurs) ou 404.
final class ControleurModule
{
    public static function afficherSection(string $chemin): void
    {
        $membre = Authentification::membreConnecte();
        $known = RegistreRoles::cheminConnuDansAutreRole($chemin);

        if ($membre === null) {
            $known ? rediriger('/connexion?next=' . urlencode($chemin)) : self::afficherErreur(404);
            return;
        }
        $item = RegistreRoles::elementMenu($membre['role'], $chemin);
        if ($item === null) {
            self::afficherErreur($known ? 403 : 404, $membre);
            return;
        }
        Vue::afficher('module', PreparationEspaceMembre::preparer($membre, $chemin, $item['label']) + ['item' => $item], 'app');
    }

    private static function afficherErreur(int $codeHttp, ?array $membre = null): void
    {
        Vue::afficher('errors/error', [
            'status' => $codeHttp,
            'title'  => $codeHttp === 403 ? 'Accès refusé' : 'Page introuvable',
            'text'   => $codeHttp === 403
                ? "Cette section n'est pas disponible pour votre rôle."
                : "La page demandée n'existe pas ou a été déplacée.",
            'member' => $membre,
        ], 'plain', $codeHttp);
    }
}
