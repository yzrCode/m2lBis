<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ClubRepository;
use DateTimeImmutable;
use DateTimeZone;

/** Export .ics du calendrier d'une équipe (« Ajouter à mon agenda »). */
final class CalendarController
{
    public static function ics(): void
    {
        $team = ClubRepository::team((string) ($_GET['equipe'] ?? ''));   // liste blanche
        if ($team === null) {
            View::render('errors/error', ['status' => 404, 'title' => 'Page introuvable',
                'text' => "Cette équipe n'existe pas.", 'member' => null], 'plain', 404);
            return;
        }
        $esc = static fn (string $s): string => str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s);
        $utc = new DateTimeZone('UTC');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//M2L Football Club//Calendrier//FR', 'CALSCALE:GREGORIAN',
                  'X-WR-CALNAME:' . $esc('M2L ' . $team['name']), 'X-WR-TIMEZONE:Europe/Paris'];
        foreach (ClubRepository::calendarRaw($team['id']) as $i => [$date, $site, $opp, $venue, $comp]) {
            $start = new DateTimeImmutable($date);
            $label = 'M2L ' . $team['name'];
            $title = match ($site) { 'H' => "$label – $opp", 'A' => "$opp – $label", default => $opp };
            array_push($lines, 'BEGIN:VEVENT',
                'UID:' . $team['id'] . '-' . $start->format('Ymd\THi') . '-' . $i . '@m2l-fc.fr',
                'DTSTAMP:' . gmdate('Ymd\THis\Z'),
                'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
                'DTEND:' . $start->modify('+2 hours')->setTimezone($utc)->format('Ymd\THis\Z'),
                'SUMMARY:' . $esc($title), 'LOCATION:' . $esc($venue), 'DESCRIPTION:' . $esc($comp), 'END:VEVENT');
        }
        $lines[] = 'END:VCALENDAR';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="m2l-' . $team['id'] . '.ics"');
        echo implode("\r\n", $lines) . "\r\n";
    }
}
