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

## Comptes de test (fixtures)

Mot de passe commun : `Club-M2L-2026`

| Rôle | E-mail |
|---|---|
| Licencié / Parent | sophie.martin@example.fr |
| Éducateur | karim.benali@m2l-fc.fr |
| Secrétaire | claire.dubois@m2l-fc.fr |
| Président | philippe.roux@m2l-fc.fr |
| Trésorier | nadia.lefebvre@m2l-fc.fr |
| Service civique | mael.guerin@m2l-fc.fr |
| Arbitre | julien.renaud@m2l-fc.fr |
| Resp. événementiel | amelie.bertrand@m2l-fc.fr |
| Resp. achats | thomas.lemaire@m2l-fc.fr |
| Resp. tournois | antoine.morel@m2l-fc.fr |
| Resp. école de foot | emilie.vasseur@m2l-fc.fr |

À supprimer avant la mise en ligne : `database/fixtures/users.json` (remplacé par votre table des membres).

## Données du membre connecté

Nom, prénom, e-mail et rôle viennent de la **session** (`Auth::attempt`) et sont rendus côté serveur dans la barre latérale, la barre du haut et le titre « Bonjour, Prénom ». Le menu est aussi rendu par PHP.

Le JSON `#dashboard-data` ne contient **que** les données du rôle du membre connecté : `meta`, `hero`, `agenda`, `kpis`, `panels`, `notifications`. Aucun menu, aucun autre rôle, aucune donnée de profil. Le rôle n'est jamais un paramètre d'URL : il vient de la session et passe par la liste blanche `app/Config/roles.php`.

## Architecture (MVC)

```
public/index.php          front controller + routes + en-têtes de sécurité
app/Core/                 Router, View, Auth, Csrf, Session, Roles, Shell, Fr (dates)
app/Config/roles.php      les 11 rôles : libellé, action principale, menu
app/Controllers/          Home, Auth, Dashboard, Module, Action, Import, Inscription, Calendar
app/Models/               ClubRepository, DashboardRepository, UserRepository
app/Views/                layouts (site, app, plain), home, dashboard, auth, errors
database/fixtures/        données de départ (JSON)  ->  à remplacer par vos requêtes SQL
public/assets/            css (base, site, dashboard) et js (site, dashboard, theme)
storage/                  journaux d'actions, pré-inscriptions, imports CSV (non versionné)
```

### Parcours d'une requête
1. `public/index.php` démarre la session, applique les en-têtes de sécurité et associe chaque URL à un contrôleur.
2. Le contrôleur vérifie les droits et récupère les données nécessaires auprès d'un dépôt.
3. Le dépôt lit les fichiers JSON de démonstration (il pourra ensuite interroger la base de données).
4. `View` affiche le résultat dans un layout commun.

Gardez une responsabilité par couche : les règles de présentation vont dans les vues, les contrôles d'accès et la validation dans les contrôleurs, et l'accès aux données dans `app/Models/`. Les commentaires expliquent les choix non évidents (sécurité, calcul métier) ; ils évitent de répéter ce que le nom des méthodes rend déjà clair.

### Brancher votre base de données
Les trois dépôts de `app/Models/` lisent aujourd'hui `database/fixtures/`. Remplacez le corps de leurs méthodes par du PDO : **la forme des tableaux retournés est le contrat** avec les vues et `dashboard.js`.
`DashboardRepository::forRole()` doit retourner : `lead`, `counts` (pastilles du menu, par URL), `hero`, `agenda`, `kpis`, `panels`, `notifications`.

### Ajouter un rôle
1. Une entrée dans `app/Config/roles.php` (libellé, menu, action principale).
2. Un fichier `database/fixtures/dashboard/{role}.json` (ou la requête SQL équivalente).
Types de panneaux disponibles : `convocations`, `attendance`, `dossiers`, `table`, `import`, `documents`, `categories`, `finance`, `trend`, `decisions`, `tasks`.

## Sécurité en place
- Mots de passe : `password_hash` / `password_verify`, temps de réponse constant, blocage 60 s après 5 échecs, `session_regenerate_id` à la connexion, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` en HTTPS).
- CSRF sur tous les formulaires POST et sur les appels `fetch`.
- RBAC serveur : une section hors du menu du rôle renvoie 403. `/api/actions` reconstruit le tableau de bord du rôle connecté et refuse tout chemin qui n'y figure pas (aucune action d'un autre rôle n'est déclenchable).
- Échappement systématique (`e()`), JSON injecté avec `JSON_HEX_*`, pas de redirection ouverte, liste blanche sur l'export `.ics`.
- Import CSV : rôle, extension, taille (2 Mo) et colonnes contrôlés. Pré-inscription : champ piège anti-robots, 3 envois/heure/session.
- CSP stricte (`script-src 'self'`), `X-Frame-Options`, `nosniff`.

## À brancher côté métier
Les endroits sont marqués `TODO métier` : `ActionController` (valider, relancer, confirmer…), `ImportController` (insertion des licenciés), `InscriptionController` (création du dossier + e-mail au secrétariat). Les sections du menu (`ModuleController`) affichent une page d'attente jusqu'à ce que vous créiez leur contrôleur.

## Déploiement
Servez `public/` comme racine web. Apache : `public/.htaccess` fourni. Nginx : `try_files $uri /index.php?$query_string;`.
Droits d'écriture sur `storage/`. Polices : Archivo et Figtree via Google Fonts (à auto-héberger si vous le souhaitez, et adapter la CSP).
