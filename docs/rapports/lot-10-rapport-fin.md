# Rapport de fin — Lot 10 : Journaux d'exécution et historique personnel

**Branche :** `lot-10-journaux`  
**Date :** 2026-10-19  
**Statut :** Prêt pour validation  

---

## 1. Périmètre réalisé

| Tâche / Fonctionnalité | Livré | Description |
|---|---|---|
| **Service `LogReader`** | Oui | Lecture tolérante (fichiers absents, vides, corrompus), filtres multiples (utilisateur, extraction, mode, statut, période, recherche plein texte), tri antéchronologique, pagination serveur (50/page). |
| **Mesures de performance** | Oui | Benchmark synthétique à 5 000 (0,050 s) et 20 000 entrées (0,201 s), bien sous le seuil d'1 seconde. Groupe `#[Group('lent')]` exclu des tests de routine. |
| **Journal d'administration (`GET /admin/logs`)** | Oui | Vue S08/S09 avec deux onglets (« Exécutions » et « Connexions »), 3 cartes KPI, filtres interactifs, modal de consultation des paramètres, pagination et export CSV neutralisé. |
| **Historique personnel (`GET /mes-extractions`)** | Oui | Vue M05 (« Retrouver mes extractions »), 3 cartes KPI personnalisées, filtres d'activité et de statut, bouton « Relancer » avec pré-remplissage des paramètres, désactivation claire si extraction inactive, export CSV personnel. |
| **Isolation stricte des utilisateurs** | Oui | Filtre utilisateur imposé côté serveur (`auth()->user()->identifiant`), invulnérable à la manipulation d'URL (`?utilisateur=...` ou `?q=...`), protection des paramètres confidentiels. |
| **Contrôle d'accès rôle-based** | Oui | Accès à `/admin/logs` strictement réservé au rôle `admin` (403 Forbidden pour utilisateur métier). Redirection des administrateurs accédant à `/mes-extractions` vers leur espace. |
| **Navigation & Menus** | Oui | Entrée « Journal d'activité » dans la barre latérale administrateur et « Mon historique » dans la barre latérale métier avec icône dédiée (`history.svg`). |
| **Neutralisation formule Excel (CSV)** | Oui | Neutralisation des injections (`=`, `+`, `-`, `@`, tab) par préfixage d'une apostrophe sur tous les exports CSV (admin et métier). |

---

## 2. Fichiers créés ou modifiés

### Nouveaux fichiers
- `app/Services/Extraction/LogReader.php` — Service central de lecture, filtrage, pagination et calcul des KPI des journaux.
- `app/Http/Controllers/Admin/LogController.php` — Contrôleur d'administration pour la consultation et l'export du journal d'activité.
- `app/Http/Controllers/Metier/MesExtractionsController.php` — Contrôleur de l'espace métier pour l'historique personnel et son export.
- `resources/views/admin/logs/index.blade.php` — Vue d'administration avec onglets Exécutions / Connexions et modal Alpine.js.
- `resources/views/metier/mes-extractions.blade.php` — Vue métier de l'historique personnel avec relance d'extraction.
- `resources/svg/history.svg` — Icône SVG Feather/Lucide pour la navigation d'historique/journal.
- `tests/Unit/LogReaderTest.php` — 8 tests unitaires couvrant la tolérance aux erreurs, le tri, la pagination et les filtres.
- `tests/Feature/LogPerformanceTest.php` — 2 tests de benchmark (5 000 et 20 000 entrées) sous le groupe `lent`.
- `tests/Feature/AdminLogsTest.php` — 6 tests fonctionnels (contrôle d'accès 403, affichage exécutions, connexions, filtres, export CSV).
- `tests/Feature/MesExtractionsTest.php` — 10 tests fonctionnels (redirections, isolation, relance, extraction inactive, fuite de recherche, menus, neutralisation CSV).

### Fichiers modifiés
- `app/Http/Controllers/Metier/ExtractionController.php` — Support du pré-remplissage direct des paramètres via query string `params` pour l'action « Relancer ».
- `resources/css/components.css` — Styles des KPI cards (`.kpi-grid`, `.kpi-card`), onglets (`.tabs-nav`), tableau de journalisation, badges d'activité et cellules d'historique.
- `resources/views/layouts/app.blade.php` — Ajout des entrées de navigation « Journal d'activité » (admin) et « Mon historique » (métier).
- `routes/web.php` — Déclaration des routes `/admin/logs`, `/admin/logs/export`, `/mes-extractions` et `/mes-extractions/export`.
- `phpunit.xml` — Exclusion par défaut du groupe `lent`.

---

## 3. Décisions appliquées

| Décision | Implémentation |
|---|---|
| **D14 — Séparation stricte des espaces** | Routes `/admin/logs*` sous middleware `admin` ; routes `/mes-extractions*` sous middleware `user`. Contrôle côté serveur, pas seulement visuel. |
| **D15 — Format des journaux & seuil d'1 s** | Évaluation de volumétrie à 20 000 entrées : 0,201 s. Le format JSON unique reste performant et respecte le seuil. |
| **D16 — Historique personnel et relance** | Page `/mes-extractions` permettant de relancer une exécution avec ses paramètres initiaux pré-remplis sur `/extractions/{code}`. |
| **Neutralisation CSV** | Tout champ exporté débutant par `=`, `+`, `-`, `@`, `\t` ou `\r` est préfixé d'une apostrophe pour désactiver l'exécution de formules dans Microsoft Excel. |

---

## 4. Mesures de performance (D15)

Le benchmark synthétique (`tests/Feature/LogPerformanceTest.php`) a mesuré le temps complet de lecture, désérialisation, filtrage par utilisateur, tri antéchronologique et pagination de 50 éléments :

- **5 000 entrées :** `0,0503 secondes` (~50 ms) — 1 000 résultats filtrés.
- **20 000 entrées :** `0,2014 secondes` (~201 ms) — 4 000 résultats filtrés.

### Recommandation architecturale
Le temps à 20 000 entrées (0,201 s) se situe très largement sous le plafond d'1 seconde fixé par la décision D15. Par conséquent :
1. **Maintien du format actuel (`execution_log.json`)** pour cette phase, sans complexification inutile.
2. Une migration vers un stockage en lignes JSON (`.jsonl`) avec rotation mensuelle pourra être envisagée si la volumétrie dépasse les 100 000 entrées annuelles.

---

## 5. Preuve d'isolation et sécurité

1. **Isolation dans l'interface (`/mes-extractions`) :**
   - Le contrôleur `MesExtractionsController` force le filtre `'utilisateur' => auth()->user()->identifiant`.
   - Si `sonia.d` tente d'injecter `?utilisateur=amine.m` ou `?utilisateur=fatou.s`, le paramètre URL est ignoré.
   - Si `sonia.d` utilise la recherche textuelle `?q=fatou.s`, aucune ligne appartenant à `fatou.s` n'est retournée (`test_recherche_textuelle_ne_permet_pas_de_fuiter_les_donnees_d_un_autre_utilisateur`).
2. **Isolation dans l'export CSV :**
   - L'export streamé applique le même filtre strict côté serveur.
3. **Protection des routes d'administration :**
   - Toute requête d'un utilisateur métier sur `/admin/logs` déclenche immédiatement une réponse HTTP `403 Forbidden` (`test_utilisateur_metier_recoit_403`).
4. **Confidentialité des données :**
   - Aucun résultat brut d'extraction n'est stocké dans les journaux ni affiché à l'écran.

---

## 6. Tests automatisés

Exécution complète : `vendor\bin\phpunit`

```
PHPUnit 12.5.37 by Sebastian Bergmann and contributors.
Runtime: PHP 8.3.35

OK, but there were issues!
Tests: 160, Assertions: 436, PHPUnit Notices: 5.
```

### Détail des tests du Lot 10 (26 tests spécifiques) :
- `Tests\Unit\LogReaderTest` : 8 tests (fichiers absents, corrompus, entrées invalides, filtres mode/statut/utilisateur/extraction/dates, pagination, calcul des KPIs).
- `Tests\Feature\AdminLogsTest` : 6 tests (authentification, 403 métier, exécutions, connexions, filtres admin, export CSV neutralisé).
- `Tests\Feature\MesExtractionsTest` : 10 tests (visiteur, redirection admin, affichage historique, isolation URL, relance active, relance inactive désactivée, export CSV personnel, isolation recherche, visibilité menus, neutralisation formule CSV).
- `Tests\Feature\LogPerformanceTest` : 2 tests (benchmark 5 000 et 20 000 entrées — groupe `lent`).

---

## 7. Choix de présentation et fidélité aux maquettes

- **Administration (S08 / S09 — `9-Journal-activités.png`) :**
  - Reproduction fidèle de l'en-tête, des 3 indicateurs KPI (Activités aujourd'hui, Taux de succès, Exports générés), des onglets Exécutions / Connexions et de la table.
  - La modal Alpine.js affiche le détail complet des paramètres sous forme de liste clé-valeur formatée lisiblement.
- **Espace Métier (M05 — `5- Historique.png` et `5.1-Historique.png`) :**
  - Titre de page : « Retrouver mes extractions ».
  - 3 indicateurs KPI : Exécutions ce mois, Taux de succès (sur 30 jours), Dernier export.
  - Colonne Action : bouton « Relancer » avec icône play ; si l'extraction n'est plus active dans le catalogue, le bouton est grisé (`disabled`) avec la mention explicative « Extraction inactive ».
  - Si l'activité est un export CSV, la pastille arbore une couleur spécifique et l'action indique « Relancer » (ou téléchargement).
- **Navigation latérale :**
  - Ajout de l'icône Lucide `history` pour « Journal d'activité » côté admin et « Mon historique » côté métier.

---

## 8. Commits réalisés sur la branche `lot-10-journaux`

1. `1e961c2` : `feat: lecture filtrée et paginée des journaux`
2. `a46341f` : `test: mesure de performance du journal`
3. `998e125` : `feat: journal d'administration (exécutions et connexions)`
4. `48130da` : `feat: historique personnel avec relance`
5. `c2faf0a` : `feat: entrées de menu du journal et de l'historique`
6. `4aa44ad` : `test: journaux, isolation des utilisateurs`

---

## 9. Instructions pour la validation et la fusion (Marc)

### Vérifications manuelles suggérées
1. **Connexion admin :** Se connecter avec `amine.m` -> cliquer sur « Journal d'activité » dans la barre latérale.
   - Consulter l'onglet « Exécutions » et ouvrir la modal des paramètres.
   - Basculer sur l'onglet « Connexions » et tester les filtres de date ou de statut.
2. **Connexion métier :** Se connecter avec `sonia.d` -> cliquer sur « Mon historique ».
   - Vérifier que seules les activités de `sonia.d` sont présentes.
   - Tester l'action « Relancer » : le formulaire d'exécution de la requête s'ouvre avec les paramètres pré-remplis.
   - Cliquer sur « Exporter l'historique » et ouvrir le fichier CSV pour vérifier l'encodage et les colonnes.
3. **Contrôle d'accès :** Avec la session de `sonia.d`, saisir l'URL `http://127.0.0.1:8000/admin/logs` dans la barre d'adresse -> vérifier le code HTTP 403 Forbidden.

### Commandes de fusion (après votre accord)
```powershell
git checkout main
git merge --no-ff lot-10-journaux -m "merge: lot 10 - journaux et historique personnel"
git tag lot-10-valide
git log --oneline -n 12
```
