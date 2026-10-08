<?php
declare(strict_types=1);

use App\Core\Csrf;

/** Échappement HTML systématique dans les vues. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL d'un asset avec cache-busting sur la date de modification. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    return '/assets/' . $path . '?v=' . (is_file($file) ? filemtime($file) : 1);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function redirect(string $to, int $status = 302): never
{
    header('Location: ' . $to, true, $status);
    exit;
}

/** Initiales d'un nom complet (2 lettres max). */
function initials(string $fullName): string
{
    $out = '';
    foreach (preg_split('/\s+/u', trim($fullName)) ?: [] as $word) {
        if ($word !== '' && mb_strlen($out) < 2) {
            $out .= mb_strtoupper(mb_substr($word, 0, 1));
        }
    }
    return $out;
}

/** JSON sûr pour être injecté dans une balise <script type="application/json">. */
function json_for_html(array $data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

function money(float|int $amount): string
{
    return number_format((float) $amount, 0, ',', "\u{202F}") . "\u{00A0}€";
}
