<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;
use App\Models\DepotClub;

//Pré-inscription en ligne depuis l'accueil public.
final class ControleurInscription
{
    public static function enregistrer(): void
    {
        $valeursAutorisees = array_column(DepotClub::accueil()['registration']['prices'], 'cat');
        $saisie = [
            'prenom'    => trim((string) ($_POST['prenom'] ?? '')),
            'nom'       => trim((string) ($_POST['nom'] ?? '')),
            'email'     => trim((string) ($_POST['email'] ?? '')),
            'categorie' => (string) ($_POST['categorie'] ?? ''),
            'message'   => trim((string) ($_POST['message'] ?? '')),
        ];

        if (!ProtectionCsrf::verifierRequete()) {
            self::echouer($saisie, 'Session expirée, veuillez réessayer.');
        }
        if (($_POST['website'] ?? '') !== '') {          //champ piège anti-robots
            rediriger('/#inscriptions');
        }
        $envoisRecents = array_filter($_SESSION['inscriptions'] ?? [], static fn ($t) => $t > time() - 3600);
        if (count($envoisRecents) >= 3) {
            self::echouer($saisie, 'Trop de demandes envoyées. Réessayez plus tard.');
        }
        if ($saisie['prenom'] === '' || $saisie['nom'] === '' || mb_strlen($saisie['prenom'] . $saisie['nom']) > 120) {
            self::echouer($saisie, 'Indiquez un prénom et un nom.');
        }
        if (!filter_var($saisie['email'], FILTER_VALIDATE_EMAIL)) {
            self::echouer($saisie, "L'adresse e-mail n'est pas valide.");
        }
        if (!in_array($saisie['categorie'], $valeursAutorisees, true)) {
            self::echouer($saisie, 'Choisissez une catégorie.');
        }
        $saisie['message'] = mb_substr($saisie['message'], 0, 500);

        $envoisRecents[] = time();
        $_SESSION['inscriptions'] = array_values($envoisRecents);
        @file_put_contents(BASE_PATH . '/storage/inscriptions.jsonl',
            json_encode(['at' => date('c')] + $saisie, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        //À relier au métier : créer le dossier en base et prévenir le secrétariat par courriel.

        SessionUtilisateur::messageFlash('success', 'Merci ' . $saisie['prenom'] . ', votre demande est bien reçue. Le secrétariat vous répond sous 48 heures.');
        rediriger('/#inscriptions');
    }

    private static function echouer(array $saisie, string $message): never
    {
        SessionUtilisateur::conserverAncienneSaisie($saisie);
        SessionUtilisateur::messageFlash('error', $message);
        rediriger('/#inscriptions');
    }
}
