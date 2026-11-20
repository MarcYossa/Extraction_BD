# Lot 8 – Espace métier : exécution, résultats, export CSV

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-08-metier-execution` · **Taille** : L · **README** : étape 11 (partie 2) · **Écrans** : M03, M04

## Objectif
Permettre à l'utilisateur métier d'**exécuter** une extraction active, de consulter le **résultat** et de l'**exporter en CSV**. À la fin, le socle de test temporaire est supprimé.

## Prérequis
Lot 7 validé. Décisions **D8, D13, D21** validées. Pour la validation, publier `CRT_001` via l'administration (lot 6) si on veut la tester.

## Documents et captures à lire
`docs/design/ecrans-utilisateur-metier.md` (M03, M04) · `docs/design/catalogue/catalogue-queries-json.md` (section 7) · `docs/design/metier/03-executer-extraction.png` · `docs/design/metier/04-resultats.png`.

## Tâches
1. [ ] **M03** `GET /extractions/{code}` (extraction active uniquement, sinon 404) : « ‹ Retour au catalogue », en-tête en trois blocs (code espacé, titre, description), carte « Paramètres d'exécution » (composant partagé du lot 6), encart « Exécution sécurisée », bouton « Exécuter l'extraction ». **Pas** de note « aperçu limité ». Valeurs conservées au retour depuis les résultats.
2. [ ] **Exécution** `POST /extractions/{code}/execute` : validation des paramètres (erreurs sous les champs) → `QueryCompiler` → `ExtractionRunner` (limite `max_rows`, délai maximal) → `ResultStore` → redirection vers `GET /extractions/{code}/resultats/{jeton}`.
3. [ ] **Attente** : dès l'envoi, bouton désactivé « Exécution en cours… » et voile léger (Alpine), protection contre la double soumission. **Délai dépassé** : message explicite (« L'extraction a dépassé le délai de N secondes. Affinez vos critères. ») ; **erreur Oracle** : message générique, détail dans `laravel.log` ; les deux sont journalisés.
4. [ ] **M04 Résultats** : « ‹ Retour aux paramètres », sur-titre « EXTRACTION EXÉCUTÉE », titre, « ✓ Exécution terminée avec succès », bouton **« Exporter en CSV »**, bandeau « PARAMÈTRES UTILISÉS », carte « Résultats » avec le compteur (« 1 248 lignes retournées » ; **plafond atteint** (D21) : « 10 000 lignes (plafond atteint) » et bandeau d'avertissement « Résultat limité : affinez vos critères »), tableau paginé côté client (50 lignes) avec les formats de `colonnes` ; état « Aucun résultat ».
5. [ ] **Export CSV** `GET /extractions/{code}/csv/{jeton}` à partir du `ResultStore` (aucune seconde requête sur Oracle) : service **`CsvExporter`** extrait de `SocleTestController` (BOM UTF-8, séparateur `;`, en-têtes = libellés des colonnes, nombres bruts, dates jj/mm/aaaa, **neutralisation des formules**), nom de fichier `CPT_001_AAAAMMJJ_HHMM.csv`. Jeton inconnu ou expiré : page « Ce résultat a expiré, relancez l'extraction » avec lien vers M03.
6. [ ] **Journal** : modes `execution` et `export_csv`, avec l'identifiant réel de l'utilisateur, le code, la version, les paramètres, le nombre de lignes, « tronqué », la durée.
7. [ ] **Suppression du socle temporaire** : `SocleTestController`, vue `socle-test`, routes `/socle-test*` et leur test ; vérifier avec `php artisan route:list` ; mettre à jour le README et `AGENTS.md` (la mention du socle temporaire).
8. [ ] **Tests** : `Feature/ExtractionExecutionTest` (valeurs de référence du groupe `oracle`, validation des paramètres, extraction non active = 404, jeton d'un autre utilisateur = 404, jeton expiré, délai dépassé simulé) ; `Feature/CsvExportTest` (BOM, séparateur, en-têtes, une cellule `=SUM(1)` devient `'=SUM(1)`, accents, nombre de lignes égal à celui affiché) ; `Unit/CsvExporterTest`.

## Fichiers à créer ou modifier
`app/Http/Controllers/Metier/ExtractionController.php`, `app/Services/Extraction/CsvExporter.php`, `resources/views/metier/extractions/{show,resultats,expire}.blade.php`, `routes/web.php`, `README.md`, `AGENTS.md`, suppression du socle temporaire, tests.

## Commits prévus
1. `feat: page des paramètres d'exécution (M03)`
2. `feat: exécution avec attente, délai maximal et erreurs`
3. `feat: page des résultats (M04)`
4. `feat: export CSV depuis le résultat conservé`
5. `feat: journal des exécutions et exports avec l'utilisateur réel`
6. `chore: suppression du socle de test temporaire`
7. `test: exécution métier et export CSV`

## Résultat combiné à retourner
- **Parcours complet de `sonia.d`** : catalogue → `CPT_001` → paramètres → résultat (192 lignes) → export CSV ; captures de **M03 et M04 côte à côte avec les maquettes**.
- Captures des états : erreur de validation, attente, plafond atteint (avec `EXTRACTION_MAX_ROWS=10` temporairement), résultat vide, délai dépassé, jeton expiré.
- **CSV** : 5 premières lignes, nombre total de lignes, et octets de début du fichier prouvant le BOM (`EF BB BF`).
- **Extrait du journal** (modes `execution` et `export_csv`, utilisateur `sonia.d`).
- Preuve de la suppression du socle : `php artisan route:list` sans `socle-test`.
- Sortie de `vendor\bin\phpunit` et des tests `oracle`.

## Checklist de validation (Marc)
- [ ] Valeurs de référence : `CPT_001` (agence 1, 2018 → 2023, clôturés exclus) = **192** ; `CLI_001` (agence 4, `ACTIF`, `TCHOUPO`) = **26** ; `OPE_001` (agence 1, 2025-01-01 → 2026-09-30, montant minimum 0) = plafond atteint, **10 000** lignes.
- [ ] Le CSV s'ouvre dans Excel avec les colonnes séparées et les accents corrects.
- [ ] `amine.m` (autre utilisateur) ne peut pas ouvrir l'adresse d'un résultat de `sonia.d`.
- [ ] M03 et M04 sont fidèles aux captures.
- [ ] `/socle-test` n'existe plus.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-08-metier-execution -m "merge: lot 8 - exécution, résultats et export CSV"` · `git tag lot-08-valide`
