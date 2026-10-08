<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Fr;
use DateTimeImmutable;

/**
 * Données publiques du club (site visiteur).
 * Implémentation de départ : database/fixtures/club.json.
 * À remplacer par vos tables (matchs, classements, actualités, événements, boutique).
 */
final class ClubRepository
{
    private static function raw(): array
    {
        static $cache = null;
        return $cache ??= json_decode((string) file_get_contents(BASE_PATH . '/database/fixtures/club.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function season(): array
    {
        return self::raw()['season'];
    }

    public static function team(string $id): ?array
    {
        foreach (self::raw()['teams'] as $team) {
            if ($team['id'] === $id) {
                return $team;
            }
        }
        return null;
    }

    /** @return array<int,array{0:string,1:string,2:string,3:string,4:string}> */
    public static function calendarRaw(string $teamId): array
    {
        return self::raw()['calendar'][$teamId] ?? [];
    }

    public static function home(): array
    {
        $raw = self::raw();
        $now = new DateTimeImmutable('now');
        $teamName = [];
        foreach ($raw['teams'] as $t) {
            $teamName[$t['id']] = $t['name'];
        }

        // Résultats, du plus récent au plus ancien
        $results = [];
        foreach ($raw['results'] as [$team, $date, $home, $away, $hs, $as, $comp]) {
            $d = new DateTimeImmutable($date);
            $usHome = str_starts_with($home, 'M2L');
            $us = $usHome ? $hs : $as;
            $them = $usHome ? $as : $hs;
            $results[] = [
                'team' => $team, 'teamName' => $teamName[$team] ?? $team, 'ts' => $d->getTimestamp(),
                'date' => Fr::short($d), 'competition' => $comp,
                'home' => $home, 'away' => $away, 'hs' => $hs, 'as' => $as, 'usHome' => $usHome,
                'outcome' => $us > $them ? 'V' : ($us === $them ? 'N' : 'D'),
            ];
        }
        usort($results, static fn ($a, $b) => $b['ts'] <=> $a['ts']);

        // Classements : J, points et différence calculés, tri officiel
        $standings = [];
        foreach ($raw['standings'] as $table) {
            $rows = [];
            foreach ($table['rows'] as [$name, $g, $n, $p, $bp, $bc, $form]) {
                $rows[] = ['name' => $name, 'j' => $g + $n + $p, 'g' => $g, 'n' => $n, 'p' => $p, 'bp' => $bp, 'bc' => $bc,
                           'diff' => $bp - $bc, 'pts' => 3 * $g + $n, 'form' => str_split($form), 'us' => str_starts_with($name, 'M2L')];
            }
            usort($rows, static fn ($a, $b) => [$b['pts'], $b['diff'], $b['bp']] <=> [$a['pts'], $a['diff'], $a['bp']]);
            foreach ($rows as $i => &$row) {
                $row['rank'] = $i + 1;
            }
            unset($row);
            $standings[] = ['id' => $table['id'], 'label' => $table['label'], 'competition' => $table['competition'], 'rows' => $rows];
        }

        // Calendriers par équipe
        $calendars = [];
        foreach ($raw['calendar'] as $teamId => $matches) {
            $team = self::team($teamId);
            $calendars[$teamId] = array_map(static function (array $m) use ($team) {
                [$date, $site, $opp, $venue, $comp] = $m;
                $d = new DateTimeImmutable($date);
                $label = 'M2L ' . $team['name'];
                $title = match ($site) {
                    'H'     => $label . ' – ' . $opp,
                    'A'     => $opp . ' – ' . $label,
                    default => $opp,
                };
                return ['day' => $d->format('j'), 'month' => Fr::month($d, true), 'weekday' => ucfirst(mb_substr(Fr::weekday($d), 0, 3)) . '.',
                        'time' => Fr::time($d), 'site' => $site, 'title' => $title, 'venue' => $venue, 'competition' => $comp];
            }, $matches);
        }

        // Actualités et événements
        $news = array_map(static function (array $n) {
            $d = new DateTimeImmutable($n['date']);
            return $n + ['dateLabel' => $d->format('j') . ' ' . Fr::month($d) . ' ' . $d->format('Y'), 'iso' => $n['date']];
        }, $raw['news']);

        $events = array_map(static function (array $e) {
            $s = new DateTimeImmutable($e['start']);
            $range = $e['end'] ? $s->format('j') . ' au ' . (new DateTimeImmutable($e['end']))->format('j') . ' ' . Fr::month($s) : Fr::long($s);
            return $e + ['day' => $s->format('j'), 'month' => Fr::month($s, true), 'range' => $range];
        }, $raw['events']);

        // Prochain match : compte à rebours calculé sur l'heure du serveur
        $next = $raw['next'];
        $k = new DateTimeImmutable($next['kickoff']);
        $next += ['label' => Fr::long($k) . ', ' . Fr::time($k), 'now' => $now->format('c')];

        return [
            'club' => $raw['club'], 'season' => $raw['season'], 'stats' => $raw['stats'], 'teams' => $raw['teams'],
            'next' => $next, 'results' => $results, 'standings' => $standings, 'calendars' => $calendars,
            'news' => $news, 'events' => $events, 'registration' => $raw['registration'], 'shop' => $raw['shop'],
        ];
    }
}
