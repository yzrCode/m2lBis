<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use RuntimeException;

//Depot qui mémorise les notifications lues pour chaque compte.
final class DepotNotification
{
    //Ajoute une clé stable à chaque notification et récupère son état lu/non lu.
    public static function appliquerEtatLecture(array $notifications, int $utilisateurId): array
    {
        $clesLues = self::clesLues($utilisateurId);
        foreach ($notifications as &$notification) {
            $cle = hash('sha256', $notification['title'] . '|' . $notification['text']);
            $notification['key'] = $cle;
            $notification['unread'] = !isset($clesLues[$cle]);
        }
        unset($notification);

        return $notifications;
    }

    //Marque une notification précise comme lue pour le membre connecté.
    public static function marquerLue(int $utilisateurId, string $cle): void
    {
        self::validerCle($cle);
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'INSERT INTO notifications_lues (utilisateur_id, cle_notification)
             VALUES (:utilisateur, :cle)
             ON DUPLICATE KEY UPDATE lue_le = CURRENT_TIMESTAMP'
        );
        $requete->execute(['utilisateur' => $utilisateurId, 'cle' => $cle]);
    }

    //Marque comme lues toutes les notifications actuellement connues du compte.
    public static function marquerToutesLues(int $utilisateurId, array $notifications): void
    {
        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        $requete = $connexion->prepare(
            'INSERT INTO notifications_lues (utilisateur_id, cle_notification)
             VALUES (:utilisateur, :cle)
             ON DUPLICATE KEY UPDATE lue_le = CURRENT_TIMESTAMP'
        );
        foreach ($notifications as $notification) {
            $cle = hash('sha256', $notification['title'] . '|' . $notification['text']);
            $requete->execute(['utilisateur' => $utilisateurId, 'cle' => $cle]);
        }
    }

    //Retourne les clés déjà lues pour un utilisateur.
    private static function clesLues(int $utilisateurId): array
    {
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT cle_notification FROM notifications_lues WHERE utilisateur_id = :utilisateur'
        );
        $requete->execute(['utilisateur' => $utilisateurId]);

        return array_fill_keys($requete->fetchAll(\PDO::FETCH_COLUMN), true);
    }

    //Refuse les valeurs qui ne sont pas une empreinte SHA-256.
    private static function validerCle(string $cle): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $cle)) {
            throw new RuntimeException('La notification demandée n’est pas valide.');
        }
    }
}
