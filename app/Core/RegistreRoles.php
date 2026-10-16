<?php
declare(strict_types=1);

namespace App\Core;

/** Registre des rôles (RBAC) : libellé, menu et action principale de chaque rôle. */
final class RegistreRoles
{
    private static ?array $tousRoles = null;

    public static function tous(): array
    {
        return self::$tousRoles ??= require BASE_PATH . '/app/Config/roles.php';
    }

    public static function existe(string $role): bool
    {
        return isset(self::tous()[$role]);
    }

    public static function obtenir(string $role): array
    {
        return self::tous()[$role] ?? throw new \InvalidArgumentException('Rôle inconnu');
    }

    /** L'élément de menu correspondant à un chemin pour ce rôle, s'il existe. */
    public static function elementMenu(string $role, string $chemin): ?array
    {
        foreach (self::obtenir($role)['menu'] as $item) {
            if ($item['href'] === $chemin) {
                return $item;
            }
        }
        return null;
    }

    /** Ce chemin appartient-il au menu d'un autre rôle ? (403 plutôt que 404) */
    public static function cheminConnuDansAutreRole(string $chemin): bool
    {
        foreach (self::tous() as $configuration) {
            foreach ($configuration['menu'] as $item) {
                if ($item['href'] === $chemin) {
                    return true;
                }
            }
        }
        return false;
    }
}
