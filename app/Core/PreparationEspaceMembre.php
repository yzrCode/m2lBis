<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\DepotClub;
use App\Models\DepotTableauBord;

/** Données communes à toutes les pages de l'espace membre (menu, profil, saison). */
final class PreparationEspaceMembre
{
    public static function preparer(array $membre, string $cheminActuel, string $titre, ?array $compteurs = null): array
    {
        $role = $membre['role'];
        $configuration = RegistreRoles::obtenir($role);
        // Réutilise les données déjà chargées par le tableau de bord.
        $compteurs ??= DepotTableauBord::pourRole($role)['counts'] ?? [];

        $menu = array_map(static fn (array $element) => $element + [
            'current' => $element['href'] === $cheminActuel,
            'count'   => $compteurs[$element['href']] ?? null,
        ], $configuration['menu']);

        return [
            'user'          => $membre,
            'fullName'      => $membre['prenom'] . ' ' . $membre['nom'],
            'roleLabel'     => $configuration['label'],
            'menu'          => $menu,
            'primaryAction' => $configuration['primaryAction'],
            'season'        => DepotClub::saison(),
            'pageTitle'     => $titre,
            'flash'         => SessionUtilisateur::recupererMessageFlash(),
        ];
    }
}
