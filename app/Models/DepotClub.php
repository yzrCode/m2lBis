<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DatesFrancaises;
use DateTimeImmutable;

//Données publiques du club (site visiteur).
//Implémentation de départ : database/fixtures/club.json.
//À remplacer par vos tables (matchs, classements, actualités et événements).
final class DepotClub
{
    private static function donneesBrutes(): array
    {
        static $cache = null;
        return $cache ??= json_decode((string) file_get_contents(BASE_PATH . '/database/fixtures/club.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function saison(): array
    {
        return self::donneesBrutes()['season'];
    }

    public static function equipe(string $identifiant): ?array
    {
        foreach (self::donneesBrutes()['teams'] as $equipe) {
            if ($equipe['id'] === $identifiant) {
                return $equipe;
            }
        }
        return null;
    }

    //@return array<int,array{0:string,1:string,2:string,3:string,4:string}>
    public static function calendrierBrut(string $identifiantEquipe): array
    {
        return self::donneesBrutes()['calendar'][$identifiantEquipe] ?? [];
    }

    public static function accueil(): array
    {
        $donneesBrutes = self::donneesBrutes();
        $maintenant = new DateTimeImmutable('now');
        $nomEquipe = [];
        foreach ($donneesBrutes['teams'] as $t) {
            $nomEquipe[$t['id']] = $t['name'];
        }

        //Résultats, du plus récent au plus ancien
        $resultats = [];
        foreach ($donneesBrutes['results'] as [$equipe, $dateTexte, $domicile, $exterieur, $scoreDomicile, $scoreExterieur, $competition]) {
            $dateMatch = new DateTimeImmutable($dateTexte);
            $joueDomicile = str_starts_with($domicile, 'M2L');
            $scoreClub = $joueDomicile ? $scoreDomicile : $scoreExterieur;
            $scoreAdversaire = $joueDomicile ? $scoreExterieur : $scoreDomicile;
            $resultats[] = [
                'team' => $equipe, 'teamName' => $nomEquipe[$equipe] ?? $equipe, 'ts' => $dateMatch->getTimestamp(),
                'date' => DatesFrancaises::dateCourte($dateMatch), 'competition' => $competition,
                'home' => $domicile, 'away' => $exterieur, 'hs' => $scoreDomicile, 'as' => $scoreExterieur, 'usHome' => $joueDomicile,
                'outcome' => $scoreClub > $scoreAdversaire ? 'V' : ($scoreClub === $scoreAdversaire ? 'N' : 'D'),
            ];
        }
        usort($resultats, static fn ($a, $b) => $b['ts'] <=> $a['ts']);

        //Classements : J, points et différence calculés, tri officiel
        $classements = [];
        foreach ($donneesBrutes['standings'] as $tableClassement) {
            $lignes = [];
            foreach ($tableClassement['rows'] as [$nomEquipe, $gagnes, $nuls, $perdus, $butsPour, $butsContre, $forme]) {
                $lignes[] = ['name' => $nomEquipe, 'j' => $gagnes + $nuls + $perdus, 'g' => $gagnes, 'n' => $nuls, 'p' => $perdus, 'bp' => $butsPour, 'bc' => $butsContre,
                           'diff' => $butsPour - $butsContre, 'pts' => 3 * $gagnes + $nuls, 'form' => str_split($forme), 'us' => str_starts_with($nomEquipe, 'M2L')];
            }
            usort($lignes, static fn ($a, $b) => [$b['pts'], $b['diff'], $b['bp']] <=> [$a['pts'], $a['diff'], $a['bp']]);
            foreach ($lignes as $indice => &$ligne) {
                $ligne['rank'] = $indice + 1;
            }
            unset($ligne);
            $classements[] = ['id' => $tableClassement['id'], 'label' => $tableClassement['label'], 'competition' => $tableClassement['competition'], 'rows' => $lignes];
        }

        //Calendriers par équipe
        $calendriers = [];
        foreach ($donneesBrutes['calendar'] as $identifiantEquipe => $correspondances) {
            $equipe = self::equipe($identifiantEquipe);
            $calendriers[$identifiantEquipe] = array_map(static function (array $match) use ($equipe) {
                [$dateTexte, $lieu, $adversaire, $stade, $competition] = $match;
                $dateMatch = new DateTimeImmutable($dateTexte);
                $libelleEquipe = 'M2L ' . $equipe['name'];
                $titreMatch = match ($lieu) {
                    'H'     => $libelleEquipe . ' – ' . $adversaire,
                    'A'     => $adversaire . ' – ' . $libelleEquipe,
                    default => $adversaire,
                };
                return ['day' => $dateMatch->format('j'), 'month' => DatesFrancaises::mois($dateMatch, true), 'weekday' => ucfirst(mb_substr(DatesFrancaises::jourSemaine($dateMatch), 0, 3)) . '.',
                        'time' => DatesFrancaises::heure($dateMatch), 'site' => $lieu, 'title' => $titreMatch, 'venue' => $stade, 'competition' => $competition];
            }, $correspondances);
        }

        //Actualités et événements
        $actualites = array_map(static function (array $actualite) {
            $dateActualite = new DateTimeImmutable($actualite['date']);
            return $actualite + ['dateLabel' => $dateActualite->format('j') . ' ' . DatesFrancaises::mois($dateActualite) . ' ' . $dateActualite->format('Y'), 'iso' => $actualite['date']];
        }, $donneesBrutes['news']);

        $evenements = array_map(static function (array $evenement) {
            $dateDebut = new DateTimeImmutable($evenement['start']);
            $plageDates = $evenement['end'] ? $dateDebut->format('j') . ' au ' . (new DateTimeImmutable($evenement['end']))->format('j') . ' ' . DatesFrancaises::mois($dateDebut) : DatesFrancaises::dateLongue($dateDebut);
            return $evenement + ['day' => $dateDebut->format('j'), 'month' => DatesFrancaises::mois($dateDebut, true), 'range' => $plageDates];
        }, $donneesBrutes['events']);

        //Prochain match : compte à rebours calculé sur l'heure du serveur
        $suite = $donneesBrutes['next'];
        $coupEnvoi = new DateTimeImmutable($suite['kickoff']);
        $suite += ['label' => DatesFrancaises::dateLongue($coupEnvoi) . ', ' . DatesFrancaises::heure($coupEnvoi), 'now' => $maintenant->format('c')];

        return [
            'club' => $donneesBrutes['club'], 'season' => $donneesBrutes['season'], 'stats' => $donneesBrutes['stats'], 'teams' => $donneesBrutes['teams'],
            'next' => $suite, 'results' => $resultats, 'standings' => $classements, 'calendars' => $calendriers,
            'news' => $actualites, 'events' => $evenements, 'registration' => $donneesBrutes['registration'],
        ];
    }
}
