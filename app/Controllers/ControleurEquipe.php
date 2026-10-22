<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotEquipe;
use PDOException;

final class ControleurEquipe
{
    // Le président voit toutes les équipes ; l'éducateur voit uniquement les siennes.
    public static function lister(): void
    {
        $membre = Authentification::exigerConnexion();
        $role = $membre['role'] ?? '';
        if (!in_array($role, ['president', 'educateur'], true)) {
            Vue::afficher('errors/error', [
                'status' => 403,
                'title' => 'Accès refusé',
                'text' => 'Cette page est réservée au président et aux éducateurs.',
                'member' => $membre,
            ], 'plain', 403);
            return;
        }

        try {
            $equipes = $role === 'president'
                ? DepotEquipe::listerEquipes()
                : DepotEquipe::listerEquipesPourEducateur((int) $membre['id']);
            $educateurs = $role === 'president' ? DepotEquipe::listerEducateurs() : [];

            foreach ($equipes as &$equipe) {
                $matchs = DepotEquipe::derniersMatchsPourEquipe((int) $equipe['id_equipes']);
                $equipe['dernier_match'] = $matchs[0] ?? null;
            }
            unset($equipe);
        } catch (PDOException $erreur) {
            error_log('Affichage des équipes : ' . $erreur->getMessage());
            Vue::afficher('errors/error', [
                'status' => 500,
                'title' => 'Erreur',
                'text' => 'La liste des équipes ne peut pas être affichée pour le moment.',
                'member' => $membre,
            ], 'plain', 500);
            return;
        }

        $repartition = [];
        foreach ($equipes as $equipe) {
            $categorie = $equipe['code_categories'];
            $repartition[$categorie] = ($repartition[$categorie] ?? 0) + 1;
        }

        $donnees = PreparationEspaceMembre::preparer($membre, '/equipes', 'Équipes') + [
            'equipes' => $equipes,
            'educateurs' => $educateurs,
            'repartition' => $repartition,
            'estPresident' => $role === 'president',
        ];

        Vue::afficher('equipes/index', $donnees, 'app');
    }

    // Affiche la liste nominative des licenciés d'une équipe.
    public static function afficherEffectif(string $id): void
    {
        $membre = Authentification::exigerConnexion();
        $role = $membre['role'] ?? '';
        if (!in_array($role, ['president', 'educateur'], true)) {
            Vue::afficher('errors/error', [
                'status' => 403,
                'title' => 'Accès refusé',
                'text' => 'Cette page est réservée au président et aux éducateurs.',
                'member' => $membre,
            ], 'plain', 403);
            return;
        }

        $idEquipe = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idEquipe === false) {
            self::afficherEquipeIntrouvable($membre);
            return;
        }

        try {
            $educateurId = $role === 'educateur' ? (int) $membre['id'] : null;
            $equipe = DepotEquipe::trouverEquipe((int) $idEquipe, $educateurId);
            if ($equipe === null) {
                self::afficherEquipeIntrouvable($membre);
                return;
            }
            $joueurs = DepotEquipe::listerJoueurs((int) $idEquipe);
        } catch (PDOException $erreur) {
            error_log('Affichage de l’effectif d’une équipe : ' . $erreur->getMessage());
            Vue::afficher('errors/error', [
                'status' => 500,
                'title' => 'Erreur',
                'text' => 'L’effectif de cette équipe ne peut pas être affiché pour le moment.',
                'member' => $membre,
            ], 'plain', 500);
            return;
        }

        $donnees = PreparationEspaceMembre::preparer(
            $membre,
            '/equipes',
            'Effectif ' . $equipe['nom_equipes']
        ) + [
            'equipe' => $equipe,
            'joueurs' => $joueurs,
        ];
        Vue::afficher('equipes/effectif', $donnees, 'app');
    }

    // Enregistre le changement d’éducateur responsable.
    public static function changerEducateur(): void
    {
        if (self::exigerPresident() === null) {
            return;
        }

        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée. Réessaie.');
            rediriger('/equipes');
        }

        $valeurEquipe = $_POST['id_equipe'] ?? null;
        $valeurEducateur = $_POST['id_educateur'] ?? null;
        if ((!is_string($valeurEquipe) && !is_int($valeurEquipe))
            || (!is_string($valeurEducateur) && !is_int($valeurEducateur))) {
            SessionUtilisateur::messageFlash('error', 'Les informations de l’équipe ou de l’éducateur sont invalides.');
            rediriger('/equipes');
        }

        $idEquipe = filter_var(
            $valeurEquipe,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $idEducateur = $valeurEducateur === ''
            ? null
            : filter_var(
                $valeurEducateur,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

        if ($idEquipe === false || $idEducateur === false) {
            SessionUtilisateur::messageFlash('error', 'Les informations de l’équipe ou de l’éducateur sont invalides.');
            rediriger('/equipes');
        }

        try {
            $modificationReussie = DepotEquipe::changerEducateur(
                (int) $idEquipe,
                $idEducateur === null ? null : (int) $idEducateur
            );
        } catch (PDOException $erreur) {
            error_log('Modification de l’éducateur d’une équipe : ' . $erreur->getMessage());
            SessionUtilisateur::messageFlash('error', 'Le changement d’éducateur n’a pas pu être enregistré.');
            rediriger('/equipes');
        }

        SessionUtilisateur::messageFlash(
            $modificationReussie ? 'success' : 'error',
            $modificationReussie
                ? 'L’éducateur de l’équipe a été mis à jour.'
                : 'L’équipe ou l’éducateur sélectionné est introuvable.'
        );

        rediriger('/equipes');
    }

    // Vérifie que le membre connecté est président avant toute modification.
    private static function exigerPresident(): ?array
    {
        $membre = Authentification::exigerConnexion();
        if ($membre['role'] === 'president') {
            return $membre;
        }

        Vue::afficher('errors/error', [
            'status' => 403,
            'title' => 'Accès refusé',
            'text' => 'Cette fonction est réservée au président.',
            'member' => $membre,
        ], 'plain', 403);

        return null;
    }

    private static function afficherEquipeIntrouvable(array $membre): void
    {
        Vue::afficher('errors/error', [
            'status' => 404,
            'title' => 'Équipe introuvable',
            'text' => 'Cette équipe n’existe pas ou vous n’y avez pas accès.',
            'member' => $membre,
        ], 'plain', 404);
    }
}