<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Shell;
use App\Core\View;
use App\Models\DashboardRepository;

final class DashboardController
{
    public static function index(): void
    {
        $user = Auth::require();
        $data = DashboardRepository::forRole($user['role']);

        // Le JSON embarqué ne contient QUE les données du rôle du membre connecté.
        $payload = array_diff_key($data, ['lead' => 1, 'counts' => 1]);
        $hour = (int) date('G');

        View::render('dashboard/index', Shell::build(
            $user,
            '/tableau-de-bord',
            'Tableau de bord',
            $data['counts'] ?? []
        ) + [
            'hello'   => ($hour >= 18 || $hour < 5) ? 'Bonsoir' : 'Bonjour',
            'lead'    => $data['lead'],
            'payload' => $payload,
        ], 'app');
    }
}
