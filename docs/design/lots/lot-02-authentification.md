# Lot 2 – Authentification et rôles

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-02-authentification` · **Taille** : M · **README** : étape 8

## Objectif
Sécuriser l'accès avec deux rôles (`admin`, `user`), une page de connexion fidèle à la maquette S01, la limitation des tentatives, l'expiration de session et le journal des connexions.

## Prérequis
Lot 1 validé. Décisions **D1, D2, D3, D4, D18** validées (D1 : à confirmer avec l'encadrant avant de commencer).

## Documents et captures à lire
`docs/design/ecrans-admin-technique.md` (S01) · `docs/design/admin/01-connexion.png` · `design-tokens.md`.

## Tâches
1. [ ] **Connexions** : déclarer la connexion SQLite `app` dans `config/database.php` (fichier `database/app.sqlite`, variable `APP_DB_DATABASE`), la **rendre par défaut** (`DB_CONNECTION=app` dans `.env.example`) et vérifier que le code Oracle utilise partout `DB::connection('oracle')`. Le fichier SQLite est ignoré par Git.
2. [ ] **Migration des utilisateurs** (connexion `app`) : `identifiant` (unique), `prenom`, `nom`, `role` (`admin`/`user`), `agence_id` (nullable, non utilisé), `password`, `actif` (booléen), horodatages. **Pas d'e-mail.** Supprimer les migrations d'exemple de Laravel qui ne servent pas (jetons de réinitialisation, e-mail).
3. [ ] **Modèle `User`** adapté (identifiant comme nom d'authentification, accesseur « Prénom N. », initiales de l'avatar, libellé du rôle).
4. [ ] **Seeder de développement** (`DevUsersSeeder`) : `amine.m` (admin) et `sonia.d` (user), **uniquement** si `APP_ENV=local`, mots de passe lus dans `SEED_ADMIN_PASSWORD` et `SEED_USER_PASSWORD` du `.env` (le seeder refuse de s'exécuter s'ils sont vides). Commande `php artisan app:creer-utilisateur` (interactive) pour les vrais comptes.
5. [ ] **Connexion** : `GET/POST /login`, `POST /logout` (CSRF), `LoginController`. Page fidèle à S01 avec le gabarit `guest`, bouton « Afficher/Masquer » du mot de passe (Alpine), état d'erreur et état « trop de tentatives » avec le composant `alert`. **Ne pas reproduire** le texte « Démonstration… » ni le lien « Accéder à la démonstration administrateur ».
6. [ ] **Limitation des tentatives** : 5 échecs par minute pour un couple identifiant + adresse IP, message clair, délai indiqué.
7. [ ] **Middlewares** : `auth` du framework pour les routes protégées ; `EnsureIsAdmin` (403 sinon) pour `/admin/*`. Redirections après connexion : `admin` → `/admin`, `user` → `/dashboard`. Pages provisoires `/admin` et `/dashboard` (titre seulement) pour prouver les redirections.
8. [ ] **Expiration d'inactivité** : `SESSION_LIFETIME=30` (minutes) ; retour sur `/login` avec le message « Votre session a expiré ». Régénération de l'identifiant de session à la connexion.
9. [ ] **Journal des connexions** `auth_log.json` : connexion réussie, échec, verrouillage, déconnexion (identifiant saisi, adresse IP, date, navigateur abrégé). **Jamais de mot de passe.** Écriture avec verrou (factoriser la logique d'`ExecutionLogger` dans une classe commune).
10. [ ] **Gabarit** : afficher l'identité réelle (« Prénom N. — Administrateur » ou « … — Utilisateur métier »), les initiales, le menu selon le rôle ; « Déconnexion » en `POST`.
11. [ ] **Règles de l'agent** : mettre à jour le paragraphe « migrations » d'`AGENTS.md` si nécessaire.
12. [ ] **Tests** (`phpunit.xml` : connexion `app` en SQLite mémoire, D18) : `Feature/AuthTest` (connexion admin → `/admin`, user → `/dashboard`, mauvais mot de passe, verrouillage après 5 échecs, invité renvoyé sur `/login`, user sur `/admin` = 403, déconnexion, mot de passe stocké haché, entrées du journal, expiration), test de la commande de création.

## Fichiers à créer ou modifier
`config/database.php`, `.env.example`, `database/migrations/*_create_users_table.php`, `database/seeders/DevUsersSeeder.php`, `app/Models/User.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Middleware/EnsureIsAdmin.php`, `app/Services/Auth/AuthLogger.php`, `app/Console/Commands/CreerUtilisateur.php`, `resources/views/auth/login.blade.php`, `routes/web.php`, `phpunit.xml`, `tests/Feature/AuthTest.php`.

## Commits prévus
1. `chore: connexion SQLite applicative définie par défaut`
2. `feat: table des utilisateurs, rôles et commande de création`
3. `feat: comptes de développement (environnement local uniquement)`
4. `feat: page de connexion fidèle à la maquette`
5. `feat: limitation des tentatives et expiration de session`
6. `feat: middlewares d'authentification et d'autorisation`
7. `feat: journal des connexions`
8. `feat: gabarit avec identité réelle et menu selon le rôle`
9. `test: authentification, rôles et journal des connexions`

## Résultat combiné à retourner
- **Tableau des scénarios** (admin, user, mauvais mot de passe, 5 échecs, session expirée, accès à `/admin` par un `user`, déconnexion, accès sans connexion) avec « attendu / obtenu ».
- **Extrait de `auth_log.json`** (sans secret) montrant au moins un succès, un échec et un verrouillage.
- **Captures** de S01 dans trois états (normal, erreur, verrouillé) comparées à la maquette.
- Preuve que la connexion par défaut est `app` : sortie de `php artisan config:show database.default` ; `php artisan migrate:status` ne mentionne que SQLite.
- Contenu de `.env.example` (sans mot de passe) et confirmation que `.env` et `database/app.sqlite` ne sont pas dans Git.
- Sortie de `vendor\bin\phpunit`.

## Checklist de validation (Marc)
- [ ] Je me connecte avec `amine.m` (→ `/admin`) et `sonia.d` (→ `/dashboard`).
- [ ] `sonia.d` ouvre `/admin` : page 403.
- [ ] Après 5 mauvais mots de passe : message de verrouillage.
- [ ] Le texte « Démonstration » est absent de la page de connexion.
- [ ] `git log -p` ne contient aucun mot de passe.
- [ ] `php test_laravel_db.php` (dossier `tools`) fonctionne toujours : Oracle n'a pas été affecté.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-02-authentification -m "merge: lot 2 - authentification et rôles"` · `git tag lot-02-valide`
