<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Authentification;
use App\Core\PreparationEspaceMembre;
use App\Core\ProtectionCsrf;
use App\Core\RegistreRoles;
use App\Core\SessionUtilisateur;
use App\Core\Vue;
use App\Models\DepotCotisation;
use DateTimeImmutable;
use PDOException;
use RuntimeException;

//Gère le suivi manuel des cotisations pour le secrétariat et la trésorerie.
final class ControleurCotisation
{
    //Affiche les cotisations d'une saison avec une recherche et un filtre de statut.
    public static function lister(): void
    {
        $membre = Authentification::exigerConnexion();
        $saisons = DepotCotisation::saisons();
        $saison = self::saisonSelectionnee($saisons);
        if ($membre['role'] === 'licencie') {
            Vue::afficher('cotisations/mon-espace', PreparationEspaceMembre::preparer(
                $membre,
                '/cotisations',
                'Ma cotisation'
            ) + [
                'saison' => $saison,
                'cotisation' => $saison === null
                    ? null
                    : DepotCotisation::pourLicencie((int) $membre['id'], (int) $saison['id']),
            ], 'app');
            return;
        }
        if (!self::aAcces($membre)) {
            return;
        }

        $filtres = self::filtres();
        $cotisations = $saison === null ? [] : DepotCotisation::lister((int) $saison['id'], $filtres);

        Vue::afficher('cotisations/index', PreparationEspaceMembre::preparer(
            $membre,
            '/cotisations',
            'Cotisations'
        ) + [
            'cotisations' => $cotisations,
            'saisons' => $saisons,
            'saison' => $saison,
            'filtres' => $filtres,
        ], 'app');
    }

    //Crée une saison et prépare automatiquement les cotisations à payer des licenciés actifs.
    public static function creerSaison(): void
    {
        $membre = Authentification::exigerConnexion();
        if (!self::aAcces($membre)) {
            return;
        }
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/cotisations');
        }

        $libelle = trim(self::valeurPostee('libelle'));
        $dateDebut = trim(self::valeurPostee('date_debut'));
        $dateFin = trim(self::valeurPostee('date_fin'));
        if ($libelle === '' || mb_strlen($libelle) > 20) {
            self::erreurSaison('Le nom de la saison est obligatoire (20 caractères maximum).');
        }
        if (!self::dateValide($dateDebut) || !self::dateValide($dateFin) || $dateFin < $dateDebut) {
            self::erreurSaison('Choisissez une période valide : la date de fin doit suivre la date de début.');
        }

        try {
            DepotCotisation::creerSaison($libelle, $dateDebut, $dateFin);
        } catch (RuntimeException $erreur) {
            self::erreurSaison($erreur->getMessage());
        } catch (PDOException $erreur) {
            error_log('Création d’une saison : ' . $erreur->getMessage());
            self::erreurSaison('La saison n’a pas pu être créée. Vérifiez son nom et ses dates.');
        }

        SessionUtilisateur::messageFlash('success', 'La saison a été créée et les cotisations des licenciés actifs ont été préparées.');
        rediriger('/cotisations');
    }

    //Met à jour le montant et l'état d'une cotisation.
    public static function modifier(string $identifiant): void
    {
        $membre = Authentification::exigerConnexion();
        if (!self::aAcces($membre)) {
            return;
        }
        if (!ProtectionCsrf::verifierRequete()) {
            SessionUtilisateur::messageFlash('error', 'Session expirée, veuillez réessayer.');
            rediriger('/cotisations');
        }

        $licencieId = filter_var($identifiant, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $saisonId = filter_var($_POST['saison_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $statut = self::valeurPostee('statut');
        $montantTexte = self::valeurPostee('montant');
        $destination = $saisonId === false ? '/cotisations' : '/cotisations?saison=' . $saisonId;

        if (
            $licencieId === false
            || $saisonId === false
            || !in_array($statut, ['a_payer', 'payee', 'en_retard'], true)
            || !preg_match('/^\d{1,6}(?:[.,]\d{1,2})?$/', $montantTexte)
        ) {
            SessionUtilisateur::messageFlash('error', 'Le montant ou le statut de cotisation n’est pas valide.');
            rediriger($destination);
        }

        $montant = (float) str_replace(',', '.', $montantTexte);
        try {
            $estModifiee = DepotCotisation::modifier((int) $licencieId, (int) $saisonId, $montant, $statut);
        } catch (PDOException $erreur) {
            error_log('Modification d’une cotisation : ' . $erreur->getMessage());
            SessionUtilisateur::messageFlash('error', 'La cotisation n’a pas pu être mise à jour.');
            rediriger($destination);
        }

        SessionUtilisateur::messageFlash(
            $estModifiee ? 'success' : 'error',
            $estModifiee ? 'La cotisation a été mise à jour.' : 'Le licencié ou la saison n’existe plus.'
        );
        rediriger($destination);
    }

    //Vérifie que le rôle possède le lien Cotisations dans son menu.
    private static function aAcces(array $membre): bool
    {
        if (
            in_array($membre['role'], ['secretaire', 'tresorier'], true)
            && RegistreRoles::elementMenu($membre['role'], '/cotisations') !== null
        ) {
            return true;
        }

        Vue::afficher('errors/error', [
            'status' => 403,
            'title' => 'Accès refusé',
            'text' => "Cette fonction n'est pas disponible pour votre rôle.",
            'member' => $membre,
        ], 'plain', 403);
        return false;
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

    //Récupère uniquement les filtres acceptés par la page.
    private static function filtres(): array
    {
        $recherche = $_GET['q'] ?? '';
        $statut = $_GET['statut'] ?? '';
        return [
            'recherche' => is_string($recherche) ? mb_substr(trim($recherche), 0, 100) : '',
            'statut' => is_string($statut) && in_array($statut, ['a_payer', 'payee', 'en_retard'], true) ? $statut : '',
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

    //Affiche le message puis revient au formulaire de création de saison.
    private static function erreurSaison(string $message): never
    {
        SessionUtilisateur::messageFlash('error', $message);
        rediriger('/cotisations');
    }
}
