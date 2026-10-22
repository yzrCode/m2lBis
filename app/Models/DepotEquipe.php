<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use PDO;

//Depot qui lit les équipes et met à jour leur éducateur responsable.
final class DepotEquipe
{
    //Liste toutes les équipes avec leur catégorie, leur saison et leur effectif.
    public static function listerEquipes(): array
    {
        return self::lireEquipes(null);
    }

    //Liste uniquement les équipes rattachées à l'éducateur connecté.
    public static function listerEquipesPourEducateur(int $educateurId): array
    {
        return self::lireEquipes($educateurId);
    }

    //Partage la requête des équipes pour éviter de dupliquer les jointures et les calculs.
    private static function lireEquipes(?int $educateurId): array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $conditionEducateur = $educateurId === null ? '' : ' WHERE e.educateur_id = :educateur_id';

        $requete = $connexion->prepare(
            'SELECT e.id_equipes,
                    e.nom_equipes,
                    e.educateur_id,
                    c.code_categories,
                    c.libelle_categories,
                    s.libelle_saisons,
                    CONCAT(u.prenom, \' \', u.nom_utilisateurs) AS nom_educateur,
                    COUNT(DISTINCT joueur.id_utilisateurs) AS total_joueurs
             FROM equipes e
             INNER JOIN categories c ON c.id_categories = e.categorie_id
             INNER JOIN saisons s ON s.id_saisons = e.saison_id
             LEFT JOIN utilisateurs u ON u.id_utilisateurs = e.educateur_id
             LEFT JOIN equipes_joueurs ej ON ej.equipe_id = e.id_equipes'
             . ' LEFT JOIN utilisateurs joueur
                   ON joueur.id_utilisateurs = ej.joueur_id AND joueur.role = \'licencie\''
             . $conditionEducateur .
            ' GROUP BY e.id_equipes, e.nom_equipes, e.educateur_id,
                      c.code_categories, c.libelle_categories, s.libelle_saisons,
                      u.prenom, u.nom_utilisateurs
              ORDER BY c.code_categories, e.nom_equipes'
        );

        $parametres = $educateurId === null ? [] : ['educateur_id' => $educateurId];
        $requete->execute($parametres);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    //Retourne une équipe seulement si elle est accessible au membre connecté.
    public static function trouverEquipe(int $idEquipe, ?int $educateurId = null): ?array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $conditionEducateur = $educateurId === null ? '' : ' AND e.educateur_id = :educateur_id';
        $requete = $connexion->prepare(
            'SELECT e.id_equipes, e.nom_equipes, e.educateur_id,
                    c.code_categories, c.libelle_categories, s.libelle_saisons,
                    CONCAT(u.prenom, \' \', u.nom_utilisateurs) AS nom_educateur
             FROM equipes e
             INNER JOIN categories c ON c.id_categories = e.categorie_id
             INNER JOIN saisons s ON s.id_saisons = e.saison_id
             LEFT JOIN utilisateurs u ON u.id_utilisateurs = e.educateur_id
             WHERE e.id_equipes = :id_equipe' . $conditionEducateur . '
             LIMIT 1'
        );
        $parametres = ['id_equipe' => $idEquipe];
        if ($educateurId !== null) {
            $parametres['educateur_id'] = $educateurId;
        }
        $requete->execute($parametres);
        $equipe = $requete->fetch(PDO::FETCH_ASSOC);

        return $equipe === false ? null : $equipe;
    }

    //Liste les licenciés associés à une équipe.
    public static function listerJoueurs(int $idEquipe): array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $requete = $connexion->prepare(
            'SELECT u.id_utilisateurs, u.prenom, u.nom_utilisateurs,
                    u.num_licence, u.actif
             FROM equipes_joueurs ej
             INNER JOIN utilisateurs u ON u.id_utilisateurs = ej.joueur_id
             WHERE ej.equipe_id = :id_equipe AND u.role = :role
             ORDER BY u.nom_utilisateurs, u.prenom'
        );
        $requete->execute([
            'id_equipe' => $idEquipe,
            'role' => 'licencie',
        ]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    //Retourne les comptes éducateurs actifs pour alimenter les formulaires.
    public static function listerEducateurs(): array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $requete = $connexion->prepare(
            'SELECT id_utilisateurs,
                    prenom,
                    nom_utilisateurs,
                    CONCAT(prenom, \' \', nom_utilisateurs) AS nom_complet
             FROM utilisateurs
             WHERE role = :role AND actif = :actif
             ORDER BY nom_utilisateurs, prenom'
        );
        $requete->execute(['role' => 'educateur', 'actif' => 1]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    //Retourne les matchs passés d'une équipe, du plus récent au plus ancien.
    public static function derniersMatchsPourEquipe(int $idEquipe, int $limite = 1): array
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $requete = $connexion->prepare(
            'SELECT id_matchs, date_heure, adversaire, lieu, domicile,
                    buts_m2l, buts_adversaire
             FROM matchs
             WHERE equipe_id = :id_equipe AND date_heure <= NOW()
             ORDER BY date_heure DESC, id_matchs DESC
             LIMIT :limite'
        );
        $requete->bindValue(':id_equipe', $idEquipe, PDO::PARAM_INT);
        $requete->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $requete->execute();

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    //Affecte un éducateur actif à une équipe ; null retire l'affectation.
    public static function changerEducateur(int $idEquipe, ?int $idEducateur): bool
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();

        $verifierEquipe = $connexion->prepare(
            'SELECT 1 FROM equipes WHERE id_equipes = :id_equipe LIMIT 1'
        );
        $verifierEquipe->execute(['id_equipe' => $idEquipe]);
        if ($verifierEquipe->fetchColumn() === false) {
            return false;
        }

        if ($idEducateur !== null) {
            $verifierEducateur = $connexion->prepare(
                'SELECT 1
                 FROM utilisateurs
                 WHERE id_utilisateurs = :id_educateur
                   AND role = :role
                   AND actif = :actif
                 LIMIT 1'
            );
            $verifierEducateur->execute([
                'id_educateur' => $idEducateur,
                'role' => 'educateur',
                'actif' => 1,
            ]);
            if ($verifierEducateur->fetchColumn() === false) {
                return false;
            }
        }

        $modifier = $connexion->prepare(
            'UPDATE equipes
             SET educateur_id = :id_educateur
             WHERE id_equipes = :id_equipe'
        );
        $modifier->execute([
            'id_educateur' => $idEducateur,
            'id_equipe' => $idEquipe,
        ]);

        return true;
    }
}
