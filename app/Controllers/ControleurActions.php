<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\ProtectionCsrf;
use App\Models\DepotDossier;
use App\Models\DepotTableauBord;

//Point d'entrée des actions du tableau de bord (Valider, Relancer, Confirmer…).
//
//Sécurité : le navigateur n'envoie qu'un CHEMIN (ex. « panels.1.rows.0.actions.0 »). Le serveur
//reconstruit le tableau de bord du rôle connecté et vérifie que ce chemin désigne bien une action
//de CE rôle. Un membre ne peut donc pas déclencher l'action d'un autre rôle.
//Les relances de dossiers sont journalisées ; les autres actions restent à relier au métier.
final class ControleurActions
{
    public static function traiter(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $membre = Authentification::membreConnecte();
        if ($membre === null) {
            self::repondre(401, 'Non connecté');
        }
        if (!ProtectionCsrf::verifierRequete()) {
            self::repondre(419, 'Jeton de sécurité invalide');
        }
        $corpsRequete = json_decode((string) file_get_contents('php://input'), true);
        $chemin = is_array($corpsRequete) ? (string) ($corpsRequete['path'] ?? '') : '';
        if (!preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)*$/i', $chemin)) {
            self::repondre(422, 'Chemin invalide');
        }

        $noeud = DepotTableauBord::resoudre(DepotTableauBord::pourRole($membre['role']), $chemin);
        $estAction = is_array($noeud) && isset($noeud['toast']) && str_contains($chemin, '.actions.');
        $estPointage = is_array($noeud) && ($noeud['type'] ?? '') === 'attendance' && preg_match('/^panels\.\d+$/', $chemin);
        if (!$estAction && !$estPointage) {
            self::repondre(403, 'Action non autorisée pour votre rôle');
        }

        if (($noeud['type'] ?? '') === 'relance_dossier') {
            //Réserve cette action au secrétariat et vérifie le dossier avant de journaliser la relance.
            if ($membre['role'] !== 'secretaire') {
                self::repondre(403, 'Action non autorisée pour votre rôle');
            }
            if (!DepotDossier::enregistrerRelance((int) $noeud['dossierId'], $membre)) {
                self::repondre(409, 'Le dossier est introuvable ou ne nécessite plus de relance');
            }
            self::repondre(200, 'Relance enregistrée. Aucun e-mail n’a été envoyé.');
        }

        //À relier au métier : valider un dossier ou enregistrer un pointage.
        $entree = ['at' => date('c'), 'member' => $membre['id'], 'role' => $membre['role'], 'path' => $chemin,
                  'label' => $noeud['label'] ?? ($noeud['title'] ?? ''), 'data' => $estPointage ? ($corpsRequete['data'] ?? null) : null];
        @file_put_contents(BASE_PATH . '/storage/actions.jsonl', json_encode($entree, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

        self::repondre(200, 'OK');
    }

    private static function repondre(int $codeHttp, string $message): never
    {
        http_response_code($codeHttp);
        echo json_encode(['ok' => $codeHttp === 200, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
