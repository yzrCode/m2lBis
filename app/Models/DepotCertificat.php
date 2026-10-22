<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;

//Depot qui suit le statut des certificats sans stocker les documents médicaux.
final class DepotCertificat
{
    //Retourne les certificats des licenciés actifs pour une saison donnée.
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
        if ($filtres['statut'] === 'manquant') {
            $conditions[] = "COALESCE(c.statut_certificats, 'manquant') = 'manquant'";
        } elseif ($filtres['statut'] !== '') {
            $conditions[] = 'c.statut_certificats = :statut';
            $parametres['statut'] = $filtres['statut'];
        }

        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT u.id_utilisateurs AS id, u.prenom, u.nom_utilisateurs AS nom,
                    u.num_licence,
                    COALESCE(c.statut_certificats, \'manquant\') AS statut,
                    c.date_reception
             FROM utilisateurs u
             LEFT JOIN certificats c
                ON c.utilisateur_id = u.id_utilisateurs AND c.saison_id = :saison
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY u.nom_utilisateurs, u.prenom'
        );
        $requete->execute($parametres);

        return $requete->fetchAll();
    }

    //Crée ou met à jour le statut de certificat d'un licencié.
    public static function modifier(
        int $licencieId,
        int $saisonId,
        string $statut,
        ?string $dateReception
    ): bool {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $verifierLicencie = $connexion->prepare(
            "SELECT 1 FROM utilisateurs
             WHERE id_utilisateurs = :id AND role = 'licencie' AND actif = 1
             LIMIT 1"
        );
        $verifierLicencie->execute(['id' => $licencieId]);
        if ($verifierLicencie->fetchColumn() === false) {
            return false;
        }

        $verifierSaison = $connexion->prepare(
            'SELECT 1 FROM saisons WHERE id_saisons = :id LIMIT 1'
        );
        $verifierSaison->execute(['id' => $saisonId]);
        if ($verifierSaison->fetchColumn() === false) {
            return false;
        }

        $requete = $connexion->prepare(
            'INSERT INTO certificats (utilisateur_id, saison_id, statut_certificats, date_reception)
             VALUES (:utilisateur, :saison, :statut, :date_reception)
             ON DUPLICATE KEY UPDATE
                statut_certificats = VALUES(statut_certificats),
                date_reception = VALUES(date_reception)'
        );
        $requete->execute([
            'utilisateur' => $licencieId,
            'saison' => $saisonId,
            'statut' => $statut,
            'date_reception' => $dateReception,
        ]);

        return true;
    }

    //Compte les certificats manquants et à renouveler pour le tableau de bord.
    public static function nombreAtraiter(): int
    {
        $saison = DepotCotisation::saisonCourante();
        if ($saison === null) {
            return 0;
        }

        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            "SELECT COUNT(*)
             FROM utilisateurs u
             LEFT JOIN certificats c
                ON c.utilisateur_id = u.id_utilisateurs AND c.saison_id = :saison
             WHERE u.role = 'licencie' AND u.actif = 1
               AND COALESCE(c.statut_certificats, 'manquant') <> 'recu'"
        );
        $requete->execute(['saison' => $saison['id']]);

        return (int) $requete->fetchColumn();
    }
}
