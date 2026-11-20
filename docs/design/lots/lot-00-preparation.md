# Lot 0 – Préparation du dépôt, des assets et des règles

**Branche** : `lot-00-preparation` · **Taille** : S · **Qui** : Marc (étapes 1 à 5) puis l'agent (étape 6)

## Objectif
Mettre le dépôt dans l'état attendu avant le premier lot de code : documents, captures, assets, règles de l'agent, décisions validées et README corrigé.

## Prérequis
Socle technique validé (étapes 1 à 7 du README). `git status` propre sur `main`.

## Tâches
**À faire par Marc**
1. [ ] Copier à la racine du projet `AGENTS.md`, et dans `docs/design/` : `design-tokens.md`, `ecrans-admin-technique.md`, `ecrans-utilisateur-metier.md`, `plan-developpement-lots.md`, `decisions.md`, le dossier `lots/` et le dossier `catalogue/` (catalogue-queries-json.md, queries.schema.json, queries.example.json).
2. [ ] Ranger les captures : `docs/design/admin/01-connexion.png … 08-test-resultat.png` et `docs/design/metier/01-accueil-metier.png … 04-resultats.png` (commandes PowerShell données avec les fichiers d'écrans).
3. [ ] **Assets Figma** : exporter le **logo BCPME en SVG** (`resources/svg/logo-bcpme.svg`) ; relever dans Figma le **nom exact de la police** et des graisses, puis déposer les fichiers `.woff2` dans `resources/fonts/` (la police ressemble à Manrope, à confirmer) ; exporter ou choisir les **icônes** (D6).
4. [ ] **Trancher les décisions D1, D2, D4, D5, D6 et D7** dans `decisions.md` (état « Validée »), avec votre encadrant pour D1 et D4.
5. [ ] Créer la branche : `git checkout -b lot-00-preparation`.

**À faire par l'agent**
6. [ ] **Mettre à jour le README** selon la section 3 du plan (14 écarts) : PHP 8.3+, étapes 8 à 14 réécrites d'après les lots, format du catalogue, routes, suppression du `DELETE`, structure des dossiers `docs/design/`. Ne rien changer d'autre.
7. [ ] Vérifier que `.gitignore` exclut `.env`, `vendor/`, `node_modules/`, `storage/app/private/extraction/*.json`, `*.log`, `public/build/`.

## Fichiers à créer ou modifier
`README.md` (modifié) · `.gitignore` (vérifié) · documents et assets déposés par Marc.

## Commits prévus
1. `docs: ajout des règles de l'agent, du plan par lots et du registre des décisions`
2. `docs: ajout des maquettes, de la fiche de styles et de la spécification du catalogue`
3. `chore: ajout du logo, de la police et des icônes`
4. `docs: mise à jour du README selon les décisions validées`

## Résultat combiné à retourner
- Arborescence de `docs/`, `resources/svg/` et `resources/fonts/` (sortie de `tree /f` limitée à ces dossiers).
- **Diff du README** : liste des 14 écarts avec « corrigé » ou « non applicable ».
- Contenu de `.gitignore` pour les lignes vérifiées.
- État du registre : liste des décisions encore « Proposée » ou « À trancher » et le lot qui les attend.

## Checklist de validation (Marc)
- [ ] Les 12 captures s'ouvrent et portent des noms parlants.
- [ ] Les décisions D1, D2, D4, D5, D6 et D7 sont « Validées ».
- [ ] Le README ne contient plus « PHP 8.2+ », ni le format simple de l'étape 9, ni la route `DELETE`.
- [ ] `git status` propre ; aucun secret dans les commits.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-00-preparation -m "merge: lot 0 - préparation"` · `git tag lot-00-valide`
