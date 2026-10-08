<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Accès aux membres.
 * Implémentation de départ : database/fixtures/users.json.
 * À remplacer par PDO : SELECT id, prenom, nom, email, role, password_hash FROM membres WHERE email = :email
 */
final class UserRepository
{
    public static function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        foreach (self::all() as $member) {
            if (mb_strtolower($member['email']) === $email) {
                return $member;
            }
        }
        return null;
    }

    private static function all(): array
    {
        static $cache = null;
        return $cache ??= json_decode((string) file_get_contents(BASE_PATH . '/database/fixtures/users.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
