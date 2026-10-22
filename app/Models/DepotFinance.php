<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use PDO;

//Depot qui lit les cotisations, les achats et les saisons financières.
final class DepotFinance
{
    //Retourne les saisons pour choisir la période des cotisations.
    public static function listerSaisons(): array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT id_saisons, libelle_saisons, date_debut, date_fin,
                    CURDATE() BETWEEN date_debut AND date_fin AS en_cours
             FROM saisons
             ORDER BY date_debut DESC'
        );
        $requete->execute();

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    //Calcule les recettes de cotisations pour une saison et les dépenses payées.
    public static function obtenirIndicateurs(int $saisonId): array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();

        $recettes = $connexion->prepare(
            'SELECT COALESCE(SUM(montant_du), 0)
             FROM cotisations
             WHERE saison_id = :saison_id AND statut_cotisations = :statut'
        );
        $recettes->execute([
            'saison_id' => $saisonId,
            'statut' => 'payee',
        ]);
        $montantRecettes = (float) $recettes->fetchColumn();

        $depenses = $connexion->prepare(
            'SELECT COALESCE(SUM(montant_ttc), 0)
             FROM achats_fournisseurs
             WHERE statut_fournisseurs = :statut'
        );
        $depenses->execute(['statut' => 'paye']);
        $montantDepenses = (float) $depenses->fetchColumn();

        $achatsEnAttente = $connexion->prepare(
            'SELECT COUNT(*)
             FROM achats_fournisseurs
             WHERE LOWER(TRIM(statut_fournisseurs)) IN
                   (:en_attente, :en_attente_avec_espace, :a_valider, :a_valider_accent)'
        );
        $achatsEnAttente->execute([
            'en_attente' => 'en_attente',
            'en_attente_avec_espace' => 'en attente',
            'a_valider' => 'a_valider',
            'a_valider_accent' => 'à valider',
        ]);

        return [
            'recettes' => $montantRecettes,
            'depenses' => $montantDepenses,
            'solde' => $montantRecettes - $montantDepenses,
            'achats_en_attente' => (int) $achatsEnAttente->fetchColumn(),
        ];
    }

    //Liste les achats fournisseurs avec le nom de la personne qui les a saisis.
    public static function listerAchats(): array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT a.id_achats_fournisseurs,
                    a.fournisseur,
                    a.libelle_fournisseurs,
                    a.montant_ttc,
                    a.date_cmd,
                    a.statut_fournisseurs,
                    a.facture_pdf,
                    CONCAT(u.prenom, \' \', u.nom_utilisateurs) AS acheteur_nom
             FROM achats_fournisseurs a
             LEFT JOIN utilisateurs u ON u.id_utilisateurs = a.acheteur_id
             ORDER BY a.date_cmd DESC, a.id_achats_fournisseurs DESC'
        );
        $requete->execute();

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    //Marque comme payé un achat existant.
    public static function validerPaiementAchat(int $idAchat): bool
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $verifierAchat = $connexion->prepare(
            'SELECT 1
             FROM achats_fournisseurs
             WHERE id_achats_fournisseurs = :id_achat
             LIMIT 1'
        );
        $verifierAchat->execute(['id_achat' => $idAchat]);
        if ($verifierAchat->fetchColumn() === false) {
            return false;
        }

        $modifierAchat = $connexion->prepare(
            'UPDATE achats_fournisseurs
             SET statut_fournisseurs = :statut
             WHERE id_achats_fournisseurs = :id_achat'
        );
        $modifierAchat->execute([
            'statut' => 'paye',
            'id_achat' => $idAchat,
        ]);

        return true;
    }
}
