<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\RegistreRoles;
use App\Core\Vue;
use App\Models\DepotClub;
use App\Models\DepotCalendrier;
use DateTimeImmutable;
use DateTimeZone;

//Export .ics du calendrier d'une équipe (« Ajouter à mon agenda »).
final class ControleurCalendrier
{
    //Affiche le calendrier des événements et matchs liés au membre connecté.
    public static function afficher(): void
    {
        $membre = Authentification::exigerConnexion();
        if (RegistreRoles::elementMenu($membre['role'], '/calendrier') === null) {
            Vue::afficher('errors/error', [
                'status' => 403,
                'title' => 'Accès refusé',
                'text' => "Cette fonction n'est pas disponible pour votre rôle.",
                'member' => $membre,
            ], 'plain', 403);
            return;
        }

        $moisDemande = $_GET['mois'] ?? date('Y-m');
        $moisDemande = is_string($moisDemande) && preg_match('/^\d{4}-\d{2}$/', $moisDemande)
            ? $moisDemande
            : date('Y-m');
        $debutMois = DateTimeImmutable::createFromFormat('!Y-m-d', $moisDemande . '-01');
        if ($debutMois === false || $debutMois->format('Y-m') !== $moisDemande) {
            $debutMois = new DateTimeImmutable('first day of this month');
        }

        $finMois = $debutMois->modify('last day of this month');
        $evenements = DepotCalendrier::pourMembre(
            $membre['role'],
            (int) $membre['id'],
            $debutMois->format('Y-m-d 00:00:00'),
            $finMois->format('Y-m-d 23:59:59')
        );
        $evenementsParJour = [];
        foreach ($evenements as $evenement) {
            $evenementsParJour[$evenement['date']][] = $evenement;
        }

        $decalage = (int) $debutMois->format('N') - 1;
        $jours = (int) $finMois->format('j');
        $grille = array_fill(0, $decalage, null);
        for ($jour = 1; $jour <= $jours; $jour++) {
            $grille[] = [
                'numero' => $jour,
                'date' => $debutMois->format('Y-m-') . str_pad((string) $jour, 2, '0', STR_PAD_LEFT),
                'evenements' => $evenementsParJour[
                    $debutMois->format('Y-m-') . str_pad((string) $jour, 2, '0', STR_PAD_LEFT)
                ] ?? [],
            ];
        }
        while (count($grille) % 7 !== 0) {
            $grille[] = null;
        }

        Vue::afficher('calendrier/index', PreparationEspaceMembre::preparer(
            $membre,
            '/calendrier',
            'Calendrier'
        ) + [
            'mois' => $debutMois,
            'moisPrecedent' => $debutMois->modify('-1 month')->format('Y-m'),
            'moisSuivant' => $debutMois->modify('+1 month')->format('Y-m'),
            'grille' => $grille,
            'evenements' => $evenements,
        ], 'app');
    }

    //La clé d'équipe est vérifiée dans le dépôt avant de construire le fichier.
    public static function exporterCalendrier(): void
    {
        $equipe = DepotClub::equipe((string) ($_GET['equipe'] ?? ''));   //liste blanche
        if ($equipe === null) {
            Vue::afficher('errors/error', ['status' => 404, 'title' => 'Page introuvable',
                'text' => "Cette équipe n'existe pas.", 'member' => null], 'plain', 404);
            return;
        }
        $echapperTexte = static fn (string $texte): string => str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $texte);
        $fuseauUtc = new DateTimeZone('UTC');
        $lignesCalendrier = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//M2L Football Club//Calendrier//FR', 'CALSCALE:GREGORIAN',
                  'X-WR-CALNAME:' . $echapperTexte('M2L ' . $equipe['name']), 'X-WR-TIMEZONE:Europe/Paris'];
        foreach (DepotClub::calendrierBrut($equipe['id']) as $indice => [$dateTexte, $lieu, $adversaire, $stade, $competition]) {
            $dateDebut = new DateTimeImmutable($dateTexte);
            $libelleEquipe = 'M2L ' . $equipe['name'];
            $titreMatch = match ($lieu) { 'H' => "$libelleEquipe – $adversaire", 'A' => "$adversaire – $libelleEquipe", default => $adversaire };
            array_push($lignesCalendrier, 'BEGIN:VEVENT',
                'UID:' . $equipe['id'] . '-' . $dateDebut->format('Ymd\THi') . '-' . $indice . '@m2l-fc.fr',
                'DTSTAMP:' . gmdate('Ymd\THis\Z'),
                'DTSTART:' . $dateDebut->setTimezone($fuseauUtc)->format('Ymd\THis\Z'),
                'DTEND:' . $dateDebut->modify('+2 hours')->setTimezone($fuseauUtc)->format('Ymd\THis\Z'),
                'SUMMARY:' . $echapperTexte($titreMatch), 'LOCATION:' . $echapperTexte($stade), 'DESCRIPTION:' . $echapperTexte($competition), 'END:VEVENT');
        }
        $lignesCalendrier[] = 'END:VCALENDAR';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="m2l-' . $equipe['id'] . '.ics"');
        echo implode("\r\n", $lignesCalendrier) . "\r\n";
    }
}
