<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\BaseDeDonnees;
use App\Core\DatesFrancaises;
use App\Core\RegistreRoles;
use DateTimeImmutable;
use PDO;

//Prépare les indicateurs du tableau de bord à partir des données réellement enregistrées.
final class DepotTableauBord
{
    public static function pourRole(string $role, ?int $utilisateurId = null): array
    {
        if (!RegistreRoles::existe($role)) {
            throw new \InvalidArgumentException('Rôle inconnu');
        }

        $agenda = $utilisateurId === null
            ? []
            : DepotCalendrier::prochains($role, $utilisateurId);
        $kpis = self::indicateurs($role, $utilisateurId, $agenda);
        $notifications = self::notifications($role, $utilisateurId);
        if ($utilisateurId !== null) {
            $notifications = DepotNotification::appliquerEtatLecture($notifications, $utilisateurId);
        }

        return [
            'lead' => self::messageAccueil($role),
            'counts' => self::compteurs($role, $utilisateurId),
            'hero' => [
                'type' => 'focus',
                'title' => $kpis[0]['label'],
                'value' => $kpis[0]['value'],
                'caption' => $kpis[0]['hint'],
                'actions' => [],
            ],
            'agenda' => self::presenterAgenda($agenda),
            'kpis' => $kpis,
            'panels' => self::panneaux($role, $utilisateurId, $agenda),
            'notifications' => $notifications,
            'meta' => ['now' => date('c')],
        ];
    }

    //Les indicateurs sont volontairement limités aux informations présentes dans les tables.
    private static function indicateurs(string $role, ?int $utilisateurId, array $agenda): array
    {
        $licencies = DepotLicencie::compterActifs();
        $saison = in_array($role, ['tresorier', 'licencie'], true)
            ? DepotCotisation::saisonCourante()
            : null;
        $cotisations = in_array($role, ['secretaire', 'tresorier'], true)
            ? DepotCotisation::indicateurs()
            : [
                'total' => 0,
                'impayees' => 0,
                'montant_total' => 0.0,
                'montant_encaisse' => 0.0,
                'montant_impaye' => 0.0,
            ];
        $dossiersACompleter = 0;
        $certificatsAtraiter = 0;
        if ($role === 'secretaire') {
            $dossiersACompleter = count(array_filter(
                DepotDossier::lister(),
                static fn (array $dossier): bool => $dossier['canRelance']
            ));
            $certificatsAtraiter = DepotCertificat::nombreAtraiter();
        }
        $equipes = self::compter('SELECT COUNT(*) FROM equipes');
        $matchsAVenir = self::compter('SELECT COUNT(*) FROM matchs WHERE date_heure >= NOW()');
        $evenementsAVenir = self::compter('SELECT COUNT(*) FROM evenements WHERE date_debut >= NOW()');
        $demandesEnAttente = self::compter(
            "SELECT COUNT(*) FROM demandes_comptes WHERE statut = 'en_attente'"
        );
        $commandes = self::compter('SELECT COUNT(*) FROM achats_fournisseurs');

        switch ($role) {
            case 'secretaire':
                return [
                    self::kpi('Licenciés actifs', $licencies, 'Comptes actifs enregistrés', 'users'),
                    self::kpi('Dossiers incomplets', $dossiersACompleter, 'Pièces manquantes à traiter', 'folder', 'warn'),
                    self::kpi('Certificats à traiter', $certificatsAtraiter, 'Statuts manquants ou à renouveler', 'shield-check', 'warn'),
                    self::kpi('Cotisations impayées', $cotisations['impayees'], 'Comptes de la saison active', 'euro', 'danger'),
                ];

            case 'tresorier':
                $tauxEncaissement = $cotisations['montant_total'] === 0.0
                    ? 0
                    : (int) round($cotisations['montant_encaisse'] / $cotisations['montant_total'] * 100);
                return [
                    self::kpi('Cotisations encaissées', $tauxEncaissement . ' %', self::montant($cotisations['montant_encaisse']) . ' encaissés', 'euro', 'ok'),
                    self::kpi('Cotisations impayées', $cotisations['impayees'], self::montant($cotisations['montant_impaye']) . ' à encaisser', 'euro', 'danger'),
                    self::kpi('Licenciés concernés', $cotisations['total'], $saison === null ? 'Aucune saison active' : 'Saison ' . $saison['libelle'], 'users'),
                    self::kpi('Commandes enregistrées', $commandes, 'Achats présents dans la base', 'cart'),
                ];

            case 'president':
                return [
                    self::kpi('Licenciés actifs', $licencies, 'Comptes actifs enregistrés', 'users'),
                    self::kpi('Équipes', $equipes, 'Équipes enregistrées', 'trophy'),
                    self::kpi('Demandes de compte', $demandesEnAttente, 'Demandes en attente de décision', 'clipboard', 'warn'),
                    self::kpi('Échéances à venir', $matchsAVenir + $evenementsAVenir, 'Matchs et événements enregistrés', 'calendar'),
                ];

            case 'educateur':
                $equipesEducateur = $utilisateurId === null ? 0 : self::compter(
                    'SELECT COUNT(*) FROM equipes WHERE educateur_id = :utilisateur',
                    ['utilisateur' => $utilisateurId]
                );
                $joueurs = $utilisateurId === null ? 0 : self::compter(
                    'SELECT COUNT(DISTINCT ej.joueur_id)
                     FROM equipes_joueurs ej
                     INNER JOIN equipes e ON e.id_equipes = ej.equipe_id
                     WHERE e.educateur_id = :utilisateur',
                    ['utilisateur' => $utilisateurId]
                );
                return [
                    self::kpi('Mes équipes', $equipesEducateur, 'Équipes qui vous sont attribuées', 'users'),
                    self::kpi('Joueurs dans mes équipes', $joueurs, 'Licenciés rattachés à vos équipes', 'users'),
                    self::kpi('Échéances à venir', count($agenda), 'Matchs et événements de votre calendrier', 'calendar'),
                    self::kpi('Matchs à venir au club', $matchsAVenir, 'Matchs enregistrés dans la base', 'trophy'),
                ];

            case 'licencie':
                return self::indicateursLicencie($utilisateurId, $agenda, $saison);

            case 'resp_evenementiel':
                $evenements = self::compter('SELECT COUNT(*) FROM evenements');
                return [
                    self::kpi('Événements à venir', $evenementsAVenir, 'Événements enregistrés à venir', 'calendar'),
                    self::kpi('Événements enregistrés', $evenements, 'Total dans la base', 'star'),
                    self::kpi('Prochaines échéances', count($agenda), 'Événements et matchs du club', 'calendar'),
                    self::kpi('Licenciés actifs', $licencies, 'Comptes actifs du club', 'users'),
                ];

            case 'resp_achats':
                $montantCommandes = (float) self::valeur('SELECT COALESCE(SUM(montant_ttc), 0) FROM achats_fournisseurs');
                $fournisseurs = self::compter('SELECT COUNT(DISTINCT fournisseur) FROM achats_fournisseurs');
                return [
                    self::kpi('Commandes enregistrées', $commandes, 'Lignes présentes dans les achats', 'cart'),
                    self::kpi('Montant des commandes', self::montant($montantCommandes), 'Total TTC enregistré', 'euro'),
                    self::kpi('Fournisseurs utilisés', $fournisseurs, 'Fournisseurs présents dans les achats', 'users'),
                    self::kpi('Échéances à venir', count($agenda), 'Matchs et événements du club', 'calendar'),
                ];

            case 'resp_tournois':
                return [
                    self::kpi('Matchs à venir', $matchsAVenir, 'Matchs réellement planifiés', 'trophy'),
                    self::kpi('Événements à venir', $evenementsAVenir, 'Événements réellement planifiés', 'calendar'),
                    self::kpi('Équipes du club', $equipes, 'Équipes enregistrées', 'users'),
                    self::kpi('Échéances à venir', count($agenda), 'Calendrier du club', 'calendar'),
                ];

            case 'resp_ecole_foot':
                $effectifEcole = self::compter(
                    "SELECT COUNT(DISTINCT u.id_utilisateurs)
                     FROM utilisateurs u
                     INNER JOIN dossiers d ON d.id_dossiers = (
                         SELECT d2.id_dossiers FROM dossiers d2
                         WHERE d2.id_utilisateurs = u.id_utilisateurs
                         ORDER BY d2.id_dossiers DESC LIMIT 1
                     )
                     WHERE u.role = 'licencie' AND u.actif = 1
                       AND d.categorie_dossiers IN ('U7', 'U9', 'U11')"
                );
                return [
                    self::kpi('Licenciés U7 à U11', $effectifEcole, 'Catégorie issue du dossier le plus récent', 'users'),
                    self::kpi('Équipes', $equipes, 'Équipes enregistrées dans la base', 'trophy'),
                    self::kpi('Matchs à venir', $matchsAVenir, 'Matchs réellement planifiés', 'calendar'),
                    self::kpi('Événements à venir', $evenementsAVenir, 'Événements réellement planifiés', 'star'),
                ];

            case 'arbitre':
                return [
                    self::kpi('Matchs du club à venir', $matchsAVenir, 'La base ne contient pas encore de désignations personnelles', 'flag'),
                    self::kpi('Événements à venir', $evenementsAVenir, 'Événements réellement planifiés', 'calendar'),
                    self::kpi('Équipes', $equipes, 'Équipes enregistrées', 'users'),
                    self::kpi('Licenciés actifs', $licencies, 'Comptes actifs du club', 'users'),
                ];

            case 'service_civique':
                return [
                    self::kpi('Échéances à venir', count($agenda), 'Matchs et événements du club', 'calendar'),
                    self::kpi('Événements à venir', $evenementsAVenir, 'Événements réellement planifiés', 'star'),
                    self::kpi('Licenciés actifs', $licencies, 'Comptes actifs du club', 'users'),
                    self::kpi('Équipes', $equipes, 'Équipes enregistrées', 'trophy'),
                ];
        }

        throw new \LogicException('Aucun indicateur défini pour ce rôle.');
    }

    private static function indicateursLicencie(?int $utilisateurId, array $agenda, ?array $saison): array
    {
        if ($utilisateurId === null) {
            return [
                self::kpi('Mes cotisations', '—', 'Connectez-vous pour consulter votre compte', 'euro'),
                self::kpi('Mon certificat', '—', 'Connectez-vous pour consulter votre dossier', 'shield-check'),
                self::kpi('Mes échéances', count($agenda), 'Éléments du calendrier', 'calendar'),
                self::kpi('Saison active', $saison['libelle'] ?? 'Aucune', 'Saison en cours dans la base', 'calendar'),
            ];
        }

        $cotisation = $saison === null ? null : DepotCotisation::pourLicencie($utilisateurId, (int) $saison['id']);
        $certificat = $saison === null ? null : self::valeur(
            'SELECT statut_certificats FROM certificats
             WHERE utilisateur_id = :utilisateur AND saison_id = :saison
             LIMIT 1',
            ['utilisateur' => $utilisateurId, 'saison' => $saison['id']]
        );
        $dossier = self::ligne(
            'SELECT pieces_recues, pieces_total FROM dossiers
             WHERE id_utilisateurs = :utilisateur ORDER BY id_dossiers DESC LIMIT 1',
            ['utilisateur' => $utilisateurId]
        );

        return [
            self::kpi(
                'Ma cotisation',
                $cotisation === null ? 'Aucune saison' : self::libelleStatut($cotisation['statut']),
                $cotisation === null ? 'Aucune saison active' : self::montant((float) $cotisation['montant']) . ' pour ' . $cotisation['saison'],
                'euro',
                $cotisation === null ? 'neutral' : self::tonStatut($cotisation['statut'])
            ),
            self::kpi(
                'Mon certificat',
                self::libelleStatut((string) ($certificat ?: 'manquant')),
                $saison === null ? 'Aucune saison active' : 'Statut enregistré pour ' . $saison['libelle'],
                'shield-check',
                self::tonStatut((string) ($certificat ?: 'manquant'))
            ),
            self::kpi(
                'Mon dossier',
                $dossier === null ? 'Non créé' : $dossier['pieces_recues'] . ' / ' . $dossier['pieces_total'],
                'Pièces enregistrées dans votre dossier',
                'folder'
            ),
            self::kpi('Mes échéances', count($agenda), 'Éléments à venir de votre calendrier', 'calendar'),
        ];
    }

    private static function panneaux(string $role, ?int $utilisateurId, array $agenda): array
    {
        if ($role === 'secretaire') {
            $dossiers = DepotDossier::lister();
            $lignes = [];
            foreach ($dossiers as $index => $dossier) {
                $ligne = [
                    'cells' => [$dossier['name'], $dossier['category'], $dossier['date'], $dossier['pieces']],
                    'status' => $dossier['status'],
                ];
                if ($dossier['canRelance']) {
                    $ligne['actions'] = [[
                        'type' => 'relance_dossier',
                        'dossierId' => $dossier['id'],
                        'label' => 'Relancer',
                        'kind' => 'ghost',
                        'toast' => 'Relance enregistrée. Aucun e-mail n’a été envoyé.',
                        'done' => 'Relance enregistrée',
                    ]];
                }
                $lignes[] = $ligne;
            }
            return [
                self::table('dossiers', 'Dossiers enregistrés', ['Licencié', 'Catégorie', 'Déposé le', 'Pièces'], $lignes, true, 'Statut'),
                [
                    'type' => 'import',
                    'id' => 'import',
                    'title' => 'Importer des licenciés',
                    'full' => true,
                    'subtitle' => 'Créez les comptes et leurs dossiers à partir d’un fichier CSV ou Excel.',
                    'formAction' => '/licencies/import',
                    'templateCsvHref' => '/modeles/licencies.csv',
                    'templateXlsxHref' => '/modeles/licencies.xlsx',
                ],
            ];
        }

        if ($role === 'president') {
            $demandes = array_map(static fn (array $demande): array => [
                'cells' => [
                    trim($demande['prenom'] . ' ' . $demande['nom']),
                    $demande['email'],
                    $demande['role'],
                    (new DateTimeImmutable($demande['cree_le']))->format('d/m/Y'),
                ],
                'status' => ['tone' => 'warn', 'label' => 'En attente'],
            ], DepotDemandesCompte::enAttente());
            return [
                self::table('demandes', 'Demandes de compte en attente', ['Nom', 'E-mail', 'Rôle demandé', 'Reçue le'], $demandes, true, 'Statut'),
                self::table('equipes', 'Équipes enregistrées', ['Équipe', 'Catégorie', 'Responsable'], self::lignesEquipes()),
            ];
        }

        if ($role === 'tresorier') {
            return [self::table('cotisations', 'Cotisations de la saison active', ['Licencié', 'Montant', 'Statut'], self::lignesCotisations())];
        }

        if ($role === 'licencie') {
            return [self::table('convocations', 'Mes convocations à venir', ['Date', 'Match', 'Équipe'], self::lignesConvocations($utilisateurId))];
        }

        if ($role === 'resp_evenementiel') {
            return [self::table('evenements', 'Événements enregistrés', ['Date', 'Événement', 'Lieu'], self::lignesEvenements())];
        }

        if ($role === 'resp_achats') {
            return [self::table('commandes', 'Commandes enregistrées', ['Date', 'Fournisseur', 'Objet', 'Montant TTC', 'Statut'], self::lignesCommandes(), true, 'Statut')];
        }

        if ($role === 'resp_ecole_foot') {
            return [self::table('categories', 'Effectif par catégorie U7 à U11', ['Catégorie', 'Licenciés'], self::lignesCategories())];
        }

        $titre = match ($role) {
            'educateur' => 'Matchs et événements de mes équipes',
            'arbitre' => 'Matchs et événements du club',
            'resp_tournois' => 'Calendrier sportif du club',
            'service_civique' => 'Activités du club enregistrées',
            default => 'Échéances du calendrier',
        };
        return [self::table('calendrier', $titre, ['Date', 'Échéance', 'Lieu', 'Type'], self::lignesAgenda($agenda))];
    }

    private static function notifications(string $role, ?int $utilisateurId): array
    {
        $notifications = [];

        if ($role === 'secretaire') {
            $incomplets = count(array_filter(
                DepotDossier::lister(),
                static fn (array $dossier): bool => $dossier['canRelance']
            ));
            $certificats = DepotCertificat::nombreAtraiter();
            $impayees = DepotCotisation::indicateurs()['impayees'];
            if ($incomplets > 0) {
                $notifications[] = self::notification('danger', $incomplets . ' dossier' . ($incomplets > 1 ? 's' : '') . ' incomplet' . ($incomplets > 1 ? 's' : ''), 'Des pièces restent à enregistrer dans la base.');
            }
            if ($certificats > 0) {
                $notifications[] = self::notification('warn', $certificats . ' certificat' . ($certificats > 1 ? 's' : '') . ' à traiter', 'Statut manquant ou à renouveler pour la saison active.');
            }
            if ($impayees > 0) {
                $notifications[] = self::notification('warn', $impayees . ' cotisation' . ($impayees > 1 ? 's' : '') . ' impayée' . ($impayees > 1 ? 's' : ''), 'Consultez les cotisations enregistrées pour la saison active.');
            }
        } elseif ($role === 'tresorier') {
            $impayees = DepotCotisation::indicateurs()['impayees'];
            if ($impayees > 0) {
                $notifications[] = self::notification('warn', $impayees . ' cotisation' . ($impayees > 1 ? 's' : '') . ' à encaisser', 'Le montant restant est calculé à partir des paiements enregistrés.');
            }
        } elseif ($role === 'president') {
            $demandes = DepotDemandesCompte::enAttente();
            if ($demandes !== []) {
                $notifications[] = self::notification('warn', count($demandes) . ' demande' . (count($demandes) > 1 ? 's' : '') . ' de compte en attente', 'Une décision est nécessaire dans la liste des demandes.');
            }
        } elseif ($role === 'licencie' && $utilisateurId !== null) {
            $saison = DepotCotisation::saisonCourante();
            if ($saison !== null) {
                $cotisation = DepotCotisation::pourLicencie($utilisateurId, (int) $saison['id']);
                if ($cotisation !== null && $cotisation['statut'] !== 'payee') {
                    $notifications[] = self::notification('warn', 'Cotisation à régler', 'Le statut est ' . self::libelleStatut($cotisation['statut']) . ' pour la saison ' . $cotisation['saison'] . '.');
                }
                $certificat = self::valeur(
                    'SELECT statut_certificats FROM certificats
                     WHERE utilisateur_id = :utilisateur AND saison_id = :saison
                     LIMIT 1',
                    ['utilisateur' => $utilisateurId, 'saison' => $saison['id']]
                );
                if ($certificat !== 'recu') {
                    $notifications[] = self::notification('warn', 'Certificat à mettre à jour', 'Le statut du certificat de la saison active est ' . self::libelleStatut((string) ($certificat ?: 'manquant')) . '.');
                }
            }
        } elseif ($role === 'resp_evenementiel') {
            $prochain = self::ligne(
                'SELECT titre_evenements, date_debut FROM evenements
                 WHERE date_debut >= NOW() ORDER BY date_debut LIMIT 1'
            );
            if ($prochain !== null) {
                $notifications[] = self::notification('neutral', 'Prochain événement : ' . $prochain['titre_evenements'], 'Prévu le ' . DatesFrancaises::dateCourte(new DateTimeImmutable($prochain['date_debut'])) . '.');
            }
        } elseif ($role === 'resp_achats') {
            $enAttente = self::compter(
                "SELECT COUNT(*) FROM achats_fournisseurs
                 WHERE LOWER(statut_fournisseurs) IN ('en_attente', 'en attente', 'a_valider', 'à valider')"
            );
            if ($enAttente > 0) {
                $notifications[] = self::notification('warn', $enAttente . ' commande' . ($enAttente > 1 ? 's' : '') . ' à valider', 'Des commandes portent un statut d’attente dans la base.');
            }
        }

        return array_slice($notifications, 0, 6);
    }

    private static function compteurs(string $role, ?int $utilisateurId): array
    {
        return match ($role) {
            'secretaire' => [
                '/licencies' => DepotLicencie::compterActifs(),
                '/dossiers' => count(array_filter(DepotDossier::lister(), static fn (array $dossier): bool => $dossier['status']['tone'] !== 'ok')),
                '/certificats' => DepotCertificat::nombreAtraiter(),
                '/cotisations' => DepotCotisation::indicateurs()['impayees'],
            ],
            'president' => [
                '/utilisateurs' => self::compter("SELECT COUNT(*) FROM demandes_comptes WHERE statut = 'en_attente'"),
                '/equipes' => self::compter('SELECT COUNT(*) FROM equipes'),
            ],
            'tresorier' => ['/cotisations' => DepotCotisation::indicateurs()['impayees']],
            'educateur' => $utilisateurId === null ? [] : [
                '/equipes' => self::compter('SELECT COUNT(*) FROM equipes WHERE educateur_id = :utilisateur', ['utilisateur' => $utilisateurId]),
            ],
            'licencie' => $utilisateurId === null ? [] : [
                '/convocations' => self::compter(
                    'SELECT COUNT(*) FROM convocations c
                     INNER JOIN matchs m ON m.id_matchs = c.match_id
                     WHERE c.joueur_id = :utilisateur AND m.date_heure >= NOW()',
                    ['utilisateur' => $utilisateurId]
                ),
            ],
            'resp_evenementiel' => ['/evenements' => self::compter('SELECT COUNT(*) FROM evenements WHERE date_debut >= NOW()')],
            'resp_achats' => ['/commandes' => self::compter('SELECT COUNT(*) FROM achats_fournisseurs')],
            default => [],
        };
    }

    private static function presenterAgenda(array $evenements): array
    {
        $items = array_map(static function (array $evenement): array {
            $date = new DateTimeImmutable($evenement['date_heure']);
            return [
                'day' => ucfirst(mb_substr(DatesFrancaises::jourSemaine($date), 0, 3)),
                'n' => $date->format('d'),
                'title' => $evenement['titre'],
                'meta' => trim($evenement['heure'] . ($evenement['lieu'] !== '' ? ', ' . $evenement['lieu'] : '')),
                'highlight' => $evenement['type'] === 'match',
            ];
        }, $evenements);
        if ($items === []) {
            $items[] = [
                'day' => '—',
                'n' => '—',
                'title' => 'Aucune échéance enregistrée',
                'meta' => 'Les événements et matchs apparaîtront ici après leur saisie.',
            ];
        }

        return ['title' => 'À venir', 'items' => $items];
    }

    private static function lignesAgenda(array $agenda): array
    {
        return array_map(static fn (array $evenement): array => [
            'cells' => [
                DatesFrancaises::dateCourte(new DateTimeImmutable($evenement['date_heure'])),
                $evenement['titre'],
                $evenement['lieu'] !== '' ? $evenement['lieu'] : '—',
                $evenement['type'] === 'match' ? 'Match' : 'Événement',
            ],
        ], $agenda);
    }

    private static function lignesEquipes(): array
    {
        return array_map(static fn (array $ligne): array => [
            'cells' => [$ligne['nom_equipes'], $ligne['categorie'], $ligne['educateur'] ?? '—'],
        ], self::lignes(
            'SELECT e.nom_equipes, c.libelle_categories AS categorie,
                    CONCAT(u.prenom, \' \', u.nom_utilisateurs) AS educateur
             FROM equipes e
             INNER JOIN categories c ON c.id_categories = e.categorie_id
             LEFT JOIN utilisateurs u ON u.id_utilisateurs = e.educateur_id
             ORDER BY e.nom_equipes LIMIT 20'
        ));
    }

    private static function lignesCotisations(): array
    {
        $saison = DepotCotisation::saisonCourante();
        if ($saison === null) {
            return [];
        }
        return array_map(static fn (array $ligne): array => [
            'cells' => [
                trim($ligne['prenom'] . ' ' . $ligne['nom']),
                self::montant((float) $ligne['montant_du']),
                self::libelleStatut($ligne['statut']),
            ],
            'status' => [
                'tone' => self::tonStatut($ligne['statut']),
                'label' => self::libelleStatut($ligne['statut']),
            ],
        ], self::lignes(
            'SELECT u.prenom, u.nom_utilisateurs AS nom,
                    COALESCE(c.montant_du, 150.00) AS montant_du,
                    COALESCE(c.statut_cotisations, \'a_payer\') AS statut
             FROM utilisateurs u
             LEFT JOIN cotisations c
                ON c.id_cotisations = (
                    SELECT c2.id_cotisations FROM cotisations c2
                    WHERE c2.utilisateur_id = u.id_utilisateurs AND c2.saison_id = :saison
                    ORDER BY c2.id_cotisations DESC LIMIT 1
                )
             WHERE u.role = \'licencie\' AND u.actif = 1
             ORDER BY u.nom_utilisateurs, u.prenom LIMIT 20',
            ['saison' => $saison['id']]
        ));
    }

    private static function lignesConvocations(?int $utilisateurId): array
    {
        if ($utilisateurId === null) {
            return [];
        }
        return array_map(static fn (array $ligne): array => [
            'cells' => [
                DatesFrancaises::dateCourte(new DateTimeImmutable($ligne['date_heure'])),
                (bool) $ligne['domicile']
                    ? 'M2L ' . $ligne['nom_equipes'] . ' – ' . $ligne['adversaire']
                    : $ligne['adversaire'] . ' – M2L ' . $ligne['nom_equipes'],
                $ligne['nom_equipes'],
            ],
            'status' => [
                'tone' => self::tonStatut($ligne['statut_reponse']),
                'label' => self::libelleStatut($ligne['statut_reponse']),
            ],
        ], self::lignes(
            'SELECT m.date_heure, m.adversaire, m.domicile, e.nom_equipes, c.statut_reponse
             FROM convocations c
             INNER JOIN matchs m ON m.id_matchs = c.match_id
             INNER JOIN equipes e ON e.id_equipes = m.equipe_id
             WHERE c.joueur_id = :utilisateur AND m.date_heure >= NOW()
             ORDER BY m.date_heure LIMIT 20',
            ['utilisateur' => $utilisateurId]
        ));
    }

    private static function lignesEvenements(): array
    {
        return array_map(static fn (array $ligne): array => [
            'cells' => [
                DatesFrancaises::dateCourte(new DateTimeImmutable($ligne['date_debut'])),
                $ligne['titre_evenements'],
                $ligne['lieu'] ?? '—',
            ],
        ], self::lignes(
            'SELECT titre_evenements, lieu, date_debut FROM evenements
             ORDER BY date_debut DESC LIMIT 20'
        ));
    }

    private static function lignesCommandes(): array
    {
        return array_map(static fn (array $ligne): array => [
            'cells' => [
                DatesFrancaises::dateCourte(new DateTimeImmutable($ligne['date_cmd'])),
                $ligne['fournisseur'],
                $ligne['libelle_fournisseurs'],
                self::montant((float) $ligne['montant_ttc']),
                self::libelleStatut($ligne['statut_fournisseurs']),
            ],
            'status' => [
                'tone' => self::tonStatut($ligne['statut_fournisseurs']),
                'label' => self::libelleStatut($ligne['statut_fournisseurs']),
            ],
        ], self::lignes(
            'SELECT fournisseur, libelle_fournisseurs, montant_ttc, date_cmd, statut_fournisseurs
             FROM achats_fournisseurs ORDER BY date_cmd DESC LIMIT 20'
        ));
    }

    private static function lignesCategories(): array
    {
        return array_map(static fn (array $ligne): array => [
            'cells' => [$ligne['categorie'], (string) $ligne['effectif']],
        ], self::lignes(
            "SELECT d.categorie_dossiers AS categorie, COUNT(DISTINCT u.id_utilisateurs) AS effectif
             FROM utilisateurs u
             INNER JOIN dossiers d ON d.id_dossiers = (
                 SELECT d2.id_dossiers FROM dossiers d2
                 WHERE d2.id_utilisateurs = u.id_utilisateurs
                 ORDER BY d2.id_dossiers DESC LIMIT 1
             )
             WHERE u.role = 'licencie' AND u.actif = 1
               AND d.categorie_dossiers IN ('U7', 'U9', 'U11')
             GROUP BY d.categorie_dossiers ORDER BY d.categorie_dossiers"
        ));
    }

    private static function messageAccueil(string $role): string
    {
        return match ($role) {
            'arbitre' => 'Les matchs et événements viennent de la base. Les désignations personnelles ne sont pas encore enregistrées.',
            'resp_tournois' => 'Les matchs et événements saisis sont affichés. Les inscriptions de tournoi ne disposent pas encore de table en base.',
            'service_civique' => 'Les événements et matchs du club sont affichés. Le suivi des missions et des heures n’est pas encore enregistré en base.',
            'resp_ecole_foot' => 'Les effectifs par catégorie sont calculés depuis les licenciés actifs et leurs dossiers.',
            default => 'Les indicateurs et les listes affichés proviennent des données enregistrées dans la base du club.',
        };
    }

    private static function notification(string $ton, string $titre, string $texte): array
    {
        return ['tone' => $ton, 'title' => $titre, 'text' => $texte, 'time' => 'À l’instant'];
    }

    private static function kpi(string $label, int|string $value, string $hint, string $icon, string $tone = 'neutral'): array
    {
        return ['label' => $label, 'value' => (string) $value, 'hint' => $hint, 'icon' => $icon, 'tone' => $tone];
    }

    private static function table(
        string $id,
        string $title,
        array $columns,
        array $rows,
        bool $full = false,
        ?string $statusLabel = null
    ): array {
        return [
            'type' => 'table',
            'id' => $id,
            'title' => $title,
            'full' => $full,
            'columns' => $columns,
            'rows' => $rows,
            'statusLabel' => $statusLabel ?? 'Statut',
        ];
    }

    private static function libelleStatut(string $statut): string
    {
        return match (mb_strtolower($statut)) {
            'payee' => 'Payée',
            'impayee' => 'Impayée',
            'a_payer' => 'À payer',
            'en_retard' => 'En retard',
            'recu' => 'Reçu',
            'manquant' => 'Manquant',
            'present' => 'Présence confirmée',
            'absent' => 'Indisponible',
            'en_attente' => 'En attente',
            'a_valider' => 'À valider',
            'confirmee' => 'Confirmée',
            'annulee' => 'Annulée',
            default => ucfirst(str_replace('_', ' ', $statut)),
        };
    }

    private static function tonStatut(string $statut): string
    {
        return match (mb_strtolower(trim($statut))) {
            'payee', 'recu', 'present', 'oui', 'confirmee', 'acceptee', 'livree', 'terminee' => 'ok',
            'impayee', 'en_retard', 'refusee', 'annulee', 'absent', 'non' => 'danger',
            'a_payer', 'manquant', 'en_attente', 'a_valider', 'sans_reponse', 'partielle' => 'warn',
            default => 'neutral',
        };
    }

    private static function montant(float $montant): string
    {
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    private static function compter(string $sql, array $parametres = []): int
    {
        return (int) self::valeur($sql, $parametres);
    }

    private static function valeur(string $sql, array $parametres = []): mixed
    {
        $requete = self::connexion()->prepare($sql);
        $requete->execute($parametres);
        return $requete->fetchColumn();
    }

    private static function ligne(string $sql, array $parametres = []): ?array
    {
        $lignes = self::lignes($sql, $parametres);
        return $lignes[0] ?? null;
    }

    private static function lignes(string $sql, array $parametres = []): array
    {
        $requete = self::connexion()->prepare($sql);
        $requete->execute($parametres);
        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function connexion(): PDO
    {
        return (new BaseDeDonnees())->obtenirConnexion();
    }

    //Résout un chemin pointé transmis par le contrôleur d'actions.
    public static function resoudre(array $donnees, string $chemin): mixed
    {
        $noeud = $donnees;
        foreach (explode('.', $chemin) as $segment) {
            if (!is_array($noeud) || !array_key_exists($segment, $noeud)) {
                return null;
            }
            $noeud = $noeud[$segment];
        }
        return $noeud;
    }
}
