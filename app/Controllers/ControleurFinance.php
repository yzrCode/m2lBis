<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotFinance;
use PDOException;

//Gère la consultation des finances pour le président et le trésorier.
final class ControleurFinance
{
    //Affiche les indicateurs et les achats pour la saison choisie.
    public static function index(): void
    {
        $membre = self::exigerAcces();
        if ($membre === null) {
            return;
        }

        try {
            $saisons = DepotFinance::listerSaisons();
            $saison = self::selectionnerSaison($saisons);
            $indicateurs = $saison === null
                ? null
                : DepotFinance::obtenirIndicateurs((int) $saison['id_saisons']);
            $achats = DepotFinance::listerAchats();
        } catch (PDOException $erreur) {
            error_log('Affichage des finances : ' . $erreur->getMessage());
            Vue::afficher('errors/error', [
                'status' => 500,
                'title' => 'Erreur',
                'text' => 'Les informations financières ne peuvent pas être affichées pour le moment.',
                'member' => $membre,
            ], 'plain', 500);
            return;
        }

        if ($saisons === []) {
            SessionUtilisateur::messageFlash('error', 'Aucune saison n’est enregistrée. Les recettes de cotisations ne peuvent pas être calculées.');
        }

        $donnees = PreparationEspaceMembre::preparer($membre, '/finances', 'Finances') + [
            'saisons' => $saisons,
            'saison' => $saison,
            'indicateurs' => $indicateurs,
            'achats' => $achats,
        ];
        Vue::afficher('finances/index', $donnees, 'app');
    }

    //Valide le paiement d'un achat fournisseur.
    public static function validerPaiement(): void
    {
        if (self::exigerAcces() === null) {
            return;
        }

        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée. Réessaie.');
            rediriger('/finances');
        }

        $valeurId = $_POST['id_achat'] ?? null;
        if (!is_string($valeurId) && !is_int($valeurId)) {
            SessionUtilisateur::messageFlash('error', 'L’identifiant de l’achat est invalide.');
            rediriger('/finances');
        }

        $idAchat = filter_var(
            $valeurId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($idAchat === false) {
            SessionUtilisateur::messageFlash('error', 'L’identifiant de l’achat est invalide.');
            rediriger('/finances');
        }

        try {
            $paiementValide = DepotFinance::validerPaiementAchat((int) $idAchat);
        } catch (PDOException $erreur) {
            error_log('Validation du paiement fournisseur : ' . $erreur->getMessage());
            SessionUtilisateur::messageFlash('error', 'Le paiement n’a pas pu être enregistré.');
            rediriger('/finances');
        }

        SessionUtilisateur::messageFlash(
            $paiementValide ? 'success' : 'error',
            $paiementValide ? 'Le paiement a été validé.' : 'Achat introuvable.'
        );
        rediriger('/finances');
    }

    //Autorise uniquement le président et le trésorier.
    private static function exigerAcces(): ?array
    {
        $membre = Authentification::exigerConnexion();
        if (in_array($membre['role'] ?? '', ['president', 'tresorier'], true)) {
            return $membre;
        }

        Vue::afficher('errors/error', [
            'status' => 403,
            'title' => 'Accès refusé',
            'text' => 'Cette page est réservée au président et au trésorier.',
            'member' => $membre,
        ], 'plain', 403);

        return null;
    }

    //Choisit la saison demandée ou, par défaut, la saison en cours.
    private static function selectionnerSaison(array $saisons): ?array
    {
        if ($saisons === []) {
            return null;
        }

        $saisonDemandee = $_GET['saison'] ?? null;
        if (is_string($saisonDemandee) || is_int($saisonDemandee)) {
            $idSaison = filter_var(
                $saisonDemandee,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($idSaison !== false) {
                foreach ($saisons as $saison) {
                    if ((int) $saison['id_saisons'] === (int) $idSaison) {
                        return $saison;
                    }
                }
            }

            SessionUtilisateur::messageFlash('error', 'La saison demandée est invalide. La saison en cours est affichée.');
        } elseif ($saisonDemandee !== null) {
            SessionUtilisateur::messageFlash('error', 'La saison demandée est invalide. La saison en cours est affichée.');
        }

        foreach ($saisons as $saison) {
            if ((int) $saison['en_cours'] === 1) {
                return $saison;
            }
        }

        return $saisons[0];
    }
}
