<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;

/** Enregistre les demandes et transforme une demande acceptée en compte membre. */
final class DepotDemandesCompte
{
    public static function envoyerDemande(array $demande): string
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        // La vérification et l'ajout sont groupés pour éviter une demande partiellement enregistrée.
        $connexion->beginTransaction();

        $requete = $connexion->prepare('SELECT 1 FROM utilisateurs WHERE email = :email LIMIT 1');
        $requete->execute(['email' => $demande['email']]);
        if ($requete->fetchColumn() !== false) {
            $connexion->rollBack();
            return 'account_exists';
        }

        $requete = $connexion->prepare(
            "SELECT 1 FROM demandes_comptes WHERE email = :email AND statut = 'en_attente' LIMIT 1"
        );
        $requete->execute(['email' => $demande['email']]);
        if ($requete->fetchColumn() !== false) {
            $connexion->rollBack();
            return 'request_exists';
        }

        $requete = $connexion->prepare(
            "INSERT INTO demandes_comptes (prenom, nom, email, mdp_hash, role, statut)
             VALUES (:prenom, :nom, :email, :mdp_hash, :role, 'en_attente')"
        );
        $requete->execute($demande);
        $connexion->commit();

        return 'submitted';
    }

    public static function enAttente(): array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->query(
            "SELECT id, prenom, nom, email, role, cree_le
             FROM demandes_comptes
             WHERE statut = 'en_attente'
             ORDER BY cree_le ASC, id ASC"
        );

        return $requete->fetchAll();
    }

    public static function accepterDemande(int $identifiant, int $identifiantPresident): bool
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $connexion->beginTransaction();

        try {
            $requete = $connexion->prepare(
                "SELECT prenom, nom, email, mdp_hash, role
                 FROM demandes_comptes
                 WHERE id = :id AND statut = 'en_attente'
                 FOR UPDATE"
            );
            $requete->execute(['id' => $identifiant]);
            $demande = $requete->fetch();
            if ($demande === false) {
                $connexion->rollBack();
                return false;
            }

            $requete = $connexion->prepare('SELECT 1 FROM utilisateurs WHERE email = :email LIMIT 1');
            $requete->execute(['email' => $demande['email']]);
            if ($requete->fetchColumn() !== false) {
                throw new \DomainException('Un compte utilise déjà cette adresse e-mail.');
            }

            $requete = $connexion->prepare(
                'INSERT INTO utilisateurs (prenom, nom_utilisateurs, email, mdp_hash, role, actif)
                 VALUES (:prenom, :nom, :email, :mdp_hash, :role, 1)'
            );
            $requete->execute($demande);

            $requete = $connexion->prepare(
                "UPDATE demandes_comptes
                 SET statut = 'acceptee', mdp_hash = NULL, traite_le = CURRENT_TIMESTAMP, traite_par = :president_id
                 WHERE id = :id"
            );
            $requete->execute(['president_id' => $identifiantPresident, 'id' => $identifiant]);
            // Le mot de passe n'est plus nécessaire dans la demande une fois la décision prise.
            $connexion->commit();

            return true;
        } catch (\Throwable $erreur) {
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }
            throw $erreur;
        }
    }

    public static function refuserDemande(int $identifiant, int $identifiantPresident): bool
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            "UPDATE demandes_comptes
             SET statut = 'refusee', mdp_hash = NULL, traite_le = CURRENT_TIMESTAMP, traite_par = :president_id
             WHERE id = :id AND statut = 'en_attente'"
        );
        $requete->execute(['president_id' => $identifiantPresident, 'id' => $identifiant]);

        return $requete->rowCount() === 1;
    }
}
