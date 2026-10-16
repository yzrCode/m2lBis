<?php
declare(strict_types=1);


// Serveur PHP intégré : laisse passer les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\{
    ControleurDemandesCompte,
    ControleurActions,
    ControleurConnexion,
    ControleurCalendrier,
    ControleurTableauBord,
    ControleurAccueil,
    ControleurImport,
    ControleurInscription,
    ControleurDossier
};
use App\Core\{Routeur, SessionUtilisateur};

SessionUtilisateur::demarrer();

// En-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; frame-ancestors 'none'; form-action 'self'");

$router = new Routeur();

// Public
$router->ajouterRouteGet('/', [ControleurAccueil::class, 'afficher']);
$router->ajouterRouteGet('/calendrier.ics', [ControleurCalendrier::class, 'exporterCalendrier']);
$router->ajouterRoutePost('/inscription', [ControleurInscription::class, 'enregistrer']);

// Authentification
$router->ajouterRouteGet('/connexion', [ControleurConnexion::class, 'afficherConnexion']);
$router->ajouterRoutePost('/connexion', [ControleurConnexion::class, 'traiterConnexion']);
$router->ajouterRoutePost('/deconnexion', [ControleurConnexion::class, 'deconnecter']);
$router->ajouterRouteGet('/creation-compte', [ControleurDemandesCompte::class, 'afficherFormulaire']);
$router->ajouterRoutePost('/creation-compte', [ControleurDemandesCompte::class, 'envoyerDemande']);

// Espace membre
$router->ajouterRouteGet('/tableau-de-bord', [ControleurTableauBord::class, 'afficher']);
$router->ajouterRouteGet('/utilisateurs', [ControleurDemandesCompte::class, 'afficherDemandes']);
$router->ajouterRoutePost('/utilisateurs/demandes/{id}/accepter', [ControleurDemandesCompte::class, 'accepterDemande']);
$router->ajouterRoutePost('/utilisateurs/demandes/{id}/refuser', [ControleurDemandesCompte::class, 'refuserDemande']);
$router->ajouterRoutePost('/api/actions', [ControleurActions::class, 'traiter']);
$router->ajouterRoutePost('/licencies/import', [ControleurImport::class, 'enregistrer']);
$router->ajouterRouteGet('/dossiers', [ControleurDossier::class, 'lister']);

// Reçoit le formulaire CSRF de relance pour un dossier précis.
$router->ajouterRoutePost('/dossiers/{id}/relancer', [ControleurDossier::class, 'relancer']);

// Sections du menu (RBAC) puis 404
$router->distribuer($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], [ControleurAccueil::class, 'pageIntrouvable']);
