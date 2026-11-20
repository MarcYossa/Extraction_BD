# Lot 12 – Tests complets, sécurité et documentation

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-12-qualite-securite` · **Taille** : M · **README** : étape 13

## Objectif
Consolider la qualité : suites de tests complètes, **revue de sécurité** avec preuves, audits des dépendances, et documentation permettant à quelqu'un d'autre d'installer, d'utiliser et d'exploiter l'application.

## Prérequis
Lot 11 validé. Décisions **D18** validée. Marc **signe** la checklist de sécurité (l'agent la prépare et fournit les preuves).

## Documents à lire
`AGENTS.md` (règles de sécurité) · `README.md` · `docs/design/catalogue/catalogue-queries-json.md` (section 9) · le rapport du lot 11.

## Tâches
**A. Tests**
1. [ ] **Inventaire des tests** : tableau « fonctionnalité du README (étape 13) → test existant » ; compléter les manques. `phpunit.xml` avec trois suites : `Unit`, `Feature` et `Oracle` (groupe `oracle`). Documenter les trois commandes de lancement.
2. [ ] **Matrice rôles × routes automatisée** : un test parcourt toutes les routes (`Route::getRoutes()`), les appelle en invité, `user` et `admin`, et compare à un tableau attendu (302 vers `/login`, 403, 200, 404). Toute route non déclarée dans le tableau fait **échouer** le test.
3. [ ] **Couverture** : seulement si un pilote (PCOV) est installé. Sinon, ne pas l'inventer : fournir la liste des classes de `app/Services` et du test qui les couvre.
4. [ ] **Tests de non-régression** des valeurs de référence (lots 3, 8 et 9) regroupés dans la suite `Oracle`.

**B. Sécurité** (une preuve par ligne dans le rapport)
5. [ ] **En-têtes HTTP** via un middleware : `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` restrictive, et une **Content-Security-Policy** (`default-src 'self'`, pas de CDN). Alpine standard exige `unsafe-eval` : **évaluer le build `@alpinejs/csp`** ; si le coût est trop élevé, documenter le compromis et demander à Marc.
6. [ ] **Session et cookies** : `HttpOnly`, `SameSite=Lax`, `Secure` quand `APP_ENV=production` ; expiration d'inactivité testée ; régénération à la connexion.
7. [ ] **CSRF** : prouver que toute route `POST`/`PUT`/`PATCH` le vérifie (test de la matrice : sans jeton, 419).
8. [ ] **Injection SQL** : revue de tous les appels à la base ; recherche de `DB::raw`, `selectRaw`, `statement`, `whereRaw`, concaténations ; chaque exécution passe par `QueryCompiler` et des valeurs liées. Le résultat de la revue (liste des fichiers examinés) est dans le rapport.
9. [ ] **XSS** : recherche de `{!!` dans les vues (aucun usage non justifié) ; test avec une saisie `<script>alert(1)</script>` dans un paramètre texte, un titre d'extraction et un nom de client : toujours affichée comme texte.
10. [ ] **Limitation de débit** : `throttle` sur la connexion (déjà fait au lot 2) et sur l'exécution et l'export (par exemple 30 par minute et par utilisateur) pour protéger Oracle.
11. [ ] **Exposition de fichiers** : prouver que `queries.json`, `execution_log.json`, `auth_log.json`, `.env`, `database/app.sqlite` et `storage/app/private/results/` ne sont **pas** accessibles par une adresse web (tests HTTP sur ces chemins) ; absence du lien `public/storage`.
12. [ ] **Mots de passe** : hachage fort (Laravel par défaut) ; longueur minimale de 12 caractères dans `app:creer-utilisateur` ; aucun mot de passe en clair dans le dépôt : recherche dans tout l'historique (`git log -p`) avec les motifs `password`, `APP_KEY`, `DB_PASSWORD`, `secret`.
13. [ ] **Configuration de production** : `APP_DEBUG=false`, erreurs génériques, aucun `dd()` ni `dump()` oublié, journaux Laravel sans données métier.
14. [ ] **Dépendances** : `composer audit` et `npm audit` ; pour chaque alerte, correction ou justification écrite ; liste des paquets ajoutés depuis le début du projet.
15. [ ] **Revue critique** : l'agent relit son propre code et liste les **10 points les plus risqués** (fichier, risque, correctif proposé) ; Marc décide lesquels corriger dans ce lot.

**C. Documentation**
16. [ ] **README final** : prérequis, installation pas à pas, configuration `.env`, commandes (tests, build, catalogue), structure, routes, capture d'écran.
17. [ ] **Test de reprise** : cloner le dépôt dans un dossier vide et suivre le README à la lettre jusqu'à `php artisan serve` et au premier résultat d'extraction ; consigner chaque point où le README est insuffisant et le corriger.
18. [ ] **Guides** : `docs/guides/guide-administrateur.md` (créer, tester, publier, désactiver une extraction) et `docs/guides/guide-utilisateur.md` (trouver, exécuter, exporter), avec captures de l'application réelle.
19. [ ] **Checklist de sécurité** `docs/securite/checklist-securite.md` : une ligne par point 5 à 14, avec preuve, état et case de signature de Marc.

## Fichiers à créer ou modifier
`phpunit.xml`, `app/Http/Middleware/SecurityHeaders.php`, `config/session.php`, `routes/web.php` (throttle), `tests/Feature/{RoutesMatrixTest,SecurityTest,XssTest}.php`, `README.md`, `docs/guides/*`, `docs/securite/checklist-securite.md`.

## Commits prévus
1. `test: suites Unit, Feature et Oracle et inventaire des tests`
2. `test: matrice des rôles et des routes`
3. `feat: en-têtes de sécurité et politique de contenu`
4. `feat: limitation du débit des exécutions et des exports`
5. `test: sécurité (CSRF, XSS, exposition de fichiers)`
6. `fix: points de la revue critique retenus par Marc`
7. `docs: README final, guides et checklist de sécurité`

## Résultat combiné à retourner
- **Rapport de tests** : nombre par suite, durée, sortie brute des trois commandes.
- **Matrice rôles × routes** (tableau complet).
- **Checklist de sécurité** avec une preuve par ligne (sortie de commande, test, capture).
- Sorties de `composer audit` et `npm audit`, et liste des paquets ajoutés.
- **Revue critique** : les 10 points les plus risqués et ce qui a été corrigé.
- **Compte rendu du test de reprise** et diff du README.
- Les deux guides avec leurs captures.

## Checklist de validation (Marc)
- [ ] Les trois suites passent chez moi ; la suite `Oracle` donne les valeurs de référence.
- [ ] Je sais ajouter une route sans l'oublier dans la matrice : le test échoue tant que ce n'est pas déclaré.
- [ ] Les chemins `.env`, `queries.json` et `storage/*` renvoient 403 ou 404 par le navigateur.
- [ ] Le test de reprise a été suivi à la lettre et fonctionne.
- [ ] **Je signe la checklist de sécurité** (ou je liste les points à corriger).

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-12-qualite-securite -m "merge: lot 12 - tests, sécurité et documentation"` · `git tag lot-12-valide`
