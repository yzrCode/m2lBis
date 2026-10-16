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

final class ControleurDossier
{
    public static function lister(): void
    {
        // On vérifie qu'un membre est connecté sinon redirigé
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
        // Récupère les dossiers depuis le dépôt, qui s'occupe de l'accès aux données
        $dossiers = DepotDossier::lister();
        //Préparation des éléments communs de l'espace membre
        $donneesCommunes = PreparationEspaceMembre::preparer(
            $membre,
            '/dossiers',
            'Dossiers',
        );

        // Affiche la vue dans le gabarit
        Vue::afficher(
            'dossiers/liste',
            $donneesCommunes + ['dossiers' => $dossiers],
            'app'
        );
    }

    public static function relancer(string $identifiant): void
    {
        $membre = Authentification::exigerConnexion();
        // Vérifie le droit d'accès au même endroit que pour la consultation de la liste.
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

        // Refuse un identifiant qui n'est pas un entier positif avant d'interroger le dépôt.
        $idDossier = filter_var($identifiant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idDossier === false) {
            SessionUtilisateur::messageFlash('error', 'Dossier introuvable.');
            rediriger('/dossiers');
        }

        // Le dépôt confirme que le dossier est toujours incomplet et en attente.
        $estRelanceEnregistree = DepotDossier::enregistrerRelance($idDossier, $membre);
        SessionUtilisateur::messageFlash(
            $estRelanceEnregistree ? 'success' : 'error',
            $estRelanceEnregistree
                ? 'Relance enregistrée. Aucun e-mail n’a été envoyé.'
                : 'Ce dossier est introuvable ou ne nécessite plus de relance.'
        );
        rediriger('/dossiers');
    }
}