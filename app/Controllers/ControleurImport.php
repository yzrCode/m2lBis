<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\ProtectionCsrf;
use App\Core\SessionUtilisateur;

/** Import CSV de licenciés (secrétariat et présidence). */
final class ControleurImport
{
    private const ROLES_AUTORISES = ['secretaire', 'president'];
    private const COLONNES_REQUISES = ['nom', 'prenom', 'date_naissance', 'categorie', 'email_responsable'];
    private const TAILLE_MAX_OCTETS = 2_097_152;

    public static function enregistrer(): void
    {
        $membre = Authentification::exigerConnexion();
        if (!in_array($membre['role'], self::ROLES_AUTORISES, true)) {
            http_response_code(403);
            exit('Accès refusé');
        }
        if (!ProtectionCsrf::verifierRequete()) {
            self::echouer('Session expirée, veuillez réessayer.');
        }
        $fichier = $_FILES['fichier_csv'] ?? null;
        if (!$fichier || $fichier['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($fichier['tmp_name'])) {
            self::echouer("Le fichier n'a pas pu être envoyé.");
        }
        if ($fichier['size'] > self::TAILLE_MAX_OCTETS || !preg_match('/\.csv$/i', (string) $fichier['name'])) {
            self::echouer('Choisissez un fichier .csv de moins de 2 Mo.');
        }

        $flux = fopen($fichier['tmp_name'], 'rb');
        $premiereLigne = (string) fgets($flux);
        $separateur = substr_count($premiereLigne, ';') >= substr_count($premiereLigne, ',') ? ';' : ',';
        rewind($flux);
        $entetes = array_map([self::class, 'normaliser'], (array) fgetcsv($flux, 0, $separateur));
        $colonnesManquantes = array_diff(self::COLONNES_REQUISES, $entetes);
        if ($colonnesManquantes) {
            fclose($flux);
            self::echouer('Colonnes manquantes : ' . implode(', ', $colonnesManquantes) . '.');
        }
        $lignes = 0;
        while (($ligne = fgetcsv($flux, 0, $separateur)) !== false) {
            if (array_filter($ligne, static fn ($valeur) => trim((string) $valeur) !== '')) {
                $lignes++;
            }
        }
        fclose($flux);

        $dossier = BASE_PATH . '/storage/imports';
        is_dir($dossier) || mkdir($dossier, 0775, true);
        move_uploaded_file($fichier['tmp_name'], $dossier . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.csv');
        // À relier au métier : insérer les licenciés en transaction et éviter les doublons.

        SessionUtilisateur::messageFlash('success', 'Fichier importé : ' . number_format($lignes, 0, ',', "\u{202F}") . ' licenciés détectés.');
        rediriger('/tableau-de-bord');
    }

    private static function normaliser(?string $col): string
    {
        $col = mb_strtolower(trim((string) $col));
        $col = preg_replace('/^\xEF\xBB\xBF/', '', $col) ?? $col;
        return strtr($col, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', ' ' => '_']);
    }

    private static function echouer(string $message): never
    {
        SessionUtilisateur::messageFlash('error', $message);
        rediriger('/tableau-de-bord#panel-import');
    }
}
