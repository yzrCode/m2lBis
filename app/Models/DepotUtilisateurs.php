<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;

//Accès aux comptes membres stockés dans MySQL
final class DepotUtilisateurs
{
    public static function chercherParCourriel(string $courriel): ?array
    {
        $courriel = mb_strtolower(trim($courriel));
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT id_utilisateurs AS id, prenom, nom_utilisateurs AS nom, email, role, mdp_hash AS password_hash
             FROM utilisateurs
             WHERE email = :email AND actif = 1
             LIMIT 1'
        );
        $requete->execute(['email' => $courriel]);
        $membre = $requete->fetch();

        return $membre === false ? null : $membre;
    }
}
