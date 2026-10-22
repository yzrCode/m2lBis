<?php
declare(strict_types=1);


//Serveur PHP intégré : laisse passer les fichiers statiques.
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
    ControleurLicencie,
    ControleurCotisation,
    ControleurCertificat,
    ControleurNotification,
    ControleurMotDePasse,
    ControleurInscription,
    ControleurDossier,
    ControleurEquipe,
    ControleurFinance
};
use App\Core\{Routeur, SessionUtilisateur};

SessionUtilisateur::demarrer();

//En-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; frame-ancestors 'none'; form-action 'self'");

$router = new Routeur();

//Public
$router->ajouterRouteGet('/', [ControleurAccueil::class, 'afficher']);
$router->ajouterRouteGet('/calendrier', [ControleurCalendrier::class, 'afficher']);
$router->ajouterRouteGet('/calendrier.ics', [ControleurCalendrier::class, 'exporterCalendrier']);
$router->ajouterRoutePost('/inscription', [ControleurInscription::class, 'enregistrer']);

//Authentification
$router->ajouterRouteGet('/connexion', [ControleurConnexion::class, 'afficherConnexion']);
$router->ajouterRoutePost('/connexion', [ControleurConnexion::class, 'traiterConnexion']);
$router->ajouterRouteGet('/mot-de-passe-provisoire', [ControleurMotDePasse::class, 'afficher']);
$router->ajouterRoutePost('/mot-de-passe-provisoire', [ControleurMotDePasse::class, 'enregistrer']);
$router->ajouterRoutePost('/deconnexion', [ControleurConnexion::class, 'deconnecter']);
$router->ajouterRouteGet('/creation-compte', [ControleurDemandesCompte::class, 'afficherFormulaire']);
$router->ajouterRoutePost('/creation-compte', [ControleurDemandesCompte::class, 'envoyerDemande']);

//Espace membre
$router->ajouterRouteGet('/tableau-de-bord', [ControleurTableauBord::class, 'afficher']);
$router->ajouterRouteGet('/utilisateurs', [ControleurDemandesCompte::class, 'afficherDemandes']);
$router->ajouterRoutePost('/utilisateurs/demandes/{id}/accepter', [ControleurDemandesCompte::class, 'accepterDemande']);
$router->ajouterRoutePost('/utilisateurs/demandes/{id}/refuser', [ControleurDemandesCompte::class, 'refuserDemande']);
$router->ajouterRoutePost('/api/actions', [ControleurActions::class, 'traiter']);
$router->ajouterRoutePost('/api/notifications/lire', [ControleurNotification::class, 'lire']);
$router->ajouterRouteGet('/finances', [ControleurFinance::class, 'index']);
$router->ajouterRoutePost('/finances/achats/payer', [ControleurFinance::class, 'validerPaiement']);
$router->ajouterRouteGet('/equipes', [ControleurEquipe::class, 'lister']);
$router->ajouterRouteGet('/equipes/{id}', [ControleurEquipe::class, 'afficherEffectif']);
$router->ajouterRoutePost('/equipes/educateur', [ControleurEquipe::class, 'changerEducateur']);
$router->ajouterRoutePost('/licencies/import', [ControleurImport::class, 'enregistrer']);
$router->ajouterRouteGet('/licencies/export.csv', [ControleurLicencie::class, 'exporterCsv']);
$router->ajouterRouteGet('/licencies/export.xlsx', [ControleurLicencie::class, 'exporterExcel']);
$router->ajouterRouteGet('/licencies/imprimer', [ControleurLicencie::class, 'imprimer']);
$router->ajouterRouteGet('/licencies/nouveau', [ControleurLicencie::class, 'afficherNouveau']);
$router->ajouterRoutePost('/licencies/nouveau', [ControleurLicencie::class, 'creer']);
$router->ajouterRouteGet('/licencies/{id}/modifier', [ControleurLicencie::class, 'afficherModification']);
$router->ajouterRoutePost('/licencies/{id}/modifier', [ControleurLicencie::class, 'modifier']);
$router->ajouterRoutePost('/licencies/{id}/desactiver', [ControleurLicencie::class, 'desactiver']);
$router->ajouterRoutePost('/licencies/{id}/reactiver', [ControleurLicencie::class, 'reactiver']);
$router->ajouterRouteGet('/licencies/{id}', [ControleurLicencie::class, 'afficher']);
$router->ajouterRouteGet('/licencies', [ControleurLicencie::class, 'lister']);
$router->ajouterRouteGet('/cotisations', [ControleurCotisation::class, 'lister']);
$router->ajouterRoutePost('/cotisations/saisons', [ControleurCotisation::class, 'creerSaison']);
$router->ajouterRoutePost('/cotisations/{id}/modifier', [ControleurCotisation::class, 'modifier']);
$router->ajouterRouteGet('/certificats', [ControleurCertificat::class, 'lister']);
$router->ajouterRoutePost('/certificats/{id}/modifier', [ControleurCertificat::class, 'modifier']);
$router->ajouterRouteGet('/dossiers/nouveau', [ControleurDossier::class, 'afficherNouveau']);
$router->ajouterRoutePost('/dossiers/nouveau', [ControleurDossier::class, 'creer']);
$router->ajouterRouteGet('/dossiers', [ControleurDossier::class, 'lister']);

//Reçoit le formulaire CSRF de relance pour un dossier précis.
$router->ajouterRoutePost('/dossiers/{id}/relancer', [ControleurDossier::class, 'relancer']);

//Sections du menu (RBAC) puis 404
$router->distribuer($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], [ControleurAccueil::class, 'pageIntrouvable']);
