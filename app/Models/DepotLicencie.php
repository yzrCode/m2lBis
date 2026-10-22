<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use PDO;
use RuntimeException;
use Throwable;

//Depot qui lit et modifie les comptes licenciés et leurs dossiers.
final class DepotLicencie
{
    //Compte les comptes licenciés actifs pour afficher le chiffre réel au tableau de bord.
    public static function compterActifs(): int
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role = 'licencie' AND actif = 1"
        );

        return (int) $requete->fetchColumn();
    }

    //Retourne les licenciés après application des filtres de recherche.
    public static function lister(array $filtres = []): array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $saisonId = self::saisonEnCours($connexion);
        $conditions = ["u.role = 'licencie'"];
        $parametres = [];

        //La recherche porte sur le nom, le prénom et le numéro de licence.
        if ($filtres['recherche'] !== '') {
            $conditions[] = '(u.nom_utilisateurs LIKE :recherche_nom
                OR u.prenom LIKE :recherche_prenom
                OR u.num_licence LIKE :recherche_licence)';
            $terme = '%' . $filtres['recherche'] . '%';
            $parametres['recherche_nom'] = $terme;
            $parametres['recherche_prenom'] = $terme;
            $parametres['recherche_licence'] = $terme;
        }

        //Une catégorie vide signifie que la secrétaire veut afficher toutes les catégories.
        if ($filtres['categorie'] !== '') {
            $conditions[] = 'd.categorie_dossiers = :categorie';
            $parametres['categorie'] = $filtres['categorie'];
        }

        //Les filtres d'activité et de cotisation sont séparés pour rester faciles à comprendre.
        if ($filtres['statut'] === 'actifs') {
            $conditions[] = 'u.actif = 1';
        } elseif ($filtres['statut'] === 'inactifs') {
            $conditions[] = 'u.actif = 0';
        } elseif ($filtres['statut'] === 'payee') {
            $conditions[] = "c.statut_cotisations = 'payee'";
        } elseif ($filtres['statut'] === 'en_retard') {
            $conditions[] = "c.statut_cotisations = 'en_retard'";
        } elseif ($filtres['statut'] === 'a_payer') {
            $conditions[] = "c.statut_cotisations = 'a_payer'";
        } elseif ($filtres['statut'] === 'sans_cotisation') {
            $conditions[] = 'c.id_cotisations IS NULL';
        }

        //Le sous-select prend le dossier le plus récent rattaché au compte.
        $jointureDossier = 'LEFT JOIN dossiers d
            ON d.id_dossiers = (
                SELECT d2.id_dossiers
                FROM dossiers d2
                WHERE d2.id_utilisateurs = u.id_utilisateurs
                ORDER BY d2.id_dossiers DESC
                LIMIT 1
            )';

        //Seule la cotisation de la saison en cours est affichée dans l'annuaire.
        $jointureCotisation = $saisonId === null
            ? 'LEFT JOIN cotisations c ON 1 = 0'
            : 'LEFT JOIN cotisations c
                ON c.id_cotisations = (
                    SELECT c2.id_cotisations
                    FROM cotisations c2
                    WHERE c2.utilisateur_id = u.id_utilisateurs
                      AND c2.saison_id = :saison_id
                    ORDER BY c2.id_cotisations DESC
                    LIMIT 1
                )';
        if ($saisonId !== null) {
            $parametres['saison_id'] = $saisonId;
        }

        $requete = $connexion->prepare(
            'SELECT u.id_utilisateurs AS id, u.prenom, u.nom_utilisateurs AS nom,
                    u.email, u.telephone, u.num_licence, u.actif,
                    d.categorie_dossiers AS categorie, d.date_naissance_dossiers AS date_naissance,
                    c.statut_cotisations AS cotisation
             FROM utilisateurs u
             ' . $jointureDossier . '
             ' . $jointureCotisation . '
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY u.nom_utilisateurs, u.prenom, u.id_utilisateurs'
        );
        $requete->execute($parametres);

        return array_map(
            static fn (array $ligne): array => self::presenter($ligne),
            $requete->fetchAll()
        );
    }

    //Retourne les catégories déjà utilisées pour remplir le filtre de la page.
    public static function categories(): array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->query(
            "SELECT DISTINCT d.categorie_dossiers
             FROM dossiers d
             INNER JOIN utilisateurs u ON u.id_utilisateurs = d.id_utilisateurs
             WHERE u.role = 'licencie' AND d.categorie_dossiers <> ''
             ORDER BY d.categorie_dossiers"
        );

        return array_column($requete->fetchAll(), 'categorie_dossiers');
    }

    //Retourne la saison en cours pour expliquer si le filtre des cotisations est disponible.
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

    //Retourne la fiche complète d'un licencié, ou null si son compte n'existe pas.
    public static function trouver(int $identifiant): ?array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $saisonId = self::saisonEnCours($connexion);
        $jointureCotisation = $saisonId === null
            ? 'LEFT JOIN cotisations c ON 1 = 0'
            : 'LEFT JOIN cotisations c
                ON c.id_cotisations = (
                    SELECT c2.id_cotisations
                    FROM cotisations c2
                    WHERE c2.utilisateur_id = u.id_utilisateurs
                      AND c2.saison_id = :saison_id
                    ORDER BY c2.id_cotisations DESC
                    LIMIT 1
                )';

        $requete = $connexion->prepare(
            'SELECT u.id_utilisateurs AS id, u.prenom, u.nom_utilisateurs AS nom,
                    u.email, u.telephone, u.num_licence, u.actif,
                    d.categorie_dossiers AS categorie, d.date_naissance_dossiers AS date_naissance,
                    d.email_dossiers AS email_contact, c.statut_cotisations AS cotisation,
                    s.libelle_saisons AS saison, c.montant_du
             FROM utilisateurs u
             LEFT JOIN dossiers d
                ON d.id_dossiers = (
                    SELECT d2.id_dossiers
                    FROM dossiers d2
                    WHERE d2.id_utilisateurs = u.id_utilisateurs
                    ORDER BY d2.id_dossiers DESC
                    LIMIT 1
                )
             ' . $jointureCotisation . '
             LEFT JOIN saisons s ON s.id_saisons = c.saison_id
             WHERE u.id_utilisateurs = :id AND u.role = :role
             LIMIT 1'
        );
        $parametres = ['id' => $identifiant, 'role' => 'licencie'];
        if ($saisonId !== null) {
            $parametres['saison_id'] = $saisonId;
        }
        $requete->execute($parametres);
        $licencie = $requete->fetch();

        return $licencie === false ? null : self::presenter($licencie);
    }

    //Crée un compte et son dossier dans une seule transaction.
    public static function creer(array $licencie, string $empreinteMotDePasse): int
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $connexion->beginTransaction();

        try {
            //L'adresse e-mail sert d'identifiant de connexion et doit être unique.
            $verifierEmail = $connexion->prepare(
                'SELECT 1 FROM utilisateurs WHERE email = :email LIMIT 1'
            );
            $verifierEmail->execute(['email' => $licencie['email']]);
            if ($verifierEmail->fetchColumn() !== false) {
                throw new RuntimeException('Un compte existe déjà avec cette adresse e-mail.');
            }

            //Le numéro officiel reste facultatif, mais sa valeur ne peut pas appartenir à un autre membre.
            if ($licencie['num_licence'] !== null) {
                $verifierLicence = $connexion->prepare(
                    'SELECT 1 FROM utilisateurs WHERE num_licence = :numero LIMIT 1'
                );
                $verifierLicence->execute(['numero' => $licencie['num_licence']]);
                if ($verifierLicence->fetchColumn() !== false) {
                    throw new RuntimeException('Ce numéro de licence est déjà associé à un compte.');
                }
            }

            //Le compte commence actif et devra remplacer son mot de passe provisoire.
            $ajouterUtilisateur = $connexion->prepare(
                'INSERT INTO utilisateurs
                    (prenom, nom_utilisateurs, email, mdp_hash, telephone, num_licence,
                     role, actif, doit_changer_mot_de_passe)
                 VALUES
                    (:prenom, :nom, :email, :mdp_hash, :telephone, :num_licence,
                     :role, 1, 1)'
            );
            $ajouterUtilisateur->execute([
                'prenom' => $licencie['prenom'],
                'nom' => $licencie['nom'],
                'email' => $licencie['email'],
                'mdp_hash' => $empreinteMotDePasse,
                'telephone' => $licencie['telephone'],
                'num_licence' => $licencie['num_licence'],
                'role' => 'licencie',
            ]);
            $identifiant = (int) $connexion->lastInsertId();

            //Le dossier reprend les coordonnées du licencié et garde l'état initial défini par MySQL.
            $ajouterDossier = $connexion->prepare(
                'INSERT INTO dossiers
                    (id_utilisateurs, prenom_dossiers, nom_dossiers, email_dossiers,
                     categorie_dossiers, date_naissance_dossiers)
                 VALUES
                    (:id, :prenom, :nom, :email, :categorie, :date_naissance)'
            );
            $ajouterDossier->execute([
                'id' => $identifiant,
                'prenom' => $licencie['prenom'],
                'nom' => $licencie['nom'],
                'email' => $licencie['email'],
                'categorie' => $licencie['categorie'],
                'date_naissance' => $licencie['date_naissance'],
            ]);

            //Une cotisation est créée seulement si la base contient une saison active.
            $saisonId = self::saisonEnCours($connexion);
            if ($saisonId !== null) {
                $ajouterCotisation = $connexion->prepare(
                    "INSERT INTO cotisations (utilisateur_id, saison_id, statut_cotisations)
                     VALUES (:utilisateur, :saison, 'a_payer')"
                );
                $ajouterCotisation->execute([
                    'utilisateur' => $identifiant,
                    'saison' => $saisonId,
                ]);
            }

            $connexion->commit();
            return $identifiant;
        } catch (Throwable $erreur) {
            //Une erreur annule le compte et le dossier pour éviter un ajout incomplet.
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }
            throw $erreur;
        }
    }

    //Met à jour les informations et, si demandé, le statut de cotisation de la saison en cours.
    public static function modifier(int $identifiant, array $licencie): bool
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $connexion->beginTransaction();

        try {
            //Le rôle est contrôlé pour qu'un autre type de compte ne soit jamais modifié ici.
            $verifierCompte = $connexion->prepare(
                "SELECT email FROM utilisateurs WHERE id_utilisateurs = :id AND role = 'licencie' LIMIT 1"
            );
            $verifierCompte->execute(['id' => $identifiant]);
            $emailActuel = $verifierCompte->fetchColumn();
            if ($emailActuel === false) {
                $connexion->rollBack();
                return false;
            }

            //Une adresse de connexion doit rester unique même si elle est modifiée.
            $verifierEmail = $connexion->prepare(
                'SELECT 1 FROM utilisateurs
                 WHERE email = :email AND id_utilisateurs <> :id
                 LIMIT 1'
            );
            $verifierEmail->execute(['email' => $licencie['email'], 'id' => $identifiant]);
            if ($verifierEmail->fetchColumn() !== false) {
                throw new RuntimeException('Cette adresse e-mail est déjà utilisée par un autre compte.');
            }

            //Le numéro de licence reste unique et facultatif.
            if ($licencie['num_licence'] !== null) {
                $verifierLicence = $connexion->prepare(
                    'SELECT 1 FROM utilisateurs
                     WHERE num_licence = :numero AND id_utilisateurs <> :id
                     LIMIT 1'
                );
                $verifierLicence->execute([
                    'numero' => $licencie['num_licence'],
                    'id' => $identifiant,
                ]);
                if ($verifierLicence->fetchColumn() !== false) {
                    throw new RuntimeException('Ce numéro de licence est déjà associé à un autre compte.');
                }
            }

            //Les coordonnées de connexion, le téléphone et le numéro de licence sont enregistrés sur le compte.
            $modifierUtilisateur = $connexion->prepare(
                "UPDATE utilisateurs
                 SET prenom = :prenom, nom_utilisateurs = :nom, email = :email,
                     telephone = :telephone, num_licence = :num_licence
                 WHERE id_utilisateurs = :id AND role = 'licencie'"
            );
            $modifierUtilisateur->execute([
                'prenom' => $licencie['prenom'],
                'nom' => $licencie['nom'],
                'email' => $licencie['email'],
                'telephone' => $licencie['telephone'],
                'num_licence' => $licencie['num_licence'],
                'id' => $identifiant,
            ]);

            //On met à jour le dossier le plus récent, ou on le crée si le compte n'en a pas encore.
            $chercherDossier = $connexion->prepare(
                'SELECT id_dossiers, email_dossiers FROM dossiers
                 WHERE id_utilisateurs = :id
                 ORDER BY id_dossiers DESC
                 LIMIT 1'
            );
            $chercherDossier->execute(['id' => $identifiant]);
            $dossier = $chercherDossier->fetch();
            if ($dossier === false) {
                $ajouterDossier = $connexion->prepare(
                    'INSERT INTO dossiers
                        (id_utilisateurs, prenom_dossiers, nom_dossiers, email_dossiers,
                         categorie_dossiers, date_naissance_dossiers)
                     VALUES
                        (:id, :prenom, :nom, :email, :categorie, :date_naissance)'
                );
                $ajouterDossier->execute([
                    'id' => $identifiant,
                    'prenom' => $licencie['prenom'],
                    'nom' => $licencie['nom'],
                    'email' => $licencie['email'],
                    'categorie' => $licencie['categorie'],
                    'date_naissance' => $licencie['date_naissance'],
                ]);
            } else {
                $modifierDossier = $connexion->prepare(
                    'UPDATE dossiers
                     SET prenom_dossiers = :prenom, nom_dossiers = :nom,
                         email_dossiers = IF(email_dossiers = :email_actuel, :email_dossier, email_dossiers),
                         categorie_dossiers = :categorie,
                         date_naissance_dossiers = :date_naissance
                     WHERE id_dossiers = :id_dossier'
                );
                $modifierDossier->execute([
                    'prenom' => $licencie['prenom'],
                    'nom' => $licencie['nom'],
                    'email_actuel' => $emailActuel,
                    'email_dossier' => $licencie['email'],
                    'categorie' => $licencie['categorie'],
                    'date_naissance' => $licencie['date_naissance'],
                    'id_dossier' => $dossier['id_dossiers'],
                ]);
            }

            //Aucune cotisation n'est ajoutée tant qu'aucune saison n'est définie.
            if ($licencie['cotisation'] !== '') {
                self::enregistrerCotisation($connexion, $identifiant, $licencie['cotisation']);
            }

            $connexion->commit();
            return true;
        } catch (Throwable $erreur) {
            //Le compte, le dossier et la cotisation restent cohérents si une écriture échoue.
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }
            throw $erreur;
        }
    }

    //Désactive ou réactive un compte sans supprimer ses données.
    public static function definirActif(int $identifiant, bool $actif): bool
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            "UPDATE utilisateurs
             SET actif = :actif
             WHERE id_utilisateurs = :id AND role = 'licencie'"
        );
        $requete->execute([
            'actif' => $actif ? 1 : 0,
            'id' => $identifiant,
        ]);

        if ($requete->rowCount() > 0) {
            return true;
        }

        //MySQL retourne zéro si le compte existe déjà dans l'état demandé.
        return self::trouver($identifiant) !== null;
    }

    //Retourne l'identifiant de la saison dont les dates couvrent aujourd'hui.
    private static function saisonEnCours(PDO $connexion): ?int
    {
        $requete = $connexion->query(
            'SELECT id_saisons
             FROM saisons
             WHERE CURDATE() BETWEEN date_debut AND date_fin
             ORDER BY date_debut DESC
             LIMIT 1'
        );
        $identifiant = $requete->fetchColumn();

        return $identifiant === false ? null : (int) $identifiant;
    }

    //Enregistre un statut choisi par la secrétaire pour la saison en cours.
    private static function enregistrerCotisation(PDO $connexion, int $identifiant, string $statut): void
    {
        if (!in_array($statut, ['a_payer', 'payee', 'en_retard'], true)) {
            throw new RuntimeException('Le statut de cotisation choisi est invalide.');
        }

        $saisonId = self::saisonEnCours($connexion);
        if ($saisonId === null) {
            throw new RuntimeException('Aucune saison active ne permet de modifier la cotisation.');
        }

        //On modifie la cotisation existante au lieu d'en créer une seconde pour le même licencié.
        $chercherCotisation = $connexion->prepare(
            'SELECT id_cotisations FROM cotisations
             WHERE utilisateur_id = :utilisateur AND saison_id = :saison
             ORDER BY id_cotisations DESC
             LIMIT 1'
        );
        $chercherCotisation->execute(['utilisateur' => $identifiant, 'saison' => $saisonId]);
        $idCotisation = $chercherCotisation->fetchColumn();

        if ($idCotisation === false) {
            //La base applique le montant par défaut prévu pour une nouvelle cotisation.
            $ajouterCotisation = $connexion->prepare(
                'INSERT INTO cotisations (utilisateur_id, saison_id, statut_cotisations)
                 VALUES (:utilisateur, :saison, :statut)'
            );
            $ajouterCotisation->execute([
                'utilisateur' => $identifiant,
                'saison' => $saisonId,
                'statut' => $statut,
            ]);
            return;
        }

        $modifierCotisation = $connexion->prepare(
            'UPDATE cotisations SET statut_cotisations = :statut WHERE id_cotisations = :id'
        );
        $modifierCotisation->execute(['statut' => $statut, 'id' => $idCotisation]);
    }

    //Prépare les valeurs affichées dans la fiche et dans le tableau.
    private static function presenter(array $ligne): array
    {
        $statutsCotisation = [
            'payee' => ['label' => 'À jour', 'tone' => 'ok'],
            'a_payer' => ['label' => 'À payer', 'tone' => 'warn'],
            'en_retard' => ['label' => 'En retard', 'tone' => 'danger'],
        ];
        $statutCotisation = $ligne['cotisation'] === null
            ? ['label' => 'Non renseignée', 'tone' => 'neutral']
            : ($statutsCotisation[$ligne['cotisation']] ?? ['label' => 'À vérifier', 'tone' => 'neutral']);

        return array_merge($ligne, [
            'nom_complet' => trim($ligne['prenom'] . ' ' . $ligne['nom']),
            'actif' => (bool) $ligne['actif'],
            'cotisation_affichage' => $statutCotisation,
        ]);
    }
}
