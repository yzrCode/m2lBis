<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\ProtectionCsrf;
use App\Core\RegistreRoles;
use App\Core\SessionUtilisateur;
use App\Core\PreparationEspaceMembre;
use App\Core\Vue;
use App\Models\DepotDemandesCompte;

//Reçoit les demandes de compte et réserve leur validation au président.
final class ControleurDemandesCompte
{
    //Un visiteur ne peut pas demander les rôles de président ou de licencié.
    private const ROLES_DEMANDABLES = [
        'educateur',
        'secretaire',
        'tresorier',
        'service_civique',
        'arbitre',
        'resp_evenementiel',
        'resp_achats',
        'resp_tournois',
        'resp_ecole_foot',
    ];

    public static function afficherFormulaire(): void
    {
        Vue::afficher('auth/request-account', [
            'title' => 'Demande de compte personnel',
            'flash' => SessionUtilisateur::recupererMessageFlash(),
            'old'   => SessionUtilisateur::recupererAncienneSaisie(),
            'roles' => self::rolesDemandables(),
        ], 'plain');
    }

    public static function envoyerDemande(): void
    {
        $saisie = [
            'prenom' => trim((string) ($_POST['prenom'] ?? '')),
            'nom'    => trim((string) ($_POST['nom'] ?? '')),
            'email'  => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
            'role'   => (string) ($_POST['role'] ?? ''),
        ];
        $motDePasse = (string) ($_POST['password'] ?? '');
        $confirmationMotDePasse = (string) ($_POST['password_confirmation'] ?? '');

        if (!ProtectionCsrf::verifierRequete()) {
            self::echouer($saisie, 'Session expirée, veuillez réessayer.');
        }
        if (($_POST['website'] ?? '') !== '') {
            rediriger('/creation-compte');
        }

        $envoisRecents = array_filter($_SESSION['account_requests'] ?? [], static fn ($heureEnvoi) => $heureEnvoi > time() - 3600);
        if (count($envoisRecents) >= 3) {
            self::echouer($saisie, 'Trop de demandes envoyées. Réessayez plus tard.');
        }
        if ($saisie['prenom'] === '' || $saisie['nom'] === ''
            || mb_strlen($saisie['prenom']) > 80 || mb_strlen($saisie['nom']) > 80) {
            self::echouer($saisie, 'Indiquez un prénom et un nom valides (80 caractères maximum chacun).');
        }
        if (!filter_var($saisie['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($saisie['email']) > 190) {
            self::echouer($saisie, 'Indiquez une adresse e-mail valide.');
        }
        if (!in_array($saisie['role'], self::ROLES_DEMANDABLES, true)) {
            self::echouer($saisie, 'Choisissez un rôle dans la liste.');
        }
        //Le mot de passe est validé ici puis haché avant d'être confié au dépôt SQL.
        if (strlen($motDePasse) < 12) {
            self::echouer($saisie, 'Le mot de passe doit contenir au moins 12 caractères.');
        }
        if (!hash_equals($motDePasse, $confirmationMotDePasse)) {
            self::echouer($saisie, 'Les deux mots de passe ne correspondent pas.');
        }

        $resultat = DepotDemandesCompte::envoyerDemande($saisie + [
            'mdp_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
        ]);
        if ($resultat === 'account_exists') {
            self::echouer($saisie, 'Un compte existe déjà avec cette adresse e-mail.');
        }
        if ($resultat === 'request_exists') {
            self::echouer($saisie, 'Une demande est déjà en attente pour cette adresse e-mail.');
        }

        $envoisRecents[] = time();
        $_SESSION['account_requests'] = array_values($envoisRecents);
        SessionUtilisateur::messageFlash('success', 'Votre demande a été transmise au président du club. Vous pourrez vous connecter après son approbation.');
        rediriger('/creation-compte');
    }

    public static function afficherDemandes(): void
    {
        $president = self::exigerPresident();
        if ($president === null) {
            return;
        }

        Vue::afficher('users/requests', PreparationEspaceMembre::preparer($president, '/utilisateurs', 'Demandes de comptes') + [
            'requests' => DepotDemandesCompte::enAttente(),
            'roles'    => RegistreRoles::tous(),
        ], 'app');
    }

    public static function accepterDemande(string $identifiant): void
    {
        $president = self::exigerPresident();
        if ($president === null) {
            return;
        }
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/utilisateurs');
        }

        $identifiantDemande = filter_var($identifiant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($identifiantDemande === false) {
            SessionUtilisateur::messageFlash('error', 'Demande introuvable.');
            rediriger('/utilisateurs');
        }
        try {
            $estAcceptee = DepotDemandesCompte::accepterDemande($identifiantDemande, (int) $president['id']);
        } catch (\DomainException $erreur) {
            SessionUtilisateur::messageFlash('error', $erreur->getMessage());
            rediriger('/utilisateurs');
        }
        SessionUtilisateur::messageFlash($estAcceptee ? 'success' : 'error', $estAcceptee
            ? 'Le compte a été créé et activé.'
            : 'Cette demande a déjà été traitée ou est introuvable.');
        rediriger('/utilisateurs');
    }

    public static function refuserDemande(string $identifiant): void
    {
        $president = self::exigerPresident();
        if ($president === null) {
            return;
        }
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/utilisateurs');
        }

        $identifiantDemande = filter_var($identifiant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($identifiantDemande === false) {
            SessionUtilisateur::messageFlash('error', 'Demande introuvable.');
            rediriger('/utilisateurs');
        }
        $estRefusee = DepotDemandesCompte::refuserDemande($identifiantDemande, (int) $president['id']);
        SessionUtilisateur::messageFlash($estRefusee ? 'success' : 'error', $estRefusee
            ? 'La demande a été refusée.'
            : 'Cette demande a déjà été traitée ou est introuvable.');
        rediriger('/utilisateurs');
    }

    private static function rolesDemandables(): array
    {
        $tousRoles = RegistreRoles::tous();
        return array_intersect_key($tousRoles, array_flip(self::ROLES_DEMANDABLES));
    }

    private static function exigerPresident(): ?array
    {
        $membre = Authentification::exigerConnexion();
        if ($membre['role'] === 'president') {
            return $membre;
        }

        Vue::afficher('errors/error', [
            'status' => 403,
            'title'  => 'Accès refusé',
            'text'   => 'La gestion des demandes de comptes est réservée au président du club.',
            'member' => $membre,
        ], 'plain', 403);
        return null;
    }

    private static function echouer(array $saisie, string $message): never
    {
        SessionUtilisateur::conserverAncienneSaisie($saisie);
        SessionUtilisateur::messageFlash('error', $message);
        rediriger('/creation-compte');
    }
}
