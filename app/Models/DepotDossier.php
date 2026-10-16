<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use App\Core\DatesFrancaises;
use DateTimeImmutable;
use RuntimeException;

final class DepotDossier
{
    public static function lister(): array
    {
        // Les alias gardent des clés courtes côté PHP malgré les noms de colonnes de la table.
        $requete = (new BaseDeDonnees())->obtenirConnexion()->query(
            'SELECT id_dossiers AS id, prenom_dossiers AS prenom, nom_dossiers AS nom,
                    email_dossiers AS email, categorie_dossiers AS categorie, cree_le,
                    pieces_recues, pieces_total, statut_dossiers AS statut
             FROM dossiers
             ORDER BY cree_le DESC, id_dossiers DESC'
        );

        return array_map(static function (array $dossier): array {
            $piecesRecues = (int) $dossier['pieces_recues'];
            $piecesTotal = (int) $dossier['pieces_total'];
            $statut = (string) $dossier['statut'];
            $piecesManquantes = $piecesRecues < $piecesTotal;

            // Le statut validé prime ; sinon on distingue le dossier complet de celui qui manque des pièces.
            if ($statut === 'valide') {
                $etat = ['tone' => 'ok', 'label' => 'Validé'];
            } elseif ($statut === 'en_attente' && $piecesManquantes) {
                $etat = ['tone' => 'danger', 'label' => 'Pièce manquante'];
            } elseif ($statut === 'en_attente') {
                $etat = ['tone' => 'warn', 'label' => 'À valider'];
            } else {
                throw new \UnexpectedValueException('Statut du dossier inconnu : ' . $statut);
            }

            return [
                'id' => (int) $dossier['id'],
                'name' => trim($dossier['prenom'] . ' ' . $dossier['nom']),
                'email' => $dossier['email'],
                'category' => $dossier['categorie'],
                'date' => DatesFrancaises::dateCourte(new DateTimeImmutable($dossier['cree_le'])),
                'pieces' => $piecesRecues . ' sur ' . $piecesTotal,
                'status' => $etat,
                // Seuls les dossiers en attente et incomplets peuvent être relancés.
                'canRelance' => $statut === 'en_attente' && $piecesManquantes,
            ];
        }, $requete->fetchAll());
    }

    // Enregistre une relance pour un dossier incomplet sans envoyer de courriel.
    public static function enregistrerRelance(int $identifiant, array $membre): bool
    {
        // Vérifie en base que le dossier existe encore et nécessite toujours une relance.
        $requete = (new BaseDeDonnees())->obtenirConnexion()->prepare(
            'SELECT 1 FROM dossiers
             WHERE id_dossiers = :id AND statut_dossiers = :statut
               AND pieces_recues < pieces_total
             LIMIT 1'
        );
        $requete->execute(['id' => $identifiant, 'statut' => 'en_attente']);
        if ($requete->fetchColumn() === false) {
            return false;
        }

        // On journalise la demande ; aucun courriel n'est envoyé sans configuration de messagerie.
        $entree = json_encode([
            'at' => date('c'),
            'member' => $membre['id'],
            'role' => $membre['role'],
            'action' => 'relance_dossier',
            'dossier_id' => $identifiant,
            'label' => 'Relance enregistrée (courriel non envoyé)',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $fichierJournal = BASE_PATH . '/storage/actions.jsonl';
        if (file_put_contents($fichierJournal, $entree . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’enregistrer la relance du dossier.');
        }

        return true;
    }
}
