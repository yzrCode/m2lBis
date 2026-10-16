<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Vue;
use App\Models\DepotClub;
use DateTimeImmutable;
use DateTimeZone;

/** Export .ics du calendrier d'une équipe (« Ajouter à mon agenda »). */
final class ControleurCalendrier
{
    /** La clé d'équipe est vérifiée dans le dépôt avant de construire le fichier. */
    public static function exporterCalendrier(): void
    {
        $equipe = DepotClub::equipe((string) ($_GET['equipe'] ?? ''));   // liste blanche
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
