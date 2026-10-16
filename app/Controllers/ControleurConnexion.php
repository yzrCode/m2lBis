<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;
use App\Core\Vue;

/** Affiche le formulaire de connexion et traite sa soumission. */
final class ControleurConnexion
{
    public static function afficherConnexion(): void
    {
        if (Authentification::membreConnecte() !== null) {
            rediriger('/tableau-de-bord');
        }
        Vue::afficher('auth/login', [
            'next'   => (string) ($_GET['next'] ?? ''),
            'flash'  => SessionUtilisateur::recupererMessageFlash(),
            'email'  => SessionUtilisateur::recupererAncienneSaisie()['email'] ?? '',
            'connexionVerrouillee' => Authentification::connexionVerrouillee(),
        ], 'plain');
    }

    public static function traiterConnexion(): void
    {
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/connexion');
        }
        $courriel = trim((string) ($_POST['email'] ?? ''));
        $motDePasse = (string) ($_POST['password'] ?? '');

        if (Authentification::verifierIdentifiants($courriel, $motDePasse)) {
            rediriger(self::destinationInterneSure((string) ($_POST['next'] ?? '')));
        }
        SessionUtilisateur::conserverAncienneSaisie(['email' => $courriel]);
        SessionUtilisateur::messageFlash('error', Authentification::connexionVerrouillee()
            ? 'Trop de tentatives. Réessayez dans une minute.'
            : 'E-mail ou mot de passe incorrect.');
        rediriger('/connexion' . ($_POST['next'] ?? '' ? '?next=' . urlencode((string) $_POST['next']) : ''));
    }

    public static function deconnecter(): void
    {
        if (ProtectionCsrf::verifierRequete()) {
            Authentification::deconnecter();
        }
        rediriger('/');
    }

    /** Refuse les adresses externes pour éviter de rediriger un membre vers un site malveillant. */
    private static function destinationInterneSure(string $suite): string
    {
        return ($suite !== '' && $suite[0] === '/' && !str_starts_with($suite, '//') && !str_contains($suite, '\\'))
            ? $suite : '/tableau-de-bord';
    }
}
