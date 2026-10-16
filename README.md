# M2L Football Club : site visiteur + espace membre

PHP 8.1+ (extension `mbstring`), sans dépendance ni base de données pour démarrer.

```bash
php -S localhost:8000 -t public public/index.php
```
Puis ouvrir http://localhost:8000 (site visiteur) ou http://localhost:8000/connexion (espace membre).

## Ce que contient le projet

| Zone | Contenu |
|---|---|
| **Site visiteur** `/` | Résultats, classements, calendriers par équipe (+ export `.ics`), actualités, événements, inscriptions (infos, tarifs, pré-inscription en ligne) et boutique. Rendu côté serveur. |
| **Espace membre** `/tableau-de-bord` | Un tableau de bord propre à chacun des 11 rôles : licencié/parent, éducateur, secrétaire, président, trésorier, service civique, arbitre, responsable événementiel, achats, organisation des tournois, école de foot. |

## Connexion à MySQL et comptes membres

La connexion MySQL est configurée dans `app/Config/BaseDeDonnees.php` (WampServer local par défaut :
`localhost`, base `m2l_football`, utilisateur `root`, mot de passe vide). Les comptes de connexion
sont lus et créés dans votre table `utilisateurs` existante. Les demandes en attente sont stockées
dans la table `demandes_comptes` de la même base.

Le dépôt utilise `id_utilisateurs`, `prenom`, `nom_utilisateurs`, `email`, `mdp_hash`, `role` et
`actif`. Seuls les comptes dont `actif = 1` peuvent se connecter. Le rôle doit correspondre à une
valeur définie dans `app/Config/roles.php`. `mdp_hash` doit contenir un hash produit par
`password_hash`, jamais le mot de passe en clair.

### Demandes de compte du personnel
La table `demandes_comptes` est créée dans la base `m2l_football`. Le lien « Vous êtes membre du
personnel ? » sur la page de connexion ouvre le formulaire de demande. Le président traite les
demandes depuis « Utilisateurs et rôles » dans son espace membre. Une acceptation crée et active
le compte dans `utilisateurs` ; un refus ne crée aucun compte. Les rôles `president` et `licencie`
ne peuvent pas être demandés depuis le formulaire.

Ne publiez jamais de vrais mots de passe ni des identifiants de base de données. Remplacez les
identifiants locaux par une configuration privée adaptée avant tout déploiement.

## Données du membre connecté

Nom, prénom, e-mail et rôle viennent de la **session** (`Authentification::verifierIdentifiants`) et sont rendus côté serveur dans la barre latérale, la barre du haut et le titre « Bonjour, Prénom ». Le menu est aussi rendu par PHP.

Le JSON `#dashboard-data` ne contient **que** les données du rôle du membre connecté : `meta`, `hero`, `agenda`, `kpis`, `panels`, `notifications`. Aucun menu, aucun autre rôle, aucune donnée de profil. Le rôle n'est jamais un paramètre d'URL : il vient de la session et passe par la liste blanche `app/Config/roles.php`.

## Architecture (MVC)

```
public/index.php          front controller + routes + en-têtes de sécurité
app/Core/                 Routeur, Vue, Authentification, ProtectionCsrf, SessionUtilisateur, RegistreRoles, PreparationEspaceMembre, DatesFrancaises
app/Config/roles.php      les 11 rôles : libellé, action principale, menu
app/Config/BaseDeDonnees.php connexion PDO à MySQL
app/Controllers/          contrôleurs de l'accueil, connexion, calendrier, tableau de bord, import et inscriptions
app/Models/               dépôts Club, TableauBord, Utilisateurs et DemandesCompte
app/Views/                layouts (site, app, plain), home, dashboard, auth, errors
database/fixtures/        données de démonstration du club et des tableaux de bord (JSON)
public/assets/            css (base, site, dashboard) et js (site, dashboard, theme)
storage/                  journaux d'actions, pré-inscriptions, imports CSV (non versionné)
```

### Parcours d'une requête
1. `public/index.php` démarre la session, applique les en-têtes de sécurité et associe chaque URL à un contrôleur.
2. Le contrôleur vérifie les droits et récupère les données nécessaires auprès d'un dépôt.
3. Les comptes sont lus dans MySQL via PDO ; le club et les tableaux de bord utilisent encore leurs fixtures JSON.
4. `Vue` affiche le résultat dans un gabarit commun.

Gardez une responsabilité par couche : les règles de présentation vont dans les vues, les contrôles d'accès et la validation dans les contrôleurs, et l'accès aux données dans `app/Models/`. Les commentaires expliquent les choix non évidents (sécurité, calcul métier) ; ils évitent de répéter ce que le nom des méthodes rend déjà clair.

### Lire et modifier le code
Les classes et méthodes écrites pour le projet portent des noms français : par exemple
`ControleurDemandesCompte::envoyerDemande()`, `DepotDemandesCompte::accepterDemande()` et
`Authentification::exigerConnexion()`. Les noms des variables de traitement sont également
francisés pour faciliter la lecture du parcours des données. Quelques clés restent stables entre
contrôleurs et vues pour transmettre les informations à leurs gabarits ; les colonnes SQL, les
clés JSON, les champs de formulaire et les attributs HTML/JavaScript restent eux aussi inchangés
car ils servent de contrats avec d'autres parties du projet. Les commentaires expliquent surtout
les étapes moins évidentes, comme le contrôle CSRF, les transactions SQL et la vérification du rôle.

Certains noms restent volontairement inchangés car ils sont des contrats avec d'autres parties :
les adresses web (`/connexion`), les colonnes SQL (`mdp_hash`), les clés des fichiers JSON
(`team`, `time`), les champs de formulaire (`email`, `password`) et les attributs
HTML/JavaScript (`data-*`). Les traduire demanderait de modifier en même temps la base, les données
enregistrées ou les scripts du navigateur.

### Données en base
`DepotUtilisateurs` interroge la table MySQL existante `utilisateurs` avec une requête préparée.
Les dossiers affichés au secrétariat et leurs indicateurs du tableau de bord sont chargés depuis
la table MySQL `dossiers` ; les autres données de ce tableau de bord restent dans sa fixture JSON.
`DepotClub` et les autres panneaux de `DepotTableauBord` utilisent leurs fixtures JSON de démonstration.

### Ajouter un rôle
1. Une entrée dans `app/Config/roles.php` (libellé, menu, action principale).
2. Un fichier `database/fixtures/dashboard/{role}.json` (ou la requête SQL équivalente).
Types de panneaux disponibles : `convocations`, `attendance`, `dossiers`, `table`, `import`, `documents`, `categories`, `finance`, `trend`, `decisions`, `tasks`.

## Sécurité en place
- Mots de passe : `password_hash` / `password_verify`, temps de réponse constant, blocage 60 s après 5 échecs, `session_regenerate_id` à la connexion, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` en HTTPS).
- CSRF sur tous les formulaires POST et sur les appels `fetch`.
- RBAC serveur : une section hors du menu du rôle renvoie 403. `/api/actions` reconstruit le tableau de bord du rôle connecté et refuse tout chemin qui n'y figure pas (aucune action d'un autre rôle n'est déclenchable).
- Échappement systématique (`echapperHtml()`), JSON injecté avec `JSON_HEX_*`, pas de redirection ouverte, liste blanche sur l'export `.ics`.
- Import CSV : rôle, extension, taille (2 Mo) et colonnes contrôlés. Pré-inscription : champ piège anti-robots, 3 envois/heure/session.
- CSP stricte (`script-src 'self'`), `X-Frame-Options`, `nosniff`.

## À brancher côté métier
Les endroits sont marqués `À relier au métier` : `ControleurActions` (valider, relancer,
confirmer…), `ControleurImport` (insertion des licenciés) et `ControleurInscription` (création du
dossier et courriel au secrétariat). Les sections du menu (`ControleurModule`) affichent une page
d'attente tant que leur logique métier n'est pas encore créée.

## Déploiement
Servez `public/` comme racine web. Apache : `public/.htaccess` fourni. Nginx : `try_files $uri /index.php?$query_string;`.
Droits d'écriture sur `storage/`. Polices : Archivo et Figtree via Google Fonts (à auto-héberger si vous le souhaitez, et adapter la CSP).
