<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\ClubRepository;
use App\Models\DashboardRepository;

/** Données communes à toutes les pages de l'espace membre (menu, profil, saison). */
final class Shell
{
    public static function build(array $user, string $currentPath, string $title, ?array $counts = null): array
    {
        $role = $user['role'];
        $cfg = Roles::get($role);
        // Réutilise les données déjà chargées par le tableau de bord.
        $counts ??= DashboardRepository::forRole($role)['counts'] ?? [];

        $menu = array_map(static fn (array $item) => $item + [
            'current' => $item['href'] === $currentPath,
            'count'   => $counts[$item['href']] ?? null,
        ], $cfg['menu']);

        return [
            'user'          => $user,
            'fullName'      => $user['prenom'] . ' ' . $user['nom'],
            'roleLabel'     => $cfg['label'],
            'menu'          => $menu,
            'primaryAction' => $cfg['primaryAction'],
            'season'        => ClubRepository::season(),
            'pageTitle'     => $title,
            'flash'         => Session::pullFlash(),
        ];
    }
}
