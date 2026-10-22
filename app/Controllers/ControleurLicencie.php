<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotLicencie;
use App\Services\ServiceCourriel;
use DateTimeImmutable;
use PDOException;
use PHPMailer\PHPMailer\Exception as ErreurCourriel;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//Gère l'annuaire des licenciés et les actions de la secrétaire.
final class ControleurLicencie
{
    //Affiche l'annuaire avec les filtres demandés dans l'adresse web.
    public static function lister(): void
    {
        $membre = self::exigerSecretaire();
        if ($membre === null) {
            return;
        }

        $filtres = self::filtres();
        Vue::afficher('licencies/index', PreparationEspaceMembre::preparer(
            $membre,
            '/licencies',
            'Licenciés'
        ) + [
            'licencies' => DepotLicencie::lister($filtres),
            'categories' => DepotLicencie::categories(),
            'filtres' => $filtres,
            'saison' => DepotLicencie::saisonCourante(),
        ], 'app');
    }

    //Affiche le formulaire pour ajouter un compte de licencié.
    public static function afficherNouveau(): void
    {
        $membre = self::exigerSecretaire();
        if ($membre === null) {
            return;
        }

        Vue::afficher('licencies/nouveau', PreparationEspaceMembre::preparer(
            $membre,
            '/licencies',
            'Ajouter un licencié'
        ) + [
            'old' => SessionUtilisateur::recupererAncienneSaisie(),
        ], 'app');
    }

    //Valide le formulaire puis crée le compte et son dossier associé.
    public static function creer(): void
    {
        if (self::exigerSecretaire() === null) {
            return;
        }
        if (!ProtectionCsrf::verifierRequete()) {
            self::echouerFormulaire([], 'Session expirée, veuillez réessayer.');
        }

        $licencie = self::lireFormulaire();
        self::validerFormulaire($licencie);

        try {
            //Le serveur SMTP est testé avant l'ajout afin de ne pas créer un compte sans accès transmissible.
            ServiceCourriel::verifierConfiguration();
        } catch (RuntimeException $erreur) {
            self::echouerFormulaire($licencie, $erreur->getMessage());
        }

        $motDePasse = bin2hex(random_bytes(12));
        try {
            //Le dépôt enregistre le compte et le dossier dans une seule transaction.
            DepotLicencie::creer($licencie, password_hash($motDePasse, PASSWORD_DEFAULT));
        } catch (RuntimeException $erreur) {
            self::echouerFormulaire($licencie, $erreur->getMessage());
        } catch (PDOException $erreur) {
            error_log('Création d’un licencié : ' . $erreur->getMessage());
            self::echouerFormulaire($licencie, 'Le licencié n’a pas pu être ajouté. Vérifiez ses informations.');
        }

        try {
            //L'adresse e-mail de connexion reçoit le mot de passe qui devra être changé au premier accès.
            ServiceCourriel::envoyerAcces($licencie, $motDePasse);
        } catch (ErreurCourriel $erreur) {
            error_log('Échec d’envoi des accès à ' . $licencie['email'] . ' : ' . $erreur->getMessage());
            self::afficherAccesNonEnvoye($licencie, $motDePasse);
            return;
        }

        SessionUtilisateur::messageFlash('success', 'Le licencié a été ajouté et ses accès lui ont été envoyés.');
        rediriger('/licencies');
    }

    //Affiche une fiche détaillée pour le licencié choisi.
    public static function afficher(string $identifiant): void
    {
        $membre = self::exigerSecretaire();
        if ($membre === null) {
            return;
        }

        $id = self::identifiantValide($identifiant);
        $licencie = $id === null ? null : DepotLicencie::trouver($id);
        if ($licencie === null) {
            self::notFound($membre);
            return;
        }

        Vue::afficher('licencies/fiche', PreparationEspaceMembre::preparer(
            $membre,
            '/licencies',
            'Fiche licencié'
        ) + [
            'licencie' => $licencie,
            'saison' => DepotLicencie::saisonCourante(),
        ], 'app');
    }

    //Affiche le formulaire de modification d'un licencié.
    public static function afficherModification(string $identifiant): void
    {
        $membre = self::exigerSecretaire();
        if ($membre === null) {
            return;
        }

        $id = self::identifiantValide($identifiant);
        $licencie = $id === null ? null : DepotLicencie::trouver($id);
        if ($licencie === null) {
            self::notFound($membre);
            return;
        }

        Vue::afficher('licencies/modifier', PreparationEspaceMembre::preparer(
            $membre,
            '/licencies',
            'Modifier un licencié'
        ) + [
            'licencie' => $licencie,
            'saison' => DepotLicencie::saisonCourante(),
            'old' => SessionUtilisateur::recupererAncienneSaisie(),
        ], 'app');
    }

    //Vérifie et enregistre les nouvelles informations du licencié.
    public static function modifier(string $identifiant): void
    {
        if (self::exigerSecretaire() === null) {
            return;
        }
        $id = self::identifiantValide($identifiant);
        if ($id === null) {
            rediriger('/licencies');
        }
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/licencies/' . $id . '/modifier');
        }

        $licencie = self::lireFormulaire();
        self::validerFormulaire($licencie, false, '/licencies/' . $id . '/modifier');

        try {
            if (!DepotLicencie::modifier($id, $licencie)) {
                self::notFound(Authentification::exigerConnexion());
                return;
            }
        } catch (RuntimeException $erreur) {
            SessionUtilisateur::conserverAncienneSaisie($licencie);
            SessionUtilisateur::messageFlash('error', $erreur->getMessage());
            rediriger('/licencies/' . $id . '/modifier');
        } catch (PDOException $erreur) {
            error_log('Modification d’un licencié : ' . $erreur->getMessage());
            SessionUtilisateur::conserverAncienneSaisie($licencie);
            SessionUtilisateur::messageFlash('error', 'La modification a échoué. Vérifiez l’adresse et le numéro de licence.');
            rediriger('/licencies/' . $id . '/modifier');
        }

        SessionUtilisateur::messageFlash('success', 'Les informations du licencié ont été modifiées.');
        rediriger('/licencies/' . $id);
    }

    //Désactive un compte sans supprimer son dossier ni son historique.
    public static function desactiver(string $identifiant): void
    {
        self::changerEtat($identifiant, false);
    }

    //Réactive un compte qui avait été désactivé.
    public static function reactiver(string $identifiant): void
    {
        self::changerEtat($identifiant, true);
    }

    //Exporte les lignes filtrées dans un fichier CSV lisible par Excel.
    public static function exporterCsv(): void
    {
        if (self::exigerSecretaire() === null) {
            return;
        }

        $licencies = DepotLicencie::lister(self::filtres());
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="licencies.csv"');
        $fichier = fopen('php://output', 'w');
        if ($fichier === false) {
            throw new RuntimeException('Impossible de préparer le fichier CSV.');
        }

        //Le BOM aide Excel à reconnaître correctement les accents du fichier UTF-8.
        fwrite($fichier, "\xEF\xBB\xBF");
        fputcsv($fichier, ['Nom', 'Prénom', 'Catégorie', 'N° de licence', 'Téléphone', 'E-mail', 'Statut', 'Cotisation'], ';', '"', '\\');
        foreach ($licencies as $ligne) {
            fputcsv($fichier, array_map(
                static fn (mixed $valeur): string => self::protegerCelluleCsv((string) ($valeur ?? '')),
                [
                    $ligne['nom'],
                    $ligne['prenom'],
                    $ligne['categorie'] ?? '',
                    $ligne['num_licence'] ?? '',
                    $ligne['telephone'] ?? '',
                    $ligne['email'],
                    $ligne['actif'] ? 'Actif' : 'Inactif',
                    $ligne['cotisation_affichage']['label'],
                ]
            ), ';', '"', '\\');
        }
        fclose($fichier);
        exit;
    }

    //Exporte les lignes filtrées dans un classeur Excel.
    public static function exporterExcel(): void
    {
        if (self::exigerSecretaire() === null) {
            return;
        }

        $licencies = DepotLicencie::lister(self::filtres());
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet();
        $feuille->setTitle('Licenciés');
        $entetes = ['Nom', 'Prénom', 'Catégorie', 'N° de licence', 'Téléphone', 'E-mail', 'Statut', 'Cotisation'];

        //Les données sont écrites comme du texte pour qu'Excel ne les interprète pas comme des formules.
        foreach ($entetes as $index => $entete) {
            $cellule = Coordinate::stringFromColumnIndex($index + 1) . '1';
            $feuille->setCellValueExplicit($cellule, $entete, DataType::TYPE_STRING);
        }
        foreach ($licencies as $index => $ligne) {
            $valeurs = [
                $ligne['nom'],
                $ligne['prenom'],
                $ligne['categorie'] ?? '',
                $ligne['num_licence'] ?? '',
                $ligne['telephone'] ?? '',
                $ligne['email'],
                $ligne['actif'] ? 'Actif' : 'Inactif',
                $ligne['cotisation_affichage']['label'],
            ];
            foreach ($valeurs as $colonne => $valeur) {
                $cellule = Coordinate::stringFromColumnIndex($colonne + 1) . ($index + 2);
                $feuille->setCellValueExplicit(
                    $cellule,
                    (string) $valeur,
                    DataType::TYPE_STRING
                );
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="licencies.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($classeur))->save('php://output');
        exit;
    }

    //Ouvre une page simplifiée que le navigateur peut imprimer ou enregistrer en PDF.
    public static function imprimer(): void
    {
        $membre = self::exigerSecretaire();
        if ($membre === null) {
            return;
        }

        Vue::afficher('licencies/imprimer', [
            'licencies' => DepotLicencie::lister(self::filtres()),
            'filtres' => self::filtres(),
            'nomComplet' => $membre['prenom'] . ' ' . $membre['nom'],
        ]);
    }

    //Contrôle que la personne connectée est bien la secrétaire.
    private static function exigerSecretaire(): ?array
    {
        $membre = Authentification::exigerConnexion();
        if (in_array($membre['role'],['secretaire', 'president'],true)) {
            return $membre;
        }

        Vue::afficher('errors/error', [
            'status' => 403,
            'title' => 'Accès refusé',
            'text' => "Cette fonction est réservée au secrétariat.",
            'member' => $membre,
        ], 'plain', 403);
        return null;
    }

    //Récupère les filtres et refuse les statuts qui ne font pas partie de la liste autorisée.
    private static function filtres(): array
    {
        $recherche = $_GET['q'] ?? '';
        $categorie = $_GET['categorie'] ?? '';
        $statut = $_GET['statut'] ?? '';
        $recherche = is_string($recherche) ? trim($recherche) : '';
        $categorie = is_string($categorie) ? trim($categorie) : '';
        $statut = is_string($statut) ? $statut : '';

        return [
            'recherche' => mb_substr($recherche, 0, 100),
            'categorie' => mb_substr($categorie, 0, 20),
            'statut' => in_array($statut, [
                'actifs',
                'inactifs',
                'payee',
                'a_payer',
                'en_retard',
                'sans_cotisation',
            ], true) ? $statut : '',
        ];
    }

    //Transforme les champs du formulaire en chaînes simples et normalisées.
    private static function lireFormulaire(): array
    {
        $saisie = [];
        foreach (['prenom', 'nom', 'email', 'telephone', 'num_licence', 'categorie', 'date_naissance', 'cotisation'] as $champ) {
            $valeur = $_POST[$champ] ?? '';
            $saisie[$champ] = is_string($valeur) ? trim($valeur) : '';
        }
        $saisie['email'] = mb_strtolower($saisie['email']);
        $saisie['telephone'] = $saisie['telephone'] === '' ? null : $saisie['telephone'];
        $saisie['num_licence'] = $saisie['num_licence'] === '' ? null : $saisie['num_licence'];
        $saisie['date_naissance'] = $saisie['date_naissance'] === '' ? null : $saisie['date_naissance'];

        return $saisie;
    }

    //Vérifie les longueurs et formats avant de transmettre les données au dépôt.
    private static function validerFormulaire(
        array &$licencie,
        bool $creation = true,
        string $destination = '/licencies/nouveau'
    ): void
    {
        if (
            $licencie['prenom'] === ''
            || $licencie['nom'] === ''
            || mb_strlen($licencie['prenom']) > 60
            || mb_strlen($licencie['nom']) > 60
        ) {
            self::echouerFormulaire($licencie, 'Le prénom et le nom sont obligatoires et limités à 60 caractères.', $destination);
        }
        if (!filter_var($licencie['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($licencie['email']) > 150) {
            self::echouerFormulaire($licencie, 'Saisissez une adresse e-mail valide de 150 caractères maximum.', $destination);
        }
        if (mb_strlen($licencie['telephone'] ?? '') > 20) {
            self::echouerFormulaire($licencie, 'Le téléphone ne doit pas dépasser 20 caractères.', $destination);
        }
        if (
            $licencie['telephone'] !== null
            && !preg_match('/^[0-9+(). -]+$/', $licencie['telephone'])
        ) {
            self::echouerFormulaire($licencie, 'Le numéro de téléphone contient des caractères non autorisés.', $destination);
        }
        if ($licencie['num_licence'] !== null && mb_strlen($licencie['num_licence']) > 40) {
            self::echouerFormulaire($licencie, 'Le numéro de licence ne doit pas dépasser 40 caractères.', $destination);
        }
        if ($licencie['categorie'] === '' || mb_strlen($licencie['categorie']) > 20) {
            self::echouerFormulaire($licencie, 'La catégorie est obligatoire et limitée à 20 caractères.', $destination);
        }

        if ($licencie['date_naissance'] !== null) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $licencie['date_naissance']);
            $erreurs = DateTimeImmutable::getLastErrors();
            if (
                $date === false
                || ($erreurs !== false && ($erreurs['warning_count'] > 0 || $erreurs['error_count'] > 0))
            ) {
                self::echouerFormulaire($licencie, 'La date de naissance n’est pas valide.', $destination);
            }
            if ($date > new DateTimeImmutable('today')) {
                self::echouerFormulaire($licencie, 'La date de naissance ne peut pas être dans le futur.', $destination);
            }
            $licencie['date_naissance'] = $date->format('Y-m-d');
        }

        if (!$creation && !in_array($licencie['cotisation'], ['', 'a_payer', 'payee', 'en_retard'], true)) {
            self::echouerFormulaire($licencie, 'Choisissez un statut de cotisation valide.', $destination);
        }
    }

    //Change l'état d'activité après vérification du jeton du formulaire.
    private static function changerEtat(string $identifiant, bool $actif): void
    {
        if (self::exigerSecretaire() === null) {
            return;
        }
        $id = self::identifiantValide($identifiant);
        if ($id === null || !ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'La demande n’est pas valide. Réessayez.');
            rediriger('/licencies');
        }

        if (!DepotLicencie::definirActif($id, $actif)) {
            SessionUtilisateur::messageFlash('error', 'Licencié introuvable.');
            rediriger('/licencies');
        }
        SessionUtilisateur::messageFlash(
            'success',
            $actif ? 'Le compte du licencié est réactivé.' : 'Le compte du licencié est désactivé.'
        );
        rediriger('/licencies/' . $id);
    }

    //Affiche les identifiants provisoires si l'ajout a réussi mais que le courriel n'a pas pu partir.
    private static function afficherAccesNonEnvoye(array $licencie, string $motDePasse): void
    {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        Vue::afficher('import/acces-a-transmettre', [
            'title' => 'Accès à transmettre',
            'comptes' => [[
                'prenom' => $licencie['prenom'],
                'nom' => $licencie['nom'],
                'email' => $licencie['email'],
                'mot_de_passe_temporaire' => $motDePasse,
            ]],
            'nombreTotal' => 1,
        ], 'plain');
    }

    //Conserve les valeurs pour que la secrétaire n'ait pas à ressaisir le formulaire.
    private static function echouerFormulaire(
        array $saisie,
        string $message,
        string $destination = '/licencies/nouveau'
    ): never
    {
        SessionUtilisateur::conserverAncienneSaisie($saisie);
        SessionUtilisateur::messageFlash('error', $message);
        rediriger($destination);
    }

    //Convertit uniquement les identifiants numériques positifs.
    private static function identifiantValide(string $identifiant): ?int
    {
        $id = filter_var($identifiant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    //Affiche une page 404 lorsque l'identifiant ne correspond à aucun licencié.
    private static function notFound(array $membre): void
    {
        Vue::afficher('errors/error', [
            'status' => 404,
            'title' => 'Licencié introuvable',
            'text' => 'Ce licencié n’existe pas ou son compte n’est pas un compte licencié.',
            'member' => $membre,
        ], 'plain', 404);
    }

    //Ajoute une apostrophe aux données CSV qui pourraient être interprétées comme des formules.
    private static function protegerCelluleCsv(string $valeur): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $valeur) ? "'" . $valeur : $valeur;
    }
}
