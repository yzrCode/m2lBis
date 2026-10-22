<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotUtilisateurs;

//Permet aux nouveaux licenciés de remplacer leur mot de passe provisoire.
final class ControleurMotDePasse
{
    public static function afficher(): void
    {
        $membre = Authentification::exigerConnexion();
        if (empty($membre['doit_changer_mot_de_passe'])) {
            rediriger('/tableau-de-bord');
        }

        Vue::afficher('auth/change-password', [
            'title' => 'Changer le mot de passe provisoire',
            'flash' => SessionUtilisateur::recupererMessageFlash(),
        ], 'plain');
    }

    public static function enregistrer(): void
    {
        $membre = Authentification::exigerConnexion();
        if (empty($membre['doit_changer_mot_de_passe'])) {
            rediriger('/tableau-de-bord');
        }
        if (!ProtectionCsrf::verifierRequete()) {
            self::echouer('Session expirée, veuillez réessayer.');
        }

        $motDePasse = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (strlen($motDePasse) < 12 || strlen($motDePasse) > 72) {
            self::echouer('Le mot de passe doit contenir entre 12 et 72 caractères.');
        }
        if (!hash_equals($motDePasse, $confirmation)) {
            self::echouer('Les deux mots de passe ne correspondent pas.');
        }

        $modificationEnregistree = DepotUtilisateurs::changerMotDePasseProvisoire(
            (int) $membre['id'],
            password_hash($motDePasse, PASSWORD_DEFAULT)
        );
        if (!$modificationEnregistree) {
            self::echouer('Le mot de passe n’a pas pu être changé. Reconnectez-vous puis réessayez.');
        }

        $_SESSION['user']['doit_changer_mot_de_passe'] = false;
        SessionUtilisateur::messageFlash('success', 'Votre nouveau mot de passe est enregistré.');
        rediriger('/tableau-de-bord');
    }

    private static function echouer(string $message): never
    {
        SessionUtilisateur::messageFlash('error', $message);
        rediriger('/mot-de-passe-provisoire');
    }
}
