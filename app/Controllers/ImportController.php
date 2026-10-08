<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;

/** Import CSV de licenciés (secrétariat et présidence). */
final class ImportController
{
    private const ALLOWED_ROLES = ['secretaire', 'president'];
    private const REQUIRED = ['nom', 'prenom', 'date_naissance', 'categorie', 'email_responsable'];
    private const MAX_BYTES = 2_097_152;

    public static function store(): void
    {
        $user = Auth::require();
        if (!in_array($user['role'], self::ALLOWED_ROLES, true)) {
            http_response_code(403);
            exit('Accès refusé');
        }
        if (!Csrf::checkRequest()) {
            self::fail('Session expirée, veuillez réessayer.');
        }
        $file = $_FILES['fichier_csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            self::fail("Le fichier n'a pas pu être envoyé.");
        }
        if ($file['size'] > self::MAX_BYTES || !preg_match('/\.csv$/i', (string) $file['name'])) {
            self::fail('Choisissez un fichier .csv de moins de 2 Mo.');
        }

        $handle = fopen($file['tmp_name'], 'rb');
        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        rewind($handle);
        $header = array_map([self::class, 'normalize'], (array) fgetcsv($handle, 0, $delimiter));
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            fclose($handle);
            self::fail('Colonnes manquantes : ' . implode(', ', $missing) . '.');
        }
        $rows = 0;
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (array_filter($line, static fn ($v) => trim((string) $v) !== '')) {
                $rows++;
            }
        }
        fclose($handle);

        $dir = BASE_PATH . '/storage/imports';
        is_dir($dir) || mkdir($dir, 0775, true);
        move_uploaded_file($file['tmp_name'], $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.csv');
        // TODO métier : insérez ici les licenciés en base (transaction, dédoublonnage par e-mail + date de naissance).

        Session::flash('success', 'Fichier importé : ' . number_format($rows, 0, ',', "\u{202F}") . ' licenciés détectés.');
        redirect('/tableau-de-bord');
    }

    private static function normalize(?string $col): string
    {
        $col = mb_strtolower(trim((string) $col));
        $col = preg_replace('/^\xEF\xBB\xBF/', '', $col) ?? $col;
        return strtr($col, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', ' ' => '_']);
    }

    private static function fail(string $message): never
    {
        Session::flash('error', $message);
        redirect('/tableau-de-bord#panel-import');
    }
}
