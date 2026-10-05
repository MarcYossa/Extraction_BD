# Lot 3 – Moteur du catalogue

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-03-moteur-catalogue` · **Taille** : L · **README** : étape 9 (format validé, pas celui du README)

## Objectif
Construire, **sans interface**, tout ce qui lit le catalogue, valide les saisies, compile la requête et l'exécute sur Oracle en lecture seule. À la fin, les 4 extractions du MVP donnent les bons chiffres.

## Prérequis
Lot 2 validé. Décisions **D9 et D12** validées.

## Documents à lire
`docs/design/catalogue/catalogue-queries-json.md` (en entier, c'est la spécification), `queries.schema.json`, `queries.example.json`.

## Tâches
1. [ ] **Paquet de validation** : proposer `opis/json-schema` (D12) après avoir vérifié la compatibilité avec la spécification 2020-12 ; installer seulement avec l'accord de Marc ; lancer `composer audit`.
2. [ ] **`CatalogueRepository`** : `all()`, `find($code)`, `actives()`, `save($extraction)` ; **écriture atomique** (verrou, fichier temporaire puis remplacement), mise à jour de `modifie_le` ; jamais de SQL renvoyé à un utilisateur métier.
3. [ ] **`CatalogueValidator`** : le schéma JSON **et** les 10 règles croisées de la section 9 de la spécification ; messages **en français** indiquant l'extraction, le champ et le problème.
4. [ ] **`ParameterResolver`** : contrôle et conversion des saisies selon le type (section 4 de la spécification) : obligatoire, bornes, longueur, valeur présente dans la liste, « Toutes » → `NULL`, vide → `NULL`, booléen → `1`/`0`, format de date `AAAA-MM-JJ`, `apres_ou_egal`, `defaut` (dont `aujourd_hui`).
5. [ ] **`ListOptionsProvider`** : options fixes ou issues d'une requête (colonnes `valeur` et `libelle`, passée par `SqlGuard`, maximum 500 options).
6. [ ] **`QueryCompiler`** : remplace chaque `:nom` par `?` **hors des textes entre apostrophes**, enveloppe les dates dans `TO_DATE(?, 'YYYY-MM-DD')`, répète les valeurs d'un paramètre utilisé plusieurs fois, retourne le SQL et la liste ordonnée des valeurs liées.
7. [ ] **`ExtractionRunner`** : accepter la requête compilée et une limite de lignes (100 pour un test, `max_rows` sinon) ; **appliquer le délai `EXTRACTION_TIMEOUT_SECONDS`** (investiguer d'abord comment l'obtenir avec `yajra/laravel-oci8` et OCI8, par exemple un délai d'appel sur la connexion ; si aucune solution propre n'existe, **ne pas improviser** : documenter et demander) ; retourner un objet résultat (colonnes, lignes, tronqué, durée, nombre). Garder `SocleTestController` fonctionnel jusqu'au lot 8.
8. [ ] **`ColumnFormatter`** : formats `texte`, `code`, `nombre`, `montant`, `date` (jj/mm/aaaa), `statut`. Pour le CSV : nombres bruts, dates jj/mm/aaaa.
9. [ ] **`ExecutionLogger` enrichi** : utilisateur, code et version de l'extraction, mode (`test`, `execution`, `export_csv`), paramètres, statut, nombre de lignes, tronqué, durée. Aucune donnée métier ni message Oracle brut dans le journal.
10. [ ] **Commandes de développement** : `php artisan catalogue:valider` (valide `queries.json` et affiche les erreurs) et `php artisan extraction:executer {code} --p=nom=valeur …` (affiche colonnes, nombre de lignes, tronqué, durée).
11. [ ] **Catalogue de départ** : décrire dans le rapport comment installer `queries.example.json` comme `storage/app/private/extraction/queries.json` (copie manuelle ou commande `catalogue:installer-exemple`).
12. [ ] **Tests unitaires** (sans Oracle) : `QueryCompilerTest` (dont l'exemple `CLI_001` de la spécification : 6 valeurs dans l'ordre ; `'12:30'` ignoré ; dates), `ParameterResolverTest` (au moins 15 cas), `CatalogueValidatorTest` (exemple valide, 9 définitions invalides, règles croisées), `CatalogueRepositoryTest`, `ColumnFormatterTest`. **Tests Oracle** (groupe `oracle`) : `ExtractionsMvpTest` avec les valeurs de référence ci-dessous.

## Valeurs de référence (jeu de test à 4 agences)
| Extraction et paramètres | Attendu |
|---|---|
| `CPT_001` : agence 1, 2018-01-01 → 2023-12-31, comptes clôturés **exclus** | 192 lignes |
| `CPT_001` : mêmes paramètres, comptes clôturés **inclus** | 200 lignes |
| `CLI_001` : agence 4, statut `ACTIF`, nom `TCHOUPO` | 26 lignes |
| `CLI_001` : toutes agences, tous statuts, nom `N'GUEMA` | 1 ligne |
| `CLI_001` : nom `X' OR '1'='1` | 0 ligne |
| `CRT_001` : agence 1, 2022-01-01 → 2026-09-30, `EN_COURS`, `CREDIT_CONSO` | 8 lignes |
| `CRT_001` : toutes agences, `EN_COURS`, tous types, mêmes dates | 245 lignes |
| `OPE_001` : agence 1, 2025-01-01 → 2026-09-30, montant minimum 0, tous types | `tronque = true`, 10 000 lignes renvoyées (plafond) |

## Fichiers à créer ou modifier
`app/Services/Extraction/{CatalogueRepository,CatalogueValidator,ParameterResolver,ListOptionsProvider,QueryCompiler,ColumnFormatter}.php`, `ExtractionRunner.php`, `ExecutionLogger.php`, `app/Console/Commands/{ValiderCatalogue,ExecuterExtraction}.php`, `config/extraction.php`, `tests/Unit/*`, `tests/Feature/ExtractionsMvpTest.php`, `composer.json` (si D12 validée).

## Commits prévus
1. `chore: ajout du validateur de schéma JSON`
2. `feat: lecture et écriture atomique du catalogue`
3. `feat: validation du catalogue (schéma et règles croisées)`
4. `feat: contrôle et conversion des paramètres`
5. `feat: compilation des paramètres nommés en variables liées`
6. `feat: exécution avec limite de lignes et délai maximal`
7. `feat: mise en forme des colonnes et journal enrichi`
8. `feat: commandes de validation et d'exécution du catalogue`
9. `test: tests unitaires et tests Oracle des 4 extractions`

## Résultat combiné à retourner
- **Tableau « attendu / obtenu »** des 8 lignes de référence, obtenu avec `php artisan extraction:executer` (sorties brutes jointes).
- Sortie de `php artisan catalogue:valider` sur l'exemple, puis sur une copie volontairement cassée (paramètre manquant) montrant le message en français.
- **Note sur le délai maximal** : méthode retenue ou question posée, avec un test qui prouve l'arrêt d'une requête longue (par exemple un délai réduit à 1 s).
- Nombre de tests unitaires et résultat (`vendor\bin\phpunit`) ; résultat séparé des tests Oracle (`--group oracle`).
- Description de l'API publique de chaque classe (signature des méthodes) en 1 page.
- Sortie de `composer audit`.

## Checklist de validation (Marc)
- [ ] Les 8 valeurs de référence correspondent exactement.
- [ ] La requête d'injection `X' OR '1'='1` renvoie 0 ligne.
- [ ] Un `queries.json` volontairement cassé est refusé avec un message clair.
- [ ] Le délai maximal arrête réellement une requête trop longue (ou la question est posée clairement).
- [ ] `SocleTestController` (`/socle-test`) fonctionne toujours.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-03-moteur-catalogue -m "merge: lot 3 - moteur du catalogue"` · `git tag lot-03-valide`
