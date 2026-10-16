<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\RegistreRoles;

/**
 * Données du tableau de bord d'UN rôle.
 * Les fixtures fournissent les données de démonstration ; les dossiers du secrétariat viennent de la base.
 * La forme du tableau retourné est le contrat avec dashboard.js.
 *
 * Le rôle est toujours celui du membre connecté (jamais un paramètre d'URL) et passe par la
 * liste blanche de config/roles.php : aucun autre rôle ne peut être chargé.
 */
final class DepotTableauBord
{
    public static function pourRole(string $role): array
    {
        if (!RegistreRoles::existe($role)) {
            throw new \InvalidArgumentException('Rôle inconnu');
        }
        $fichier = BASE_PATH . '/database/fixtures/dashboard/' . $role . '.json';
        $donnees = json_decode((string) file_get_contents($fichier), true, 512, JSON_THROW_ON_ERROR);
        if ($role === 'secretaire') {
            // Les dossiers et leurs indicateurs viennent de MySQL plutôt que des anciennes données de démonstration.
            $donnees = self::integrerDossiers($donnees);
        }
        // Heure du serveur : le compte à rebours ne dépend pas de l'horloge du visiteur.
        $donnees['meta'] = ['now' => date('c')];
        return $donnees;
    }

    private static function integrerDossiers(array $donnees): array
    {
        // Calcule les chiffres affichés à partir des mêmes dossiers que la page de liste.
        $dossiers = DepotDossier::lister();
        $total = count($dossiers);
        $aValider = count(array_filter(
            $dossiers,
            static fn (array $dossier): bool => $dossier['status']['tone'] !== 'ok'
        ));
        $incomplets = count(array_filter(
            $dossiers,
            static fn (array $dossier): bool => $dossier['canRelance']
        ));
        $valides = $total - $aValider;
        $pourcentageValides = $total === 0 ? 0 : (int) round($valides / $total * 100);

        // Remplace les compteurs et le résumé fictifs par les nombres réels de la base.
        $donnees['lead'] = $aValider . ' dossier' . ($aValider === 1 ? '' : 's') . ' à traiter.';
        $donnees['counts']['/dossiers'] = $aValider;
        $donnees['hero'] = [
            'type' => 'focus',
            'title' => 'Dossiers à traiter',
            'value' => (string) $aValider,
            'caption' => $incomplets . ' dossier' . ($incomplets === 1 ? '' : 's') . ' incomplet' . ($incomplets === 1 ? '' : 's'),
            'ring' => [
                'percent' => $pourcentageValides,
                'value' => $pourcentageValides . ' %',
                'label' => 'dossiers validés',
            ],
            'actions' => [
                ['label' => 'Traiter les dossiers', 'kind' => 'primary', 'href' => '#panel-dossiers'],
                ['label' => 'Importer un CSV', 'kind' => 'ghost', 'href' => '#panel-import'],
            ],
        ];

        // Ajoute une tuile KPI calculée depuis la liste courante des dossiers.
        $indicateurDossiers = [
            'label' => 'Dossiers à traiter',
            'value' => (string) $aValider,
            'hint' => $incomplets . ' dossier' . ($incomplets === 1 ? '' : 's') . ' incomplet' . ($incomplets === 1 ? '' : 's'),
            'icon' => 'folder',
            'tone' => $aValider > 0 ? 'warn' : 'ok',
            'chip' => [
                'tone' => $incomplets > 0 ? 'danger' : 'ok',
                'label' => $incomplets . ' incomplet' . ($incomplets === 1 ? '' : 's'),
            ],
        ];
        array_splice($donnees['kpis'], min(1, count($donnees['kpis'])), 0, [$indicateurDossiers]);

        // Transforme les dossiers au format attendu par le composant de tableau de bord.
        $lignes = array_map(static function (array $dossier): array {
            $ligne = [
                'name' => $dossier['name'],
                'email' => $dossier['email'],
                'category' => $dossier['category'],
                'date' => $dossier['date'],
                'pieces' => $dossier['pieces'],
                'status' => $dossier['status'],
            ];
            if ($dossier['canRelance']) {
                $ligne['actions'] = [[
                    'type' => 'relance_dossier',
                    'dossierId' => $dossier['id'],
                    'label' => 'Relancer',
                    'kind' => 'ghost',
                    'toast' => 'Relance enregistrée. Aucun e-mail n’a été envoyé.',
                    'done' => 'Relance enregistrée',
                ]];
            }
            return $ligne;
        }, $dossiers);

        // Supprime le panneau JSON de démonstration et le remplace par les dossiers MySQL.
        $panels = array_values(array_filter(
            $donnees['panels'],
            static fn (array $panneau): bool => ($panneau['type'] ?? '') !== 'dossiers'
        ));
        array_unshift($panels, [
            'type' => 'dossiers',
            'id' => 'dossiers',
            'title' => 'Dossiers déposés',
            'full' => true,
            'rows' => $lignes,
        ]);
        $donnees['panels'] = $panels;

        // Retire les notifications de dossiers fictives avant d'ajouter celle calculée pour la base.
        $notifications = array_values(array_filter(
            $donnees['notifications'],
            static fn (array $notification): bool => !str_contains(
                mb_strtolower($notification['title'] . ' ' . $notification['text']),
                'dossier'
            )
        ));
        if ($incomplets > 0) {
            array_unshift($notifications, [
                'tone' => 'danger',
                'title' => $incomplets . ' dossier' . ($incomplets === 1 ? '' : 's') . ' incomplet' . ($incomplets === 1 ? '' : 's'),
                'text' => 'Consultez la liste des dossiers pour enregistrer une relance. Aucun courriel ne sera envoyé.',
                'time' => 'À l’instant',
                'unread' => true,
            ]);
        }
        $donnees['notifications'] = $notifications;

        return $donnees;
    }

    /** Résout un chemin pointé, ex. « panels.2.rows.1.actions.0 » (utilisé pour valider une action). */
    public static function resoudre(array $donnees, string $chemin): mixed
    {
        $noeud = $donnees;
        foreach (explode('.', $chemin) as $segment) {
            if (!is_array($noeud) || !array_key_exists($segment, $noeud)) {
                return null;
            }
            $noeud = $noeud[$segment];
        }
        return $noeud;
    }
}
