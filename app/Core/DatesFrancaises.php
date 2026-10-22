<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeInterface;

//Dates en français sans dépendre de l'extension intl.
final class DatesFrancaises
{
    private const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    public static function jourSemaine(DateTimeInterface $d): string
    {
        return self::DAYS[(int) $d->format('w')];
    }

    public static function mois(DateTimeInterface $d, bool $short = false): string
    {
        $i = (int) $d->format('n') - 1;
        return $short ? self::MONTHS_SHORT[$i] : self::MONTHS[$i];
    }

    //« Dimanche 4 octobre »
    public static function dateLongue(DateTimeInterface $d): string
    {
        return ucfirst(self::jourSemaine($d)) . ' ' . $d->format('j') . ' ' . self::mois($d);
    }

    //« 4 oct. »
    public static function dateCourte(DateTimeInterface $d): string
    {
        return $d->format('j') . ' ' . self::mois($d, true);
    }

    //« 15h00 »
    public static function heure(DateTimeInterface $d): string
    {
        return $d->format('G\hi');
    }
}
