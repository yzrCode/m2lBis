<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\ProtectionCsrf;
use App\Core\RegistreRoles;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotCertificat;
use App\Models\DepotCotisation;
use DateTimeImmutable;
use PDOException;

//Gère le suivi administratif des certificats sans stocker les documents.
final class ControleurCertificat
{
    //Affiche les statuts des certificats pour une saison choisie.
    public static function lister(): void
    {
        $membre = Authentification::exigerConnexion();
        if (RegistreRoles::elementMenu($membre['role'], '/certificats') === null) {
            self::afficherAccesRefuse($membre);
            return;
        }

        $saisons = DepotCotisation::saisons();
        $saison = self::saisonSelectionnee($saisons);
        $filtres = self::filtres();
        $certificats = $saison === null ? [] : DepotCertificat::lister((int) $saison['id'], $filtres);

        Vue::afficher('certificats/index', PreparationEspaceMembre::preparer(
            $membre,
            '/certificats',
            'Certificats'
        ) + [
            'certificats' => $certificats,
            'saisons' => $saisons,
            'saison' => $saison,
            'filtres' => $filtres,
        ], 'app');
    }

    //Enregistre le statut du certificat et sa date de réception éventuelle.
    public static function modifier(string $identifiant): void
    {
        $membre = Authentification::exigerConnexion();
        if (RegistreRoles::elementMenu($membre['role'], '/certificats') === null) {
            self::afficherAccesRefuse($membre);
            return;
        }
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/certificats');
        }

        $licencieId = filter_var($identifiant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $saisonId = filter_var($_POST['saison_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $statut = self::valeurPostee('statut');
        $dateReception = trim(self::valeurPostee('date_reception'));
        $destination = $saisonId === false ? '/certificats' : '/certificats?saison=' . $saisonId;

        if (
            $licencieId === false
            || $saisonId === false
            || !in_array($statut, ['manquant', 'recu', 'a_renouveler'], true)
        ) {
            SessionUtilisateur::messageFlash('error', 'Le statut du certificat n’est pas valide.');
            rediriger($destination);
        }

        if ($statut === 'recu' && $dateReception === '') {
            $dateReception = date('Y-m-d');
        } elseif (
            $dateReception !== ''
            && (
                !self::dateValide($dateReception)
                || $dateReception > (new DateTimeImmutable('today'))->format('Y-m-d')
            )
        ) {
            SessionUtilisateur::messageFlash('error', 'La date de réception n’est pas valide.');
            rediriger($destination);
        }
        if ($statut === 'manquant') {
            $dateReception = '';
        }

        try {
            $estModifie = DepotCertificat::modifier(
                (int) $licencieId,
                (int) $saisonId,
                $statut,
                $dateReception === '' ? null : $dateReception
            );
        } catch (PDOException $erreur) {
            error_log('Modification d’un certificat : ' . $erreur->getMessage());
            SessionUtilisateur::messageFlash('error', 'Le statut du certificat n’a pas pu être enregistré.');
            rediriger($destination);
        }

        SessionUtilisateur::messageFlash(
            $estModifie ? 'success' : 'error',
            $estModifie ? 'Le suivi du certificat a été mis à jour.' : 'Le licencié ou la saison n’existe plus.'
        );
        rediriger($destination);
    }

    //Vérifie que la personne connectée a accès à la section des certificats.
    private static function afficherAccesRefuse(array $membre): void
    {
        Vue::afficher('errors/error', [
            'status' => 403,
            'title' => 'Accès refusé',
            'text' => "Cette fonction n'est pas disponible pour votre rôle.",
            'member' => $membre,
        ], 'plain', 403);
    }

    //Choisit la saison demandée ou, à défaut, la saison en cours.
    private static function saisonSelectionnee(array $saisons): ?array
    {
        $idSaisi = $_GET['saison'] ?? null;
        if ($idSaisi !== null) {
            $id = filter_var($idSaisi, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                foreach ($saisons as $saison) {
                    if ((int) $saison['id'] === $id) {
                        return $saison;
                    }
                }
            }
            SessionUtilisateur::messageFlash('error', 'La saison demandée est introuvable.');
        }
        foreach ($saisons as $saison) {
            if ((bool) $saison['en_cours']) {
                return $saison;
            }
        }

        return $saisons[0] ?? null;
    }

    //Récupère les filtres acceptés pour la liste.
    private static function filtres(): array
    {
        $recherche = $_GET['q'] ?? '';
        $statut = $_GET['statut'] ?? '';
        return [
            'recherche' => is_string($recherche) ? mb_substr(trim($recherche), 0, 100) : '',
            'statut' => is_string($statut) && in_array($statut, ['manquant', 'recu', 'a_renouveler'], true) ? $statut : '',
        ];
    }

    //Récupère un champ envoyé sous forme de texte.
    private static function valeurPostee(string $champ): string
    {
        $valeur = $_POST[$champ] ?? '';
        return is_string($valeur) ? $valeur : '';
    }

    //Contrôle qu'une date respecte exactement le format AAAA-MM-JJ.
    private static function dateValide(string $valeur): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        $erreurs = DateTimeImmutable::getLastErrors();

        return $date !== false
            && $date->format('Y-m-d') === $valeur
            && ($erreurs === false || ($erreurs['warning_count'] === 0 && $erreurs['error_count'] === 0));
    }
}
