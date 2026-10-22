<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use Throwable;

//Depot qui lit les fichiers de licenciés et crée les comptes et dossiers associés.
final class DepotImport
{
    //Ces noms doivent correspondre aux colonnes des modèles CSV et Excel.
    private const COLONNES_REQUISES = [
        'nom',
        'prenom',
        'date_naissance',
        'categorie',
        'email_licencie',
        'email_responsable',
    ];

    public static function lireFichier(string $chemin, string $extension): array
    {
        //Le format du fichier détermine le lecteur utilisé.
        if ($extension === 'xlsx') {
            //Le lecteur Excel récupère les cellules de la feuille active sous forme de lignes.
            $lecteur = IOFactory::createReader('Xlsx');
            $lecteur->setReadDataOnly(true);
            $classeur = $lecteur->load($chemin);
            $lignes = $classeur->getActiveSheet()->toArray(null, true, true, false);
        } elseif ($extension === 'csv') {
            //Le lecteur CSV détecte le séparateur et l'encodage du fichier.
            $lignes = self::lireCsv($chemin);
        } else {
            throw new InvalidArgumentException('Le format doit être .csv ou .xlsx.');
        }

        //Les deux formats suivent ensuite les mêmes contrôles.
        return self::validerLignes($lignes);
    }

    public static function importer(array $licencies): array
    {
        //Un fichier sans licencié ne doit pas ouvrir de transaction en base.
        if ($licencies === []) {
            throw new InvalidArgumentException('Aucun licencié à importer.');
        }

        $connexion = (new BaseDeDonnees())->obtenirConnexion();
        //Le lot est entièrement validé ou entièrement annulé en cas d'erreur.
        $connexion->beginTransaction();
        $comptesCrees = [];

        try {
            //Les requêtes préparées protègent les données saisies dans le fichier.
            $verifierCourriel = $connexion->prepare(
                'SELECT 1 FROM utilisateurs WHERE email = :email LIMIT 1'
            );
            //Le compte est créé avec un mot de passe haché et un changement obligatoire au premier accès.
            $ajouterUtilisateur = $connexion->prepare(
                'INSERT INTO utilisateurs
                    (nom_utilisateurs, prenom, email, mdp_hash, role, actif, doit_changer_mot_de_passe)
                 VALUES
                    (:nom, :prenom, :email, :mdp_hash, :role, 1, 1)'
            );
            $ajouterDossier = $connexion->prepare(
                'INSERT INTO dossiers
                    (id_utilisateurs, prenom_dossiers, nom_dossiers, email_dossiers,
                     categorie_dossiers, date_naissance_dossiers)
                 VALUES
                    (:id_utilisateur, :prenom, :nom, :email_responsable,
                     :categorie, :date_naissance)'
            );
            //Une saison active permet d'initialiser la cotisation des nouveaux comptes.
            $saisonId = $connexion->query(
                'SELECT id_saisons
                 FROM saisons
                 WHERE CURDATE() BETWEEN date_debut AND date_fin
                 ORDER BY date_debut DESC
                 LIMIT 1'
            )->fetchColumn();
            $ajouterCotisation = $saisonId === false
                ? null
                : $connexion->prepare(
                    "INSERT INTO cotisations (utilisateur_id, saison_id, statut_cotisations)
                     VALUES (:utilisateur, :saison, 'a_payer')"
                );

            foreach ($licencies as $licencie) {
                //Une adresse de connexion doit rester unique dans la table des utilisateurs.
                $verifierCourriel->execute(['email' => $licencie['email_licencie']]);
                if ($verifierCourriel->fetchColumn() !== false) {
                    throw new DomainException(
                        'Un compte utilise déjà l’adresse ' . $licencie['email_licencie'] . '. Aucun licencié n’a été ajouté.'
                    );
                }

                //Le mot de passe en clair reste seulement en mémoire pour être transmis par courriel.
                $motDePasseTemporaire = bin2hex(random_bytes(12));
                $ajouterUtilisateur->execute([
                    'nom' => $licencie['nom'],
                    'prenom' => $licencie['prenom'],
                    'email' => $licencie['email_licencie'],
                    'mdp_hash' => password_hash($motDePasseTemporaire, PASSWORD_DEFAULT),
                    'role' => 'licencie',
                ]);
                //L'identifiant créé permet de rattacher le dossier au compte du licencié.
                $identifiantUtilisateur = (int) $connexion->lastInsertId();

                $ajouterDossier->execute([
                    'id_utilisateur' => $identifiantUtilisateur,
                    'prenom' => $licencie['prenom'],
                    'nom' => $licencie['nom'],
                    'email_responsable' => $licencie['email_responsable'],
                    'categorie' => $licencie['categorie'],
                    'date_naissance' => $licencie['date_naissance'],
                ]);

                //Sans saison active, aucune cotisation n'est créée.
                if ($ajouterCotisation !== null) {
                    $ajouterCotisation->execute([
                        'utilisateur' => $identifiantUtilisateur,
                        'saison' => $saisonId,
                    ]);
                }

                //Le contrôleur utilisera ces informations pour envoyer les accès provisoires.
                $comptesCrees[] = [
                    'prenom' => $licencie['prenom'],
                    'nom' => $licencie['nom'],
                    'email' => $licencie['email_licencie'],
                    'mot_de_passe_temporaire' => $motDePasseTemporaire,
                ];
            }

            //Les changements deviennent définitifs uniquement quand tous les licenciés sont créés.
            $connexion->commit();
            return $comptesCrees;
        } catch (Throwable $erreur) {
            //En cas d'erreur, aucune création partielle du lot n'est conservée.
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }
            throw $erreur;
        }
    }

    private static function lireCsv(string $chemin): array
    {
        //Le contenu complet est lu pour détecter son encodage avant de traiter les lignes.
        $contenu = file_get_contents($chemin);
        if ($contenu === false) {
            throw new RuntimeException('Le fichier CSV ne peut pas être lu.');
        }
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            //Excel peut ajouter une marque BOM au début des fichiers CSV UTF-8.
            $contenu = substr($contenu, 3);
        }
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            //Certains tableurs français enregistrent encore le CSV en Windows-1252.
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        //Le flux temporaire permet d'utiliser fgetcsv sans modifier le fichier envoyé.
        $flux = fopen('php://temp', 'r+');
        if ($flux === false) {
            throw new RuntimeException('Le fichier CSV ne peut pas être préparé pour la lecture.');
        }

        try {
            if (fwrite($flux, $contenu) === false) {
                throw new RuntimeException('Le fichier CSV ne peut pas être préparé pour la lecture.');
            }
            rewind($flux);
            $premiereLigne = fgets($flux);
            if ($premiereLigne === false) {
                return [];
            }

            //Le séparateur est choisi d'après la première ligne, car Excel français utilise souvent « ; ».
            $separateur = substr_count($premiereLigne, ';') >= substr_count($premiereLigne, ',')
                ? ';'
                : ',';
            rewind($flux);
            $lignes = [];

            //fgetcsv prend en charge les champs entre guillemets et les séparateurs inclus dans un champ.
            while (($ligne = fgetcsv($flux, null, $separateur, '"', '\\')) !== false) {
                $lignes[] = $ligne;
            }

            return $lignes;
        } finally {
            //Le flux temporaire est fermé même si la lecture déclenche une erreur.
            fclose($flux);
        }
    }

    private static function validerLignes(array $lignes): array
    {
        //Il faut au minimum la ligne des colonnes et une ligne de données.
        if (count($lignes) < 2) {
            throw new InvalidArgumentException(
                'Le fichier doit contenir une ligne d’en-têtes et au moins un licencié.'
            );
        }

        //La première ligne contient les noms de colonnes, quel que soit leur ordre.
        $entetes = array_map(
            static fn ($valeur): string => self::normaliserEntete((string) $valeur),
            array_shift($lignes)
        );
        //Deux noms identiques rendraient le repérage des colonnes ambigu.
        if (count(array_unique($entetes)) !== count($entetes)) {
            throw new InvalidArgumentException('Le fichier contient des noms de colonnes en double.');
        }

        $colonnesManquantes = array_diff(self::COLONNES_REQUISES, $entetes);
        if ($colonnesManquantes !== []) {
            throw new InvalidArgumentException(
                'Colonnes manquantes : ' . implode(', ', $colonnesManquantes) . '.'
            );
        }

        //On mémorise la position de chaque colonne pour lire les données même si l'ordre change.
        $positions = array_flip($entetes);
        $licencies = [];
        $courriels = [];

        foreach ($lignes as $index => $ligneFichier) {
            //Les lignes vides ajoutées en fin de fichier sont ignorées.
            if (array_filter($ligneFichier, static fn ($valeur): bool => trim((string) $valeur) !== '') === []) {
                continue;
            }

            $numeroLigne = $index + 2;
            $licencie = [];
            //Les valeurs sont nettoyées avant les vérifications et l'insertion en base.
            foreach (self::COLONNES_REQUISES as $colonne) {
                $licencie[$colonne] = trim((string) ($ligneFichier[$positions[$colonne]] ?? ''));
            }

            foreach (self::COLONNES_REQUISES as $colonne) {
                if ($licencie[$colonne] === '') {
                    throw new InvalidArgumentException(
                        "La colonne « {$colonne} » est vide à la ligne {$numeroLigne}."
                    );
                }
            }

            //Les e-mails sont convertis en minuscules pour éviter les doublons dus à la casse.
            $licencie['email_licencie'] = mb_strtolower($licencie['email_licencie']);
            $licencie['email_responsable'] = mb_strtolower($licencie['email_responsable']);
            foreach ([
                'nom' => 60,
                'prenom' => 60,
                'categorie' => 20,
                'email_licencie' => 190,
                'email_responsable' => 150,
            ] as $colonne => $longueurMaximale) {
                //Ces limites correspondent aux tailles de colonnes de la base de données.
                if (mb_strlen($licencie[$colonne]) > $longueurMaximale) {
                    throw new InvalidArgumentException(
                        "La valeur de « {$colonne} » dépasse {$longueurMaximale} caractères à la ligne {$numeroLigne}."
                    );
                }
            }

            foreach (['email_licencie', 'email_responsable'] as $colonneEmail) {
                if (!filter_var($licencie[$colonneEmail], FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException(
                        "L’adresse e-mail « {$colonneEmail} » est invalide à la ligne {$numeroLigne}."
                    );
                }
            }

            //On refuse les doublons dans le fichier avant même de consulter la base.
            if (isset($courriels[$licencie['email_licencie']])) {
                throw new InvalidArgumentException(
                    "L’adresse de connexion est présente plusieurs fois dans le fichier (ligne {$numeroLigne})."
                );
            }
            $courriels[$licencie['email_licencie']] = true;

            //La date est convertie au format SQL AAAA-MM-JJ avant l'insertion.
            $licencie['date_naissance'] = self::normaliserDate(
                $ligneFichier[$positions['date_naissance']] ?? null
            );
            $licencies[] = $licencie;
        }

        if ($licencies === []) {
            throw new InvalidArgumentException('Aucun licencié à importer.');
        }

        return $licencies;
    }

    private static function normaliserEntete(string $entete): string
    {
        //On retire le BOM, les espaces et les accents pour reconnaître les en-têtes équivalents.
        $entete = preg_replace('/^\xEF\xBB\xBF/', '', trim($entete)) ?? $entete;
        $entete = mb_strtolower($entete);

        return strtr($entete, [
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'à' => 'a',
            'ç' => 'c',
            ' ' => '_',
        ]);
    }

    private static function normaliserDate(mixed $valeur): string
    {
        //Dans un classeur, une date peut être enregistrée comme un numéro de série Excel.
        if (is_numeric($valeur)) {
            if ((float) $valeur <= 0 || (float) $valeur > 100000) {
                throw new InvalidArgumentException('Une date de naissance Excel est invalide.');
            }
            $date = Date::excelToDateTimeObject((float) $valeur);
        } else {
            //Les formats texte courants sont testés les uns après les autres.
            $texte = trim((string) $valeur);
            $date = false;
            foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
                $date = DateTimeImmutable::createFromFormat($format, $texte);
                $erreurs = DateTimeImmutable::getLastErrors();
                if (
                    $date !== false
                    && ($erreurs === false || ($erreurs['warning_count'] === 0 && $erreurs['error_count'] === 0))
                ) {
                    break;
                }
                $date = false;
            }

            if ($date === false) {
                throw new InvalidArgumentException(
                    'Une date de naissance est invalide. Utilisez AAAA-MM-JJ, JJ/MM/AAAA ou JJ-MM-AAAA.'
                );
            }
        }

        //La date de naissance doit être valide et ne peut pas se situer dans le futur.
        $date = $date->setTime(0, 0);
        if ($date > new DateTimeImmutable('today')) {
            throw new InvalidArgumentException('Une date de naissance ne peut pas être dans le futur.');
        }

        return $date->format('Y-m-d');
    }
}
