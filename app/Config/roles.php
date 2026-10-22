<?php
declare(strict_types=1);

//Registre RBAC : un rôle = un libellé, une action principale et un menu.
//Un membre ne voit et n'atteint QUE les entrées de son propre rôle
//(contrôle serveur dans ControleurModule et ControleurActions).
//`section` inscrit un intertitre dans le menu au-dessus de l'élément.
$dash = ['section' => 'Principal', 'label' => 'Tableau de bord', 'icon' => 'dashboard', 'href' => '/tableau-de-bord'];

return [

    'licencie' => [
        'label' => 'Licencié / Parent',
        'primaryAction' => ['label' => 'Déposer un document', 'icon' => 'upload', 'href' => '/dossier'],
        'menu' => [
            $dash,
            ['section' => 'Mon espace', 'label' => 'Mes convocations', 'icon' => 'send', 'href' => '/convocations'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Mon dossier', 'icon' => 'folder', 'href' => '/dossier'],
            ['label' => 'Cotisations', 'icon' => 'euro', 'href' => '/cotisations'],
        ],
    ],

    'educateur' => [
        'label' => 'Éducateur',
        'primaryAction' => ['label' => 'Créer une convocation', 'icon' => 'plus', 'href' => '/convocations/nouvelle'],
        'menu' => [
            $dash,
            ['section' => 'Mon équipe', 'label' => 'Mes équipes', 'icon' => 'users', 'href' => '/equipes'],
            ['label' => 'Convocations', 'icon' => 'send', 'href' => '/convocations'],
            ['label' => 'Présences', 'icon' => 'clipboard', 'href' => '/presences'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
        ],
    ],

    'secretaire' => [
        'label' => 'Secrétaire',
        'primaryAction' => ['label' => 'Ajouter un licencié', 'icon' => 'plus', 'href' => '/licencies/nouveau'],
        'menu' => [
            $dash,
            ['section' => 'Secrétariat', 'label' => 'Licenciés', 'icon' => 'users', 'href' => '/licencies'],
            ['label' => 'Dossiers', 'icon' => 'folder', 'href' => '/dossiers'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Certificats', 'icon' => 'shield-check', 'href' => '/certificats'],
            ['label' => 'Cotisations', 'icon' => 'euro', 'href' => '/cotisations'],
            ['label' => 'Importer des licenciés', 'icon' => 'upload', 'href' => '/tableau-de-bord#panel-import'],
        ],
    ],

    'president' => [
        'label' => 'Président',
        'primaryAction' => ['label' => 'Exporter le rapport', 'icon' => 'download', 'href' => '/rapports/saison'],
        'menu' => [
            $dash,
            ['section' => 'Pilotage', 'label' => 'Licenciés', 'icon' => 'users', 'href' => '/licencies'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Équipes', 'icon' => 'trophy', 'href' => '/equipes'],
            ['label' => 'Finances', 'icon' => 'euro', 'href' => '/finances'],
            ['section' => 'Administration', 'label' => 'Utilisateurs et rôles', 'icon' => 'shield', 'href' => '/utilisateurs'],
        ],
    ],

    'tresorier' => [
        'label' => 'Trésorier',
        'primaryAction' => ['label' => 'Saisir une opération', 'icon' => 'plus', 'href' => '/operations/nouvelle'],
        'menu' => [
            $dash,
            ['section' => 'Trésorerie', 'label' => 'Cotisations', 'icon' => 'euro', 'href' => '/cotisations'],
            ['label' => 'Finances', 'icon' => 'chart', 'href' => '/finances'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Opérations', 'icon' => 'clipboard', 'href' => '/operations'],
            ['label' => 'Budget', 'icon' => 'chart', 'href' => '/budget'],
            ['label' => 'Notes de frais', 'icon' => 'file', 'href' => '/notes-de-frais'],
            ['label' => 'Rapports', 'icon' => 'download', 'href' => '/rapports'],
        ],
    ],

    'service_civique' => [
        'label' => 'Service civique',
        'primaryAction' => ['label' => 'Déclarer des heures', 'icon' => 'plus', 'href' => '/heures/nouvelle'],
        'menu' => [
            $dash,
            ['section' => 'Ma mission', 'label' => 'Mes missions', 'icon' => 'clipboard', 'href' => '/missions'],
            ['label' => 'Mes heures', 'icon' => 'clock', 'href' => '/heures'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Formations', 'icon' => 'graduation', 'href' => '/formations'],
            ['label' => 'Mon dossier', 'icon' => 'folder', 'href' => '/dossier-mission'],
        ],
    ],

    'arbitre' => [
        'label' => 'Arbitre',
        'primaryAction' => ['label' => 'Saisir un rapport de match', 'icon' => 'plus', 'href' => '/rapports-match/nouveau'],
        'menu' => [
            $dash,
            ['section' => 'Arbitrage', 'label' => 'Désignations', 'icon' => 'flag', 'href' => '/designations'],
            ['label' => 'Disponibilités', 'icon' => 'calendar', 'href' => '/disponibilites'],
            ['label' => 'Calendrier du club', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Rapports de match', 'icon' => 'file', 'href' => '/rapports-match'],
            ['label' => 'Licence et formation', 'icon' => 'shield-check', 'href' => '/licence-arbitre'],
        ],
    ],

    'resp_evenementiel' => [
        'label' => 'Responsable événementiel',
        'primaryAction' => ['label' => 'Créer un événement', 'icon' => 'plus', 'href' => '/evenements/nouveau'],
        'menu' => [
            $dash,
            ['section' => 'Événements', 'label' => 'Événements', 'icon' => 'star', 'href' => '/evenements'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Bénévoles', 'icon' => 'users', 'href' => '/benevoles'],
            ['label' => 'Partenaires', 'icon' => 'trophy', 'href' => '/partenaires'],
            ['label' => 'Budget événements', 'icon' => 'euro', 'href' => '/budget-evenements'],
        ],
    ],

    'resp_achats' => [
        'label' => 'Responsable achats',
        'primaryAction' => ['label' => 'Nouvelle commande', 'icon' => 'plus', 'href' => '/commandes/nouvelle'],
        'menu' => [
            $dash,
            ['section' => 'Achats', 'label' => 'Commandes', 'icon' => 'cart', 'href' => '/commandes'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Fournisseurs', 'icon' => 'users', 'href' => '/fournisseurs'],
            ['label' => 'Stocks', 'icon' => 'package', 'href' => '/stocks'],
            ['label' => 'Demandes internes', 'icon' => 'send', 'href' => '/demandes'],
        ],
    ],

    'resp_tournois' => [
        'label' => 'Responsable organisation des tournois',
        'primaryAction' => ['label' => 'Créer un tournoi', 'icon' => 'plus', 'href' => '/tournois/nouveau'],
        'menu' => [
            $dash,
            ['section' => 'Tournois', 'label' => 'Tournois', 'icon' => 'trophy', 'href' => '/tournois'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Équipes inscrites', 'icon' => 'users', 'href' => '/equipes-inscrites'],
            ['label' => 'Terrains', 'icon' => 'pin', 'href' => '/terrains'],
            ['label' => 'Planning', 'icon' => 'calendar', 'href' => '/planning-tournoi'],
        ],
    ],

    'resp_ecole_foot' => [
        'label' => 'Responsable école de foot',
        'primaryAction' => ['label' => 'Planifier un plateau', 'icon' => 'plus', 'href' => '/plateaux/nouveau'],
        'menu' => [
            $dash,
            ['section' => 'École de foot', 'label' => 'Groupes', 'icon' => 'users', 'href' => '/groupes'],
            ['label' => 'Calendrier', 'icon' => 'calendar', 'href' => '/calendrier'],
            ['label' => 'Plateaux', 'icon' => 'trophy', 'href' => '/plateaux'],
            ['label' => 'Animateurs', 'icon' => 'graduation', 'href' => '/animateurs'],
            ['label' => 'Présences', 'icon' => 'clipboard', 'href' => '/presences-ecole'],
        ],
    ],
];
