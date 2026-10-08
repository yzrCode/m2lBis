<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Models\ClubRepository;

/** Pré-inscription en ligne depuis l'accueil public. */
final class InscriptionController
{
    public static function store(): void
    {
        $allowed = array_column(ClubRepository::home()['registration']['prices'], 'cat');
        $input = [
            'prenom'    => trim((string) ($_POST['prenom'] ?? '')),
            'nom'       => trim((string) ($_POST['nom'] ?? '')),
            'email'     => trim((string) ($_POST['email'] ?? '')),
            'categorie' => (string) ($_POST['categorie'] ?? ''),
            'message'   => trim((string) ($_POST['message'] ?? '')),
        ];

        if (!Csrf::checkRequest()) {
            self::fail($input, 'Session expirée, veuillez réessayer.');
        }
        if (($_POST['website'] ?? '') !== '') {          // champ piège anti-robots
            redirect('/#inscriptions');
        }
        $recent = array_filter($_SESSION['inscriptions'] ?? [], static fn ($t) => $t > time() - 3600);
        if (count($recent) >= 3) {
            self::fail($input, 'Trop de demandes envoyées. Réessayez plus tard.');
        }
        if ($input['prenom'] === '' || $input['nom'] === '' || mb_strlen($input['prenom'] . $input['nom']) > 120) {
            self::fail($input, 'Indiquez un prénom et un nom.');
        }
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            self::fail($input, "L'adresse e-mail n'est pas valide.");
        }
        if (!in_array($input['categorie'], $allowed, true)) {
            self::fail($input, 'Choisissez une catégorie.');
        }
        $input['message'] = mb_substr($input['message'], 0, 500);

        $recent[] = time();
        $_SESSION['inscriptions'] = array_values($recent);
        @file_put_contents(BASE_PATH . '/storage/inscriptions.jsonl',
            json_encode(['at' => date('c')] + $input, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        // TODO métier : créer le dossier en base et prévenir le secrétariat par e-mail.

        Session::flash('success', 'Merci ' . $input['prenom'] . ', votre demande est bien reçue. Le secrétariat vous répond sous 48 heures.');
        redirect('/#inscriptions');
    }

    private static function fail(array $input, string $message): never
    {
        Session::keepOld($input);
        Session::flash('error', $message);
        redirect('/#inscriptions');
    }
}
