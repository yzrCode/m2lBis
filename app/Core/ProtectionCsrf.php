<?php
declare(strict_types=1);

namespace App\Core;

//Protège les formulaires et les appels AJAX contre les requêtes provenant d'un autre site.
final class ProtectionCsrf
{
    //Le même jeton est réutilisé pendant la session pour pouvoir vérifier les formulaires envoyés.
    public static function jeton(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifier(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::jeton(), $token);
    }

    //Vérifie le jeton d'un formulaire (_csrf) ou d'un appel fetch (en-tête X-CSRF-Token).
    public static function verifierRequete(): bool
    {
        return self::verifier($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }
}
