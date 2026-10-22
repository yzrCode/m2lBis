<?php
declare(strict_types=1);

namespace App\Core;

//Associe chaque adresse web à la méthode qui doit la traiter.
final class Routeur
{
    //@var array<int, array{0:string,1:string,2:callable}>
    private array $routes = [];

    public function ajouterRouteGet(string $motif, callable $gestionnaire): void
    {
        $this->ajouterRoute('GET', $motif, $gestionnaire);
    }

    public function ajouterRoutePost(string $motif, callable $gestionnaire): void
    {
        $this->ajouterRoute('POST', $motif, $gestionnaire);
    }

    private function ajouterRoute(string $methode, string $motif, callable $gestionnaire): void
    {
        //Les accolades d'une adresse, comme {id}, deviennent des paramètres transmis au contrôleur.
        $expressionReguliere = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $motif) . '$#';
        $this->routes[] = [$methode, $expressionReguliere, $gestionnaire];
    }

    public function distribuer(string $methode, string $adresse, callable $gestionPageIntrouvable): void
    {
        $chemin = rtrim((string) parse_url($adresse, PHP_URL_PATH), '/') ?: '/';
        foreach ($this->routes as [$m, $expressionReguliere, $gestionnaire]) {
            if ($m === $methode && preg_match($expressionReguliere, $chemin, $correspondances)) {
                $parametres = array_filter($correspondances, 'is_string', ARRAY_FILTER_USE_KEY);
                $gestionnaire(...array_values($parametres));
                return;
            }
        }
        $gestionPageIntrouvable($chemin);
    }
}
