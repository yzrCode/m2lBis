<?php
declare(strict_types=1);

namespace App\Config;

use PDO;

//Ouvre une connexion PDO réutilisable vers la base MySQL du club.
final class BaseDeDonnees
{
    private string $hote = 'localhost';
    private string $nomBase = 'm2l_football';
    private string $nomUtilisateur = 'root';
    private string $motDePasse = '';

    //Les requêtes préparées et les erreurs PDO explicites aident à éviter les erreurs silencieuses.
    public function obtenirConnexion(): PDO
    {
        $dsn = "mysql:host={$this->hote};dbname={$this->nomBase};charset=utf8mb4";

        return new PDO($dsn, $this->nomUtilisateur, $this->motDePasse, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
