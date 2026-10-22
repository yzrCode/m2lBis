<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\ProtectionCsrf;
use App\Models\DepotNotification;
use App\Models\DepotTableauBord;
use PDOException;

//Reçoit les actions de lecture des notifications et les enregistre pour le compte connecté.
final class ControleurNotification
{
    //Enregistre une notification précise ou toutes les notifications comme lues.
    public static function lire(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $membre = Authentification::membreConnecte();
        if ($membre === null) {
            self::repondre(401, 'Connexion requise.');
        }
        if (!ProtectionCsrf::verifierRequete()) {
            self::repondre(419, 'Session expirée. Actualisez la page puis réessayez.');
        }

        $corps = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($corps)) {
            self::repondre(400, 'La demande de notification est invalide.');
        }

        try {
            if (($corps['all'] ?? false) === true) {
                $donnees = DepotTableauBord::pourRole($membre['role'], (int) $membre['id']);
                DepotNotification::marquerToutesLues((int) $membre['id'], $donnees['notifications']);
            } elseif (isset($corps['key']) && is_string($corps['key'])) {
                DepotNotification::marquerLue((int) $membre['id'], $corps['key']);
            } else {
                self::repondre(422, 'Choisissez une notification à marquer comme lue.');
            }
        } catch (PDOException $erreur) {
            error_log('Enregistrement de notification : ' . $erreur->getMessage());
            self::repondre(500, 'La notification n’a pas pu être enregistrée.');
        } catch (\RuntimeException $erreur) {
            self::repondre(422, $erreur->getMessage());
        }

        self::repondre(200, 'Notifications mises à jour.');
    }

    //Retourne une réponse JSON au navigateur puis termine la requête.
    private static function repondre(int $codeHttp, string $message): never
    {
        http_response_code($codeHttp);
        echo json_encode([
            'ok' => $codeHttp === 200,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
