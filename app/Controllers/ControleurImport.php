<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotImport;
use App\Services\ServiceCourriel;
use DomainException;
use InvalidArgumentException;
use PDOException;
use PHPMailer\PHPMailer\Exception as ErreurCourriel;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ErreurLectureFichier;
use RuntimeException;

//Reçoit les fichiers de la secrétaire et coordonne la création des comptes licenciés.
final class ControleurImport
{
    //Cette limite empêche l'envoi de fichiers trop volumineux au serveur.
    private const TAILLE_MAX_OCTETS = 2_097_152; //2 Mo

    public static function enregistrer(): void
    {
        //L'import est réservé au secrétariat.
        //La méthode redirige les visiteurs non connectés vers la page de connexion.
        $membre = Authentification::exigerConnexion();
        if ($membre['role'] !== 'secretaire') {
            //Un membre connecté sans le bon rôle ne doit pas accéder à cette action.
            http_response_code(403);
            exit('Accès refusé.');
        }

        //On vérifie le jeton anti-CSRF envoyé par le formulaire.
        if (!ProtectionCsrf::verifierRequete()) {
            self::echouer('Session expirée. Rechargez la page puis réessayez.');
        }

        //Le navigateur envoie le fichier dans le champ nommé « fichier ».
        $fichier = $_FILES['fichier'] ?? null;
        if (
            !is_array($fichier)
            || !is_int($fichier['error'] ?? null)
            || !is_int($fichier['size'] ?? null)
            || !is_string($fichier['tmp_name'] ?? null)
            || !is_string($fichier['name'] ?? null)
        ) {
            //Les vérifications de type empêchent de traiter des informations d'envoi incomplètes.
            self::echouer('Choisissez un fichier CSV ou Excel.');
        }

        //PHP indique si le transfert du fichier s'est terminé correctement.
        $erreurEnvoi = $fichier['error'];
        if ($erreurEnvoi !== UPLOAD_ERR_OK) {
            self::echouer($erreurEnvoi === UPLOAD_ERR_INI_SIZE || $erreurEnvoi === UPLOAD_ERR_FORM_SIZE
                ? 'Le fichier dépasse la taille maximale autorisée.'
                : 'Le fichier n’a pas pu être envoyé. Réessayez.');
        }

        //Le fichier envoyé est stocké temporairement par PHP avant son traitement.
        $cheminTemporaire = $fichier['tmp_name'];
        if (!is_uploaded_file($cheminTemporaire)) {
            self::echouer('Le fichier reçu n’est pas un envoi valide.');
        }
        if ($fichier['size'] > self::TAILLE_MAX_OCTETS) {
            self::echouer('Le fichier ne doit pas dépasser 2 Mo.');
        }

        //L'extension détermine si le dépôt doit utiliser le lecteur CSV ou Excel.
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            self::echouer('Choisissez un fichier .csv ou .xlsx.');
        }

        try {
            //Le dépôt lit le fichier et vérifie les colonnes et les valeurs de chaque licencié.
            $licencies = DepotImport::lireFichier($cheminTemporaire, $extension);
            //On teste la connexion au serveur de messagerie avant de créer les comptes.
            ServiceCourriel::verifierConfiguration();
            //Les comptes et les dossiers sont enregistrés ensemble dans une transaction SQL.
            $comptes = DepotImport::importer($licencies);
        } catch (InvalidArgumentException | DomainException | RuntimeException | ErreurLectureFichier $erreur) {
            //Les erreurs de fichier, de données ou de messagerie sont affichées à la secrétaire.
            self::echouer($erreur->getMessage());
        } catch (PDOException $erreur) {
            //Le détail technique est conservé dans le journal du serveur, pas dans la page affichée.
            error_log('Import des licenciés : ' . $erreur->getMessage());
            self::echouer('Une erreur de base de données a empêché l’import. Aucun licencié n’a été ajouté.');
        }

        //L'envoi se fait après l'enregistrement du lot en base.
        $courrielsNonEnvoyes = [];
        foreach ($comptes as $compte) {
            try {
                //Chaque licencié reçoit son propre message avec son adresse et son mot de passe provisoire.
                ServiceCourriel::envoyerAcces($compte, $compte['mot_de_passe_temporaire']);
            } catch (ErreurCourriel $erreur) {
                //Un échec d'envoi n'annule pas les autres messages déjà envoyés.
                error_log('Échec d’envoi des accès à ' . $compte['email'] . ' : ' . $erreur->getMessage());
                $courrielsNonEnvoyes[] = $compte;
            }
        }

        if ($courrielsNonEnvoyes !== []) {
            //Les mots de passe ne sont montrés qu'aux échecs d'envoi et dans cette réponse non mise en cache.
            //Ces en-têtes empêchent le navigateur et les caches intermédiaires de conserver la page sensible.
            header('Cache-Control: no-store, private');
            header('Pragma: no-cache');
            //La secrétaire peut transmettre manuellement les accès dont l'envoi a échoué.
            Vue::afficher('import/acces-a-transmettre', [
                'title' => 'Accès à transmettre',
                'comptes' => $courrielsNonEnvoyes,
                'nombreTotal' => count($comptes),
            ], 'plain');
            return;
        }

        //Si tous les messages sont partis, un récapitulatif confirme le nombre de comptes créés.
        $nombreLicencies = count($comptes);
        SessionUtilisateur::messageFlash(
            'success',
            $nombreLicencies . ' licencié' . ($nombreLicencies === 1 ? ' ajouté' : 's ajoutés')
                . '. Les accès provisoires ont été envoyés par e-mail.'
        );
        rediriger('/tableau-de-bord');
    }

    private static function echouer(string $message): never
    {
        //Le message flash sera affiché après le retour au tableau de bord.
        SessionUtilisateur::messageFlash('error', $message);
        rediriger('/tableau-de-bord#panel-import');
    }
}
