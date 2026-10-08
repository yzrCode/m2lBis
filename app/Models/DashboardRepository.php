<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Roles;

/**
 * Données du tableau de bord d'UN rôle.
 * Implémentation de départ : database/fixtures/dashboard/{role}.json.
 * À remplacer par vos requêtes SQL : la forme du tableau retourné est le contrat avec dashboard.js.
 *
 * Le rôle est toujours celui du membre connecté (jamais un paramètre d'URL) et passe par la
 * liste blanche de config/roles.php : aucun autre rôle ne peut être chargé.
 */
final class DashboardRepository
{
    public static function forRole(string $role): array
    {
        if (!Roles::exists($role)) {
            throw new \InvalidArgumentException('Rôle inconnu');
        }
        $file = BASE_PATH . '/database/fixtures/dashboard/' . $role . '.json';
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        // Heure du serveur : le compte à rebours ne dépend pas de l'horloge du visiteur.
        $data['meta'] = ['now' => date('c')];
        return $data;
    }

    /** Résout un chemin pointé, ex. « panels.2.rows.1.actions.0 » (utilisé pour valider une action). */
    public static function resolve(array $data, string $path): mixed
    {
        $node = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }
        return $node;
    }
}
