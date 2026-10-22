<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use App\Core\DatesFrancaises;
use DateTimeImmutable;

//Depot qui rassemble les événements du club et les matchs utiles au membre connecté.
final class DepotCalendrier
{
    //Retourne les matchs et événements compris dans la période demandée.
    public static function pourMembre(string $role, int $utilisateurId, string $debut, string $fin): array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $evenements = self::evenements($connexion, $debut, $fin);
        $matchs = self::matchs($connexion, $role, $utilisateurId, $debut, $fin);
        $elements = array_merge($evenements, $matchs);
        usort($elements, static fn (array $a, array $b): int => $a['date_heure'] <=> $b['date_heure']);

        return array_map(static function (array $element): array {
            $date = new DateTimeImmutable($element['date_heure']);
            return $element + [
                'date' => $date->format('Y-m-d'),
                'heure' => DatesFrancaises::heure($date),
                'date_affichage' => DatesFrancaises::dateCourte($date),
            ];
        }, $elements);
    }

    //Retourne les prochains événements du membre pour l'agenda du tableau de bord.
    public static function prochains(string $role, int $utilisateurId, int $limite = 4): array
    {
        $debut = new DateTimeImmutable('today');
        $fin = $debut->modify('+4 months');
        $elements = self::pourMembre(
            $role,
            $utilisateurId,
            $debut->format('Y-m-d 00:00:00'),
            $fin->format('Y-m-d 23:59:59')
        );

        return array_slice($elements, 0, $limite);
    }

    //Récupère les événements du club enregistrés en base.
    private static function evenements(\PDO $connexion, string $debut, string $fin): array
    {
        $requete = $connexion->prepare(
            'SELECT titre_evenements AS titre, lieu, date_debut AS date_heure
             FROM evenements
             WHERE date_debut >= :debut AND date_debut <= :fin
             ORDER BY date_debut'
        );
        $requete->execute(['debut' => $debut, 'fin' => $fin]);

        return array_map(static fn (array $ligne): array => [
            'titre' => $ligne['titre'],
            'lieu' => $ligne['lieu'] ?? '',
            'date_heure' => $ligne['date_heure'],
            'type' => 'evenement',
        ], $requete->fetchAll());
    }

    //Les joueurs et éducateurs ne voient que les matchs liés à leur équipe.
    private static function matchs(
        \PDO $connexion,
        string $role,
        int $utilisateurId,
        string $debut,
        string $fin
    ): array {
        $conditionsPersonnelles = '';
        $parametres = ['debut' => $debut, 'fin' => $fin];

        if ($role === 'licencie') {
            $conditionsPersonnelles = ' AND (
                EXISTS (
                    SELECT 1 FROM equipes_joueurs ej
                    WHERE ej.equipe_id = e.id_equipes AND ej.joueur_id = :joueur_equipe
                )
                OR EXISTS (
                    SELECT 1 FROM convocations c
                    WHERE c.match_id = m.id_matchs AND c.joueur_id = :joueur_convocation
                )
            )';
            $parametres['joueur_equipe'] = $utilisateurId;
            $parametres['joueur_convocation'] = $utilisateurId;
        } elseif ($role === 'educateur') {
            $conditionsPersonnelles = ' AND e.educateur_id = :educateur';
            $parametres['educateur'] = $utilisateurId;
        }

        $requete = $connexion->prepare(
            'SELECT e.nom_equipes, m.adversaire, m.domicile, m.lieu, m.date_heure
             FROM matchs m
             INNER JOIN equipes e ON e.id_equipes = m.equipe_id
             WHERE m.date_heure >= :debut AND m.date_heure <= :fin'
             . $conditionsPersonnelles
             . ' ORDER BY m.date_heure'
        );
        $requete->execute($parametres);

        return array_map(static function (array $ligne): array {
            $nomEquipe = 'M2L ' . $ligne['nom_equipes'];
            $titre = (bool) $ligne['domicile']
                ? $nomEquipe . ' – ' . $ligne['adversaire']
                : $ligne['adversaire'] . ' – ' . $nomEquipe;

            return [
                'titre' => $titre,
                'lieu' => $ligne['lieu'] ?? '',
                'date_heure' => $ligne['date_heure'],
                'type' => 'match',
            ];
        }, $requete->fetchAll());
    }
}
