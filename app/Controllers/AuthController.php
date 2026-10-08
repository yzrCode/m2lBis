<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::user() !== null) {
            redirect('/tableau-de-bord');
        }
        View::render('auth/login', [
            'next'   => (string) ($_GET['next'] ?? ''),
            'flash'  => Session::pullFlash(),
            'email'  => Session::pullOld()['email'] ?? '',
            'locked' => Auth::isLocked(),
        ], 'plain');
    }

    public static function login(): void
    {
        if (!Csrf::checkRequest()) {
            Session::flash('error', 'Session expirée, veuillez réessayer.');
            redirect('/connexion');
        }
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (Auth::attempt($email, $password)) {
            redirect(self::safeNext((string) ($_POST['next'] ?? '')));
        }
        Session::keepOld(['email' => $email]);
        Session::flash('error', Auth::isLocked()
            ? 'Trop de tentatives. Réessayez dans une minute.'
            : 'E-mail ou mot de passe incorrect.');
        redirect('/connexion' . ($_POST['next'] ?? '' ? '?next=' . urlencode((string) $_POST['next']) : ''));
    }

    public static function logout(): void
    {
        if (Csrf::checkRequest()) {
            Auth::logout();
        }
        redirect('/');
    }

    /** N'autorise que les redirections internes (pas d'open redirect). */
    private static function safeNext(string $next): string
    {
        return ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\'))
            ? $next : '/tableau-de-bord';
    }
}
