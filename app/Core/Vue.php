<?php
declare(strict_types=1);

namespace App\Core;

//Charge une vue et, si demandé, l'insère dans un gabarit commun.
final class Vue
{
    public static function afficher(string $vue, array $donnees = [], ?string $gabarit = null, int $codeHttp = 200): void
    {
        http_response_code($codeHttp);
        $contenu = self::capturer($vue, $donnees);
        echo $gabarit !== null ? self::capturer('layouts/' . $gabarit, $donnees + ['content' => $contenu]) : $contenu;
    }

    private static function capturer(string $vue, array $donnees): string
    {
        extract($donnees, EXTR_SKIP);
        //La mémoire tampon récupère le HTML au lieu de l'afficher avant le gabarit.
        ob_start();
        require BASE_PATH . '/app/Views/' . $vue . '.php';
        return (string) ob_get_clean();
    }
}
