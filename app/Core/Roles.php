<?php
declare(strict_types=1);

namespace App\Core;

/** Registre des rôles (RBAC) : libellé, menu et action principale de chaque rôle. */
final class Roles
{
    private static ?array $all = null;

    public static function all(): array
    {
        return self::$all ??= require BASE_PATH . '/app/Config/roles.php';
    }

    public static function exists(string $role): bool
    {
        return isset(self::all()[$role]);
    }

    public static function get(string $role): array
    {
        return self::all()[$role] ?? throw new \InvalidArgumentException('Rôle inconnu');
    }

    /** L'élément de menu correspondant à un chemin pour ce rôle, s'il existe. */
    public static function menuItem(string $role, string $path): ?array
    {
        foreach (self::get($role)['menu'] as $item) {
            if ($item['href'] === $path) {
                return $item;
            }
        }
        return null;
    }

    /** Ce chemin appartient-il au menu d'un autre rôle ? (403 plutôt que 404) */
    public static function pathExistsElsewhere(string $path): bool
    {
        foreach (self::all() as $cfg) {
            foreach ($cfg['menu'] as $item) {
                if ($item['href'] === $path) {
                    return true;
                }
            }
        }
        return false;
    }
}
