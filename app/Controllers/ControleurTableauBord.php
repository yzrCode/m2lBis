<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\Vue;
use App\Models\DepotTableauBord;

//Assemble les données du rôle connecté avant de les transmettre au gabarit du tableau de bord.
final class ControleurTableauBord
{
    public static function afficher(): void
    {
        $membre = Authentification::exigerConnexion();
        $donnees = DepotTableauBord::pourRole($membre['role'], (int) $membre['id']);

        //Les données du rôle connecté sont transmises à la vue et au rendu JavaScript.
        $donneesTableauBord = array_diff_key($donnees, ['lead' => 1, 'counts' => 1]);
        $heure = (int) date('G');

        Vue::afficher('dashboard/index', PreparationEspaceMembre::preparer(
            $membre,
            '/tableau-de-bord',
            'Tableau de bord',
            $donnees['counts'] ?? []
        ) + [
            'salutation' => ($heure >= 18 || $heure < 5) ? 'Bonsoir' : 'Bonjour',
            'lead'    => $donnees['lead'],
            'donneesTableauBord' => $donneesTableauBord,
        ], 'app');
    }
}
