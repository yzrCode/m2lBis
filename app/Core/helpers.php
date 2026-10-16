<?php
declare(strict_types=1);

use App\Core\ProtectionCsrf;

/** Échappement HTML systématique dans les vues. */
function echapperHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ajoute le numéro de version d'un fichier pour renouveler le cache du navigateur après modification. */
function urlRessource(string $chemin): string
{
    $fichier = BASE_PATH . '/public/assets/' . $chemin;
    return '/assets/' . $chemin . '?v=' . (is_file($fichier) ? filemtime($fichier) : 1);
}

function champCsrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . echapperHtml(ProtectionCsrf::jeton()) . '">';
}

function rediriger(string $destination, int $codeHttp = 302): never
{
    header('Location: ' . $destination, true, $codeHttp);
    exit;
}

/** Initiales d'un nom complet (2 lettres max). */
function initiales(string $nomComplet): string
{
    $initiales = '';
    foreach (preg_split('/\s+/u', trim($nomComplet)) ?: [] as $mot) {
        if ($mot !== '' && mb_strlen($initiales) < 2) {
            $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
        }
    }
    return $initiales;
}

/** JSON sûr pour être injecté dans une balise <script type="application/json">. */
function jsonPourHtml(array $donnees): string
{
    return json_encode(
        $donnees,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

function formaterMontant(float|int $montant): string
{
    return number_format((float) $montant, 0, ',', "\u{202F}") . "\u{00A0}€";
}
