<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\ProtectionCsrf;
use App\Core\RegistreRoles;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotDossier;
use DateTimeImmutable;
use PDOException;

final class ControleurDossier
{
    //Affiche le formulaire qui permet à la secrétaire de saisir un dossier manuellement.
    public static function afficherNouveau(): void
    {
        $membre = Authentification::exigerConnexion();
        if (RegistreRoles::elementMenu($membre['role'], '/dossiers') === null) {
            self::afficherAccesRefuse($membre);
            return;
        }

        //Les données communes préparent le menu, le profil et la saison du gabarit.
        $donneesCommunes = PreparationEspaceMembre::preparer(
            $membre,
            '/dossiers',
            'Nouveau dossier'
        );

        Vue::afficher('dossiers/nouveau', $donneesCommunes + [
            'old' => SessionUtilisateur::recupererAncienneSaisie(),
        ], 'app');
    }

    //Vérifie et enregistre les informations envoyées par le formulaire.
    public static function creer(): void
    {
        $membre = Authentification::exigerConnexion();
        if (RegistreRoles::elementMenu($membre['role'], '/dossiers') === null) {
            self::afficherAccesRefuse($membre);
            return;
        }

        //On refuse le formulaire si son jeton de sécurité est absent ou incorrect.
        if (!ProtectionCsrf::verifierRequete()) {
            self::echouerCreation([], 'Session expirée, veuillez réessayer.');
        }

        //Les données sont nettoyées avant leur validation et leur enregistrement.
        $saisie = [
            'prenom' => trim(self::valeurPostee('prenom')),
            'nom' => trim(self::valeurPostee('nom')),
            'email' => mb_strtolower(trim(self::valeurPostee('email'))),
            'categorie' => trim(self::valeurPostee('categorie')),
            'date_naissance' => trim(self::valeurPostee('date_naissance')),
        ];

        if (
            $saisie['prenom'] === ''
            || $saisie['nom'] === ''
            || mb_strlen($saisie['prenom']) > 60
            || mb_strlen($saisie['nom']) > 60
        ) {
            self::echouerCreation($saisie, 'Indiquez un prénom et un nom (60 caractères maximum chacun).');
        }
        if (
            !filter_var($saisie['email'], FILTER_VALIDATE_EMAIL)
            || mb_strlen($saisie['email']) > 150
        ) {
            self::echouerCreation($saisie, 'Indiquez une adresse e-mail valide (150 caractères maximum).');
        }
        if ($saisie['categorie'] === '' || mb_strlen($saisie['categorie']) > 20) {
            self::echouerCreation($saisie, 'Indiquez une catégorie (20 caractères maximum).');
        }

        //La date est facultative, mais doit suivre le format attendu par le champ du formulaire.
        $dateNaissance = null;
        if ($saisie['date_naissance'] !== '') {
            $dateNaissance = DateTimeImmutable::createFromFormat('!Y-m-d', $saisie['date_naissance']);
            $erreursDate = DateTimeImmutable::getLastErrors();
            if (
                $dateNaissance === false
                || ($erreursDate !== false
                    && ($erreursDate['warning_count'] > 0 || $erreursDate['error_count'] > 0))
            ) {
                self::echouerCreation($saisie, 'La date de naissance doit être une date valide.');
            }
            if ($dateNaissance > new DateTimeImmutable('today')) {
                self::echouerCreation($saisie, 'La date de naissance ne peut pas être dans le futur.');
            }
            $dateNaissance = $dateNaissance->format('Y-m-d');
        }

        //On remplace la date saisie par sa valeur validée, ou par null si elle est facultative.
        $saisie['date_naissance'] = $dateNaissance;
        try {
            //Le dépôt ajoute les informations dans la base et garde les pièces à l'état initial.
            DepotDossier::creer($saisie);
        } catch (PDOException $erreur) {
            //Le détail SQL reste dans le journal du serveur et n'est pas affiché à l'utilisateur.
            error_log('Création d’un dossier : ' . $erreur->getMessage());
            self::echouerCreation($saisie, 'Le dossier n’a pas pu être créé. Vérifiez les informations puis réessayez.');
        }

        SessionUtilisateur::messageFlash('success', 'Le nouveau dossier a été créé.');
        rediriger('/dossiers');
    }

    public static function lister(): void
    {
        //On vérifie qu'un membre est connecté sinon redirigé
        $membre = Authentification::exigerConnexion();
        //On vérifie que le rôle du membre a accès à la page /dossiers
        $elementMenu = RegistreRoles::elementMenu($membre['role'], '/dossiers');

        //Si ce n'est pas la secrétaire erreur
        if ($elementMenu === null){
            Vue::afficher('errors/error',[
                'status' => 403,
                'title' => 'Accès refusé',
                'text' => "Cette fonction n'est pas disponible pour votre rôle",
                'member' => $membre,
            ], 'plain', 403);
            
            return;
        }
        //Récupère les dossiers depuis le dépôt, qui s'occupe de l'accès aux données
        $dossiers = DepotDossier::lister();
        //Préparation des éléments communs de l'espace membre
        $donneesCommunes = PreparationEspaceMembre::preparer(
            $membre,
            '/dossiers',
            'Dossiers',
        );

        //Affiche la vue dans le gabarit
        Vue::afficher(
            'dossiers/liste',
            $donneesCommunes + ['dossiers' => $dossiers],
            'app'
        );
    }

    public static function relancer(string $identifiant): void
    {
        $membre = Authentification::exigerConnexion();
        //Vérifie le droit d'accès au même endroit que pour la consultation de la liste.
        if (RegistreRoles::elementMenu($membre['role'], '/dossiers') === null) {
            Vue::afficher('errors/error', [
                'status' => 403,
                'title' => 'Accès refusé',
                'text' => "Cette fonction n'est pas disponible pour votre rôle.",
                'member' => $membre,
            ], 'plain', 403);
            return;
        }
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/dossiers');
        }

        //Refuse un identifiant qui n'est pas un entier positif avant d'interroger le dépôt.
        $idDossier = filter_var($identifiant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idDossier === false) {
            SessionUtilisateur::messageFlash('error', 'Dossier introuvable.');
            rediriger('/dossiers');
        }

        //Le dépôt confirme que le dossier est toujours incomplet et en attente.
        $estRelanceEnregistree = DepotDossier::enregistrerRelance($idDossier, $membre);
        SessionUtilisateur::messageFlash(
            $estRelanceEnregistree ? 'success' : 'error',
            $estRelanceEnregistree
                ? 'Relance enregistrée. Aucun e-mail n’a été envoyé.'
                : 'Ce dossier est introuvable ou ne nécessite plus de relance.'
        );
        rediriger('/dossiers');
    }

    //Affiche une erreur si le membre connecté ne possède pas le menu des dossiers.
    private static function afficherAccesRefuse(array $membre): void
    {
        Vue::afficher('errors/error', [
            'status' => 403,
            'title' => 'Accès refusé',
            'text' => "Cette fonction n'est pas disponible pour votre rôle.",
            'member' => $membre,
        ], 'plain', 403);
    }

    //Renvoie une valeur de formulaire uniquement si elle a bien été envoyée comme texte.
    private static function valeurPostee(string $nom): string
    {
        $valeur = $_POST[$nom] ?? '';
        return is_string($valeur) ? $valeur : '';
    }

    //Garde les valeurs saisies pour éviter de remplir à nouveau le formulaire après une erreur.
    private static function echouerCreation(array $saisie, string $message): never
    {
        SessionUtilisateur::conserverAncienneSaisie($saisie);
        SessionUtilisateur::messageFlash('error', $message);
        rediriger('/dossiers/nouveau');
    }
}