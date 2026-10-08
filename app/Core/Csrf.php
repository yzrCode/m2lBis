<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function check(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }

    /** Vérifie le jeton d'un formulaire (_csrf) ou d'un appel fetch (en-tête X-CSRF-Token). */
    public static function checkRequest(): bool
    {
        return self::check($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }
}
