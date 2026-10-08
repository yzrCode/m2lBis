<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\UserRepository;

final class Auth
{
    private const MAX_FAILURES = 5;
    private const LOCK_SECONDS = 60;

    /** Membre connecté (données de session) ou null. */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Impose une session ; redirige vers la connexion sinon. */
    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            $next = urlencode($_SERVER['REQUEST_URI'] ?? '/tableau-de-bord');
            redirect('/connexion?next=' . $next);
        }
        return $user;
    }

    public static function isLocked(): bool
    {
        return ($_SESSION['login_lock_until'] ?? 0) > time();
    }

    public static function attempt(string $email, string $password): bool
    {
        if (self::isLocked()) {
            return false;
        }
        $member = UserRepository::findByEmail($email);
        // Un hash est toujours calculé/vérifié : le temps de réponse ne révèle pas si l'e-mail existe.
        $hash = $member['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash) && $member !== null && Roles::exists((string) $member['role']);

        if (!$valid) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= self::MAX_FAILURES) {
                $_SESSION['login_lock_until'] = time() + self::LOCK_SECONDS;
                $_SESSION['login_failures'] = 0;
            }
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['login_failures'], $_SESSION['login_lock_until']);
        // Seules ces données transitent en session : nom, prénom, e-mail et rôle du membre connecté.
        $_SESSION['user'] = [
            'id'     => $member['id'],
            'prenom' => $member['prenom'],
            'nom'    => $member['nom'],
            'email'  => $member['email'],
            'role'   => $member['role'],
        ];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
    }
}
