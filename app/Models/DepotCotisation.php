<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use PDO;
use RuntimeException;
use Throwable;

//Depot qui lit et met à jour les cotisations et les saisons.
final class DepotCotisation
{
    //Retourne les saisons avec la saison actuelle en premier.
    public static function saisons(): array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->query(
            'SELECT id_saisons AS id, libelle_saisons AS libelle,
                    date_debut, date_fin,
                    CURDATE() BETWEEN date_debut AND date_fin AS en_cours
             FROM saisons
             ORDER BY date_debut DESC'
        );

        return $requete->fetchAll();
    }

    //Retourne la saison dont les dates contiennent la date du jour.
    public static function saisonCourante(): ?array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->query(
            'SELECT id_saisons AS id, libelle_saisons AS libelle
             FROM saisons
             WHERE CURDATE() BETWEEN date_debut AND date_fin
             ORDER BY date_debut DESC
             LIMIT 1'
        );
        $saison = $requete->fetch();

        return $saison === false ? null : $saison;
    }

    //Crée une saison et prépare la cotisation « à payer » des licenciés actifs.
    public static function creerSaison(string $libelle, string $dateDebut, string $dateFin): void
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $connexion->beginTransaction();

        try {
            //Deux saisons qui se chevauchent rendraient la saison en cours ambiguë.
            $verifierChevauchement = $connexion->prepare(
                'SELECT 1 FROM saisons
                 WHERE date_debut <= :date_fin AND date_fin >= :date_debut
                 LIMIT 1'
            );
            $verifierChevauchement->execute([
                'date_fin' => $dateFin,
                'date_debut' => $dateDebut,
            ]);
            if ($verifierChevauchement->fetchColumn() !== false) {
                throw new RuntimeException('Les dates choisies chevauchent une saison déjà enregistrée.');
            }

            $ajouterSaison = $connexion->prepare(
                'INSERT INTO saisons (libelle_saisons, date_debut, date_fin)
                 VALUES (:libelle, :date_debut, :date_fin)'
            );
            $ajouterSaison->execute([
                'libelle' => $libelle,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ]);
            $saisonId = (int) $connexion->lastInsertId();

            //Chaque licencié actif reçoit une cotisation à payer au tarif par défaut de la base.
            $creerCotisations = $connexion->prepare(
                "INSERT INTO cotisations (utilisateur_id, saison_id, statut_cotisations)
                 SELECT id_utilisateurs, :saison, 'a_payer'
                 FROM utilisateurs
                 WHERE role = 'licencie' AND actif = 1"
            );
            $creerCotisations->execute(['saison' => $saisonId]);

            $connexion->commit();
        } catch (Throwable $erreur) {
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }
            throw $erreur;
        }
    }

    //Retourne les cotisations des licenciés actifs pour une saison et des filtres donnés.
    public static function lister(int $saisonId, array $filtres = []): array
    {
        $conditions = ["u.role = 'licencie'", 'u.actif = 1'];
        $parametres = ['saison' => $saisonId];

        if ($filtres['recherche'] !== '') {
            $conditions[] = '(u.nom_utilisateurs LIKE :nom OR u.prenom LIKE :prenom OR u.num_licence LIKE :licence)';
            $terme = '%' . $filtres['recherche'] . '%';
            $parametres['nom'] = $terme;
            $parametres['prenom'] = $terme;
            $parametres['licence'] = $terme;
        }
        if ($filtres['statut'] !== '') {
            $conditions[] = 'COALESCE(c.statut_cotisations, \'a_payer\') = :statut';
            $parametres['statut'] = $filtres['statut'];
        }

        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT u.id_utilisateurs AS id, u.prenom, u.nom_utilisateurs AS nom,
                    u.email, u.num_licence,
                    COALESCE(c.montant_du, 150.00) AS montant,
                    COALESCE(c.statut_cotisations, \'a_payer\') AS statut
             FROM utilisateurs u
             LEFT JOIN cotisations c
                ON c.id_cotisations = (
                    SELECT c2.id_cotisations
                    FROM cotisations c2
                    WHERE c2.utilisateur_id = u.id_utilisateurs
                      AND c2.saison_id = :saison
                    ORDER BY c2.id_cotisations DESC
                    LIMIT 1
                )
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY u.nom_utilisateurs, u.prenom'
        );
        $requete->execute($parametres);

        return $requete->fetchAll();
    }

    //Retourne uniquement la cotisation du licencié connecté.
    public static function pourLicencie(int $licencieId, int $saisonId): ?array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT s.libelle_saisons AS saison,
                    COALESCE(c.montant_du, 150.00) AS montant,
                    COALESCE(c.statut_cotisations, \'a_payer\') AS statut
             FROM saisons s
             LEFT JOIN cotisations c
                ON c.id_cotisations = (
                    SELECT c2.id_cotisations
                    FROM cotisations c2
                    WHERE c2.utilisateur_id = :utilisateur
                      AND c2.saison_id = s.id_saisons
                    ORDER BY c2.id_cotisations DESC
                    LIMIT 1
                )
             WHERE s.id_saisons = :saison
             LIMIT 1'
        );
        $requete->execute(['utilisateur' => $licencieId, 'saison' => $saisonId]);
        $cotisation = $requete->fetch();

        return $cotisation === false ? null : $cotisation;
    }

    //Enregistre le montant et le statut saisis sur une cotisation.
    public static function modifier(int $licencieId, int $saisonId, float $montant, string $statut): bool
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $connexion->beginTransaction();

        try {
            $verifierLicencie = $connexion->prepare(
                "SELECT 1 FROM utilisateurs
                 WHERE id_utilisateurs = :id AND role = 'licencie' AND actif = 1
                 LIMIT 1"
            );
            $verifierLicencie->execute(['id' => $licencieId]);
            if ($verifierLicencie->fetchColumn() === false) {
                $connexion->rollBack();
                return false;
            }

            $verifierSaison = $connexion->prepare(
                'SELECT 1 FROM saisons WHERE id_saisons = :id LIMIT 1'
            );
            $verifierSaison->execute(['id' => $saisonId]);
            if ($verifierSaison->fetchColumn() === false) {
                $connexion->rollBack();
                return false;
            }

            $chercherCotisation = $connexion->prepare(
                'SELECT id_cotisations FROM cotisations
                 WHERE utilisateur_id = :utilisateur AND saison_id = :saison
                 ORDER BY id_cotisations DESC
                 LIMIT 1'
            );
            $chercherCotisation->execute([
                'utilisateur' => $licencieId,
                'saison' => $saisonId,
            ]);
            $cotisationId = $chercherCotisation->fetchColumn();

            if ($cotisationId === false) {
                $ajouterCotisation = $connexion->prepare(
                    'INSERT INTO cotisations (utilisateur_id, saison_id, montant_du, statut_cotisations)
                     VALUES (:utilisateur, :saison, :montant, :statut)'
                );
                $ajouterCotisation->execute([
                    'utilisateur' => $licencieId,
                    'saison' => $saisonId,
                    'montant' => $montant,
                    'statut' => $statut,
                ]);
            } else {
                $modifierCotisation = $connexion->prepare(
                    'UPDATE cotisations
                     SET montant_du = :montant, statut_cotisations = :statut
                     WHERE id_cotisations = :id'
                );
                $modifierCotisation->execute([
                    'montant' => $montant,
                    'statut' => $statut,
                    'id' => $cotisationId,
                ]);
            }

            $connexion->commit();
            return true;
        } catch (Throwable $erreur) {
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }
            throw $erreur;
        }
    }

    //Calcule les indicateurs de cotisation pour la saison actuelle du tableau de bord.
    public static function indicateurs(): array
    {
        $saison = self::saisonCourante();
        if ($saison === null) {
            return [
                'total' => 0,
                'payees' => 0,
                'impayees' => 0,
                'montant_total' => 0.0,
                'montant_encaisse' => 0.0,
                'montant_impaye' => 0.0,
            ];
        }

        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN COALESCE(c.statut_cotisations, 'a_payer') = 'payee' THEN 1 ELSE 0 END) AS payees,
                    SUM(CASE WHEN COALESCE(c.statut_cotisations, 'a_payer') <> 'payee' THEN 1 ELSE 0 END) AS impayees,
                    COALESCE(SUM(COALESCE(c.montant_du, 150.00)), 0) AS montant_total,
                    COALESCE(SUM(CASE WHEN COALESCE(c.statut_cotisations, 'a_payer') = 'payee'
                        THEN COALESCE(c.montant_du, 150.00) ELSE 0 END), 0) AS montant_encaisse,
                    COALESCE(SUM(CASE WHEN COALESCE(c.statut_cotisations, 'a_payer') <> 'payee'
                        THEN COALESCE(c.montant_du, 150.00) ELSE 0 END), 0) AS montant_impaye
             FROM utilisateurs u
             LEFT JOIN cotisations c
                ON c.id_cotisations = (
                    SELECT c2.id_cotisations
                    FROM cotisations c2
                    WHERE c2.utilisateur_id = u.id_utilisateurs
                      AND c2.saison_id = :saison
                    ORDER BY c2.id_cotisations DESC
                    LIMIT 1
                )
             WHERE u.role = 'licencie' AND u.actif = 1"
        );
        $requete->execute(['saison' => $saison['id']]);
        $indicateurs = $requete->fetch();

        return [
            'total' => (int) $indicateurs['total'],
            'payees' => (int) $indicateurs['payees'],
            'impayees' => (int) $indicateurs['impayees'],
            'montant_total' => (float) $indicateurs['montant_total'],
            'montant_encaisse' => (float) $indicateurs['montant_encaisse'],
            'montant_impaye' => (float) $indicateurs['montant_impaye'],
        ];
    }
}
