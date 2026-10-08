<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\DashboardRepository;

/**
 * Point d'entrée des actions du tableau de bord (Valider, Relancer, Confirmer…).
 *
 * Sécurité : le navigateur n'envoie qu'un CHEMIN (ex. « panels.1.rows.0.actions.0 »). Le serveur
 * reconstruit le tableau de bord du rôle connecté et vérifie que ce chemin désigne bien une action
 * de CE rôle. Un membre ne peut donc pas déclencher l'action d'un autre rôle.
 * Ici l'action est journalisée ; branchez votre logique métier à l'endroit indiqué.
 */
final class ActionController
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = Auth::user();
        if ($user === null) {
            self::reply(401, 'Non connecté');
        }
        if (!Csrf::checkRequest()) {
            self::reply(419, 'Jeton de sécurité invalide');
        }
        $body = json_decode((string) file_get_contents('php://input'), true);
        $path = is_array($body) ? (string) ($body['path'] ?? '') : '';
        if (!preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)*$/i', $path)) {
            self::reply(422, 'Chemin invalide');
        }

        $node = DashboardRepository::resolve(DashboardRepository::forRole($user['role']), $path);
        $isAction = is_array($node) && isset($node['toast']) && str_contains($path, '.actions.');
        $isAttendance = is_array($node) && ($node['type'] ?? '') === 'attendance' && preg_match('/^panels\.\d+$/', $path);
        if (!$isAction && !$isAttendance) {
            self::reply(403, 'Action non autorisée pour votre rôle');
        }

        // TODO métier : appelez ici votre service (valider un dossier, envoyer une relance, etc.)
        $entry = ['at' => date('c'), 'member' => $user['id'], 'role' => $user['role'], 'path' => $path,
                  'label' => $node['label'] ?? ($node['title'] ?? ''), 'data' => $isAttendance ? ($body['data'] ?? null) : null];
        @file_put_contents(BASE_PATH . '/storage/actions.jsonl', json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

        self::reply(200, 'OK');
    }

    private static function reply(int $status, string $message): never
    {
        http_response_code($status);
        echo json_encode(['ok' => $status === 200, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
