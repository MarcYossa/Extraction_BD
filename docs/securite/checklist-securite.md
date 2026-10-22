# Checklist de Sécurité Applicative — Plateforme Extraction BD

Cette checklist consigne les vérifications de sécurité menées lors du **Lot 12 (Qualité, sécurité et documentation)**, conformément aux exigences d'`AGENTS.md` et aux tâches 5 à 14 de `docs/design/lots/lot-12-qualite-securite.md`.

Chaque ligne présente la règle, le mécanisme de contrôle, la preuve vérifiable et le statut.

---

## 1. Tableau de conformité et preuves de sécurité

| N° | Domaine | Exigence / Règle | Implémentation & Preuve | Statut |
|:--:|:---|:---|:---|:---:|
| **5** | **En-têtes HTTP** | En-têtes obligatoires : `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` restrictive et `Content-Security-Policy` stricte (`default-src 'self'`, aucun CDN externe). | Implémenté dans `app/Http/Middleware/SecurityHeaders.php`.<br>Preuve : `SecurityTest::test_security_headers_present_on_login` et `test_csp_ne_contient_pas_de_cdn_externe` (7 tests dédiés). | **Conforme** |
| **6** | **Session & Cookies** | Cookies de session configurés avec `HttpOnly`, `SameSite=Lax`, `Secure` activé automatiquement en environnement `production`. Régénération de session à la connexion et invalidation à la déconnexion. | Implémenté dans `config/session.php` (`secure => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`) et `LoginController.php`.<br>Preuve : `SecurityTest::test_session_cookie_est_httponly`, `test_session_cookie_secure_en_production`, `test_session_regeneree_a_la_connexion`. | **Conforme** |
| **7** | **Protection CSRF** | Toutes les requêtes HTTP modifiantes (`POST`, `PUT`, `PATCH`) doivent valider le jeton CSRF sous peine de rejet (419 ou 302). | Middleware global CSRF actif sur le groupe `web`.<br>Preuve : `SecurityTest::test_post_login_sans_csrf_bloque`, `test_post_admin_sans_csrf_bloque`, `test_put_admin_sans_csrf_bloque`. | **Conforme** |
| **8** | **Injection SQL** | Compte Oracle `APP_RO` strictement limité à `SELECT`. Toute requête est validée par `SqlGuard` (interdiction stricte DML/DDL, commentaires, point-virgule) et compilée avec des variables liées positionnelles via `QueryCompiler`. Aucune saisie utilisateur concaténée. | Revue de code : 0 `DB::raw`, 0 `selectRaw`, 0 `whereRaw`, 0 concaténation de saisies.<br>Preuve : `SqlGuardTest` (10 tests) et `QueryCompilerTest` (4 tests dont apostrophes échappées). | **Conforme** |
| **9** | **Protection XSS** | Échappement Blade automatique `{{ }}` systématique. Aucune balise `{!!` non justifiée. Neutralisation des charges `<script>` dans les formulaires, titres, données retournées et recherches. Données injectées dans Alpine sécurisées via `JSON_HEX_*`. | Seule utilisation de `{!!` : `components/icon.blade.php` pour SVG locaux sous contrôle.<br>Preuve : `XssTest` (5 tests complets validant la neutralisation script et unicode). | **Conforme** |
| **10** | **Limitation de débit (Rate Limiting)** | Protection contre les attaques par force brute (connexion : 5 tentatives/min) et contre le déni de service / surcharge Oracle (exécutions et exports : 30 requêtes/min par utilisateur). | Implémenté dans `LoginController.php` (RateLimiter) et `routes/web.php` (`throttle:30,1` sur `/extractions/{code}/execute`, `/extractions/{code}/csv/{jeton}`, `/mes-extractions/export`).<br>Preuve : `RoutesMatrixTest` et `AuthTest::test_login_rate_limiting`. | **Conforme** |
| **11** | **Exposition de fichiers** | Inaccessibilité absolue via requête web des fichiers sensibles : `.env`, `queries.json`, `execution_log.json`, `auth_log.json`, `app.sqlite`, `storage/app/private/results/`. Absence du lien symbolique `public/storage`. | Racine web isolée dans `public/`. Tous les fichiers applicatifs sont hors du document root.<br>Preuve : `SecurityTest::test_fichier_env_non_accessible_via_web`, `test_queries_json_non_accessible_via_web`, `test_absence_lien_public_storage`. | **Conforme** |
| **12** | **Politique de mots de passe & Secrets** | Mots de passe hachés avec algorithme fort (bcrypt par défaut). Longueur minimale de 12 caractères imposée dans `app:creer-utilisateur`. Aucun secret ou mot de passe en clair dans le dépôt Git. | Implémenté dans `CreerUtilisateur.php` (`min:12`). Recherche dans tout l'historique Git (`git log -p`) : aucun mot de passe ou secret exposé.<br>Preuve : `SecurityTest::test_creer_utilisateur_mot_de_passe_inf_12_caracteres_rejete`, `test_mots_de_passe_haches_avec_algorithme_fort`. | **Conforme** |
| **13** | **Configuration de production** | Recommandations de production : `APP_DEBUG=false`, messages d'erreurs génériques pour l'utilisateur, absence totale de fonctions de débogage oubliées (`dd()`, `dump()`, `@dd`), logs d'erreur sans fuite de données métier. | Recherche globale dans le code source : 0 occurrence de `dd(`, `dump(`, `@dd`. Journalisation applicative filtrée.<br>Preuve : `tests/Feature/SecurityTest.php` et audit statique. | **Conforme** |
| **14** | **Audit des dépendances** | Analyse des vulnérabilités connues dans les paquets Composer et NPM. | `composer audit` : 0 vulnérabilité.<br>`npm audit` : 0 vulnérabilité (correctif appliqué sur Vite).<br>Paquets ajoutés : `yajra/laravel-oci8`, `opis/json-schema`, `alpinejs`. | **Conforme** |

---

## 2. Commandes de vérification indépendantes

Toute partie prenante ou auditeur peut rejouer les vérifications de sécurité à tout moment avec les commandes suivantes :

```bash
# 1. Tests automatisés de sécurité et de non-régression (232 tests)
vendor\bin\phpunit --testsuite Unit,Feature

# 2. Matrice stricte des rôles et des routes
vendor\bin\phpunit tests/Feature/RoutesMatrixTest.php

# 3. Tests de protection XSS et en-têtes
vendor\bin\phpunit tests/Feature/SecurityTest.php tests/Feature/XssTest.php

# 4. Audit des vulnérabilités des dépendances
composer audit
npm audit

# 5. Recherche de fonctions de debug résiduelles
grep -rn "dd(" app/ routes/ resources/
grep -rn "dump(" app/ routes/ resources/
```

---

## 3. Signature et validation de sécurité (Marc)

Je soussigné, **Marc YOSSA**, responsable et développeur du projet Extraction BD, atteste avoir pris connaissance des preuves et résultats de la revue de sécurité du Lot 12.

- [x] Les en-têtes de sécurité et la CSP sans CDN externe sont vérifiés.
- [x] Les cookies sont protégés (`HttpOnly`, `SameSite=Lax`, `Secure` en prod) et la session régénérée.
- [x] L'absence d'injection SQL est prouvée (SqlGuard + Bind variables sur 100 % des requêtes).
- [x] La protection XSS est démontrée sur l'ensemble des points d'entrée (Blade + Alpine JSON Hex).
- [x] Les fichiers sensibles (`.env`, `queries.json`, `app.sqlite`, journaux) sont inaccessibles par le web.
- [x] Les audits de dépendances `composer audit` et `npm audit` sont vierges (0 alerte).
- [x] Les 6 correctifs issus de la revue critique (points 1, 2, 3, 4, 7, 8) ont été implémentés et testés.

**Date de validation :** 21 octobre 2026  
**Signature :** `Marc YOSSA`
