# Rapport de fin — Lot 7 : Espace métier (accueil et catalogue)

**Branche :** `lot-07-metier-catalogue`  
**Date :** 2026-10-14  
**Statut :** Terminé  

---

## 1. Périmètre réalisé

| Écran / fonctionnalité | Livré |
|---|---|
| M01 — Accueil métier (`GET /dashboard`) : salutation personnalisée, héro bannière avec CTA, 3 dernières extractions actives | Oui |
| M02 — Catalogue métier (`GET /catalogue`) : liste exhaustive des extractions actives, sous-titre explicatif | Oui |
| Recherche instantanée Alpine.js : filtrage temps réel insensible à la casse et aux accents sur code, titre et description | Oui |
| Composant `x-extraction-card` : puce de code, bouton rond `›`, titre, description limitée à 2 lignes, bouton « Utiliser l'extraction » | Oui |
| Grille responsive 3 colonnes (≈ 475 × 280 px, espacement 20 px) conforme à la maquette | Oui |
| Contrôle d'accès et séparation des espaces (D14) : middleware `EnsureIsUser`, redirection admin vers `/admin`, invité vers `/login` | Oui |
| Masquage strict des brouillons : ni sur M01, ni sur M02, 404 sur l'accès direct `/extractions/{code}` | Oui |
| Écran provisoire « Bientôt disponible » pour `/extractions/{code}` (en attente du formulaire du Lot 8) | Oui |
| Gestion des états vides (D16) : aucun résultat de recherche, catalogue sans extraction active | Oui |

---

## 2. Fichiers créés ou modifiés

### Nouveaux fichiers
- `app/Http/Middleware/EnsureIsUser.php` — middleware de restriction au rôle `user` (redirection admin vers `/admin`)
- `app/Http/Controllers/Metier/DashboardController.php` — contrôleur de l'accueil M01 (salutation, 3 extractions récentes)
- `app/Http/Controllers/Metier/CatalogueController.php` — contrôleur du catalogue M02 (toutes les actives triées)
- `app/Http/Controllers/Metier/ExtractionController.php` — contrôleur d'affichage provisoire M03 (404 si non active)
- `resources/views/components/extraction-card.blade.php` — composant partagé Blade de la carte d'extraction
- `resources/views/metier/dashboard.blade.php` — vue M01 Accueil espace métier
- `resources/views/metier/catalogue.blade.php` — vue M02 Catalogue avec filtrage Alpine.js
- `resources/views/metier/extraction-bientot.blade.php` — vue provisoire pour l'extraction active
- `tests/Feature/MetierCatalogueTest.php` — 20 tests fonctionnels couvrant accès, visibilité, tri et recherche

### Fichiers modifiés
- `bootstrap/app.php` — enregistrement de l'alias de middleware `user`
- `routes/web.php` — déclaration des routes métier protégées par `auth` et `user`
- `resources/views/layouts/app.blade.php` — ajout de la directive `@stack('scripts')` avant `</body>`
- `resources/css/components.css` — styles de la grille de cartes, badges code, hero card et états vides
- `config/database.php` — sécurisation de la connexion applicative SQLite par défaut (D1)

---

## 3. Décisions appliquées

| Décision | Implémentation |
|---|---|
| D1 — SQLite local `app` par défaut | `config/database.php` garantit la connexion `app` par défaut afin qu'aucune migration ne touche Oracle |
| D14 — Séparation stricte des espaces | Middleware `user` : un administrateur tentant d'accéder à `/dashboard`, `/catalogue` ou `/extractions/{code}` est redirigé vers `/admin` |
| D16 — Gestion des états | États « Aucun résultat » dans la recherche Alpine et « Aucune extraction disponible » si catalogue vide |
| D22 — Publication conditionnée | Seules les extractions ayant le statut `Active` sont chargées ; les brouillons (`Brouillon`) sont strictement exclus |

---

## 4. Tests

### Tests de régression et couverture globale
- **Total :** 119 tests, 270 assertions — **100% validés (vert)**
- Temps d'exécution : ~6.5 secondes

### Nouveaux tests `Feature/MetierCatalogueTest` (20 cas)
| Test | Résultat |
|---|---|
| Invité redirigé vers `/login` depuis `/dashboard` | OK |
| Invité redirigé vers `/login` depuis `/catalogue` | OK |
| Admin redirigé vers `/admin` depuis `/dashboard` (D14) | OK |
| Admin redirigé vers `/admin` depuis `/catalogue` (D14) | OK |
| Admin redirigé vers `/admin` depuis `/extractions/{code}` (D14) | OK |
| Utilisateur métier accède à `/dashboard` | OK |
| Dashboard affiche la salutation avec le prénom (« Bonjour, Sonia ») | OK |
| Dashboard affiche les 3 extractions actives les plus récentes | OK |
| Dashboard ne montre pas les brouillons (`CRT_001`) | OK |
| Dashboard contient le bouton CTA « Parcourir le catalogue › » | OK |
| Dashboard contient le lien « Voir le catalogue › » | OK |
| Utilisateur métier accède au `/catalogue` | OK |
| Catalogue affiche toutes les extractions actives | OK |
| Catalogue ne montre aucun brouillon | OK |
| Catalogue contient la barre de recherche textuelle | OK |
| Catalogue affiche le sous-titre explicatif | OK |
| Extraction active retourne un code HTTP 200 (« Bientôt disponible ») | OK |
| Brouillon retourne un code HTTP 404 (non trouvé) | OK |
| Extraction inexistante retourne un code HTTP 404 | OK |
| Tri par date de publication décroissante vérifié | OK |

---

## 5. Commits réalisés

- `73b6f5f` : `feat: middleware EnsureIsUser, routes metier et controleurs (D14)`
- `f7020fc` : `feat: carte d'extraction et grille du catalogue (composant + vues M01, M02, CSS)`
- `e80c18a` : `test: accueil et catalogue metier (20 tests, 32 assertions)`
- `95eeeed` : `fix: garantir que la connexion par défaut est app (D1)`
