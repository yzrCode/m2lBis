<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotClub;

final class ControleurAccueil
{
    /** Accueil public : vie sportive et informations pratiques. */
    public static function afficher(): void
    {
        Vue::afficher('home', DepotClub::accueil() + [
            'member' => Authentification::membreConnecte(),
            'flash'  => SessionUtilisateur::recupererMessageFlash(),
            'old'    => SessionUtilisateur::recupererAncienneSaisie(),
        ], 'site');
    }

    public static function pageIntrouvable(string $chemin): void
    {
        // Une section de l'espace membre est gérée par ControleurModule ; le reste est une 404.
        ControleurModule::afficherSection($chemin);
    }
}
