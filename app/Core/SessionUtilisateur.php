<?php
declare(strict_types=1);

namespace App\Core;

//Gère la session et les messages temporaires transmis à la page suivante.
final class SessionUtilisateur
{
    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_name('m2l_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function messageFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    //Lit puis efface le message temporaire pour qu'il ne s'affiche qu'une fois.
    public static function recupererMessageFlash(): ?array
    {
        //Un message flash est consommé ici pour ne s'afficher qu'une seule fois.
        $messageFlash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $messageFlash;
    }

    //Conserve la saisie d'un formulaire refusé pour le réafficher.
    public static function conserverAncienneSaisie(array $saisie): void
    {
        $_SESSION['old'] = $saisie;
    }

    public static function recupererAncienneSaisie(): array
    {
        $ancienneSaisie = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        return $ancienneSaisie;
    }
}
