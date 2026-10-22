<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\DepotUtilisateurs;

//Gère la connexion et ne conserve en session que les informations utiles à l'espace membre.
final class Authentification
{
    private const NOMBRE_MAX_ECHECS = 5;
    private const DUREE_BLOCAGE_SECONDES = 60;

    //Membre connecté (données de session) ou null.
    public static function membreConnecte(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    //Impose une session ; redirige vers la connexion sinon.
    public static function exigerConnexion(): array
    {
        $membre = self::membreConnecte();
        if ($membre === null) {
            $suite = urlencode($_SERVER['REQUEST_URI'] ?? '/tableau-de-bord');
            rediriger('/connexion?next=' . $suite);
        }
        $chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!empty($membre['doit_changer_mot_de_passe']) && $chemin !== '/mot-de-passe-provisoire') {
            rediriger('/mot-de-passe-provisoire');
        }
        return $membre;
    }

    public static function connexionVerrouillee(): bool
    {
        return ($_SESSION['login_lock_until'] ?? 0) > time();
    }

    public static function verifierIdentifiants(string $courriel, string $motDePasse): bool
    {
        if (self::connexionVerrouillee()) {
            return false;
        }
        $membre = DepotUtilisateurs::chercherParCourriel($courriel);
        //Un hash est toujours calculé/vérifié : le temps de réponse ne révèle pas si l'e-mail existe.
        $empreinte = $membre['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $estValide = password_verify($motDePasse, $empreinte) && $membre !== null && RegistreRoles::existe((string) $membre['role']);

        if (!$estValide) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= self::NOMBRE_MAX_ECHECS) {
                $_SESSION['login_lock_until'] = time() + self::DUREE_BLOCAGE_SECONDES;
                $_SESSION['login_failures'] = 0;
            }
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['login_failures'], $_SESSION['login_lock_until']);
        //Seules ces données transitent en session : nom, prénom, e-mail et rôle du membre connecté.
        $_SESSION['user'] = [
            'id'     => $membre['id'],
            'prenom' => $membre['prenom'],
            'nom'    => $membre['nom'],
            'email'  => $membre['email'],
            'role'   => $membre['role'],
            'doit_changer_mot_de_passe' => (bool) $membre['doit_changer_mot_de_passe'],
        ];
        return true;
    }

    public static function deconnecter(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
    }
}
