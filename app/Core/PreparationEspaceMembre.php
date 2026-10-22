<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\DepotCotisation;
use App\Models\DepotTableauBord;
use DateTimeImmutable;

//Données communes à toutes les pages de l'espace membre (menu, profil, saison).
final class PreparationEspaceMembre
{
    public static function preparer(array $membre, string $cheminActuel, string $titre, ?array $compteurs = null): array
    {
        $role = $membre['role'];
        $configuration = RegistreRoles::obtenir($role);
        //Réutilise les données déjà chargées par le tableau de bord.
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
            'season'        => self::saisonCourante(),
            'pageTitle'     => $titre,
            'flash'         => SessionUtilisateur::recupererMessageFlash(),
        ];
    }

    //La saison du menu vient de MySQL, pas des données de démonstration publiques.
    private static function saisonCourante(): array
    {
        foreach (DepotCotisation::saisons() as $saison) {
            if ((int) $saison['en_cours'] !== 1) {
                continue;
            }

            $debut = new DateTimeImmutable($saison['date_debut']);
            $fin = new DateTimeImmutable($saison['date_fin']);
            $aujourdHui = new DateTimeImmutable('today');
            $duree = max((int) $debut->diff($fin)->days, 1);
            $joursEcoules = max(0, min($duree, (int) $debut->diff($aujourdHui)->format('%r%a')));
            $pourcentage = (int) round($joursEcoules / $duree * 100);

            return [
                'label' => 'Saison ' . $saison['libelle'],
                'progress' => [
                    'label' => $pourcentage . ' % de la saison écoulée',
                    'percent' => $pourcentage,
                ],
            ];
        }

        return [
            'label' => 'Aucune saison en cours',
            'progress' => [
                'label' => 'La progression sera affichée lorsqu’une saison sera active.',
                'percent' => 0,
            ],
        ];
    }
}
