# Lot 7 – Espace métier : accueil et catalogue

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-07-metier-catalogue` · **Taille** : S · **README** : étape 11 (partie 1) · **Écrans** : M01, M02

## Objectif
Réaliser l'**accueil** et le **catalogue** de l'utilisateur métier, qui ne montrent que les extractions **actives**.

## Prérequis
Lot 6 validé. Décision **D14** (séparation des espaces) validée.

## Documents et captures à lire
`docs/design/ecrans-utilisateur-metier.md` (M01, M02) · `docs/design/metier/01-accueil-metier.png` · `docs/design/metier/02-catalogue.png`.

## Tâches
1. [ ] **Routes et accès** : `GET /dashboard` (M01), `GET /catalogue` (M02), réservées au rôle `user` ; l'administrateur y est renvoyé vers `/admin` (D14).
2. [ ] **Composant `x-extraction-card`** : puce de code, bouton rond ›, titre, description limitée à 2 lignes, bouton « Utiliser l'extraction » ; la carte entière mène à `/extractions/{code}` ; grille de **3 colonnes**, cartes ≈ 475 × 280 px, espace ≈ 20 px.
3. [ ] **M01 Accueil** : « Bonjour, {prénom} », carte d'accueil (« Trouvez l'extraction dont vous avez besoin. », bouton « Parcourir le catalogue › »), section « Extractions disponibles » avec lien « Voir le catalogue › », **3 cartes** : les extractions actives **publiées le plus récemment**.
4. [ ] **M02 Catalogue** : « Choisir une extraction », sous-titre, recherche (« Rechercher par code ou intitulé ») avec **filtrage immédiat** (Alpine, insensible à la casse et aux accents, sur code, titre et description), grille de toutes les extractions actives.
5. [ ] **États** (D16) : aucune extraction publiée ; aucun résultat de recherche ; descriptions très longues ; 20 extractions ou plus.
6. [ ] `GET /extractions/{code}` provisoire : 404 si l'extraction n'est pas active ; page « Bientôt disponible » sinon (réalisée au lot 8).
7. [ ] **Tests** `Feature/MetierCatalogueTest` : seules les actives sont listées ; brouillon et inactive absentes ; l'adresse directe d'un brouillon = 404 ; l'administrateur est redirigé vers `/admin` ; invité renvoyé sur `/login` ; ordre par date de publication ; présence de la recherche.

## Fichiers à créer ou modifier
`app/Http/Controllers/Metier/{DashboardController,CatalogueController,ExtractionController}.php`, `resources/views/metier/{dashboard,catalogue}.blade.php`, `resources/views/components/extraction-card.blade.php`, `routes/web.php`, `tests/Feature/MetierCatalogueTest.php`.

## Commits prévus
1. `feat: carte d'extraction et grille du catalogue`
2. `feat: accueil de l'espace métier (M01)`
3. `feat: catalogue avec recherche immédiate (M02)`
4. `feat: états vides et accès réservé au rôle métier`
5. `test: accueil et catalogue métier`

## Résultat combiné à retourner
- **Captures de M01 et M02 côte à côte avec les maquettes** et les écarts.
- **Preuve de masquage** : captures du catalogue avant et après la désactivation d'une extraction (elle disparaît), et d'un brouillon qui n'apparaît pas.
- Captures des états (aucune extraction, recherche sans résultat).
- Sortie des tests.

## Checklist de validation (Marc)
- [ ] Avec le catalogue d'exemple non modifié, `sonia.d` voit `CPT_001`, `CLI_001` et `OPE_001` (dans cet ordre, du plus récemment publié au plus ancien) ; `CRT_001` (brouillon) est absente.
- [ ] La recherche « credit » ou « clients » filtre immédiatement.
- [ ] `amine.m` ouvrant `/dashboard` est renvoyé vers `/admin`.
- [ ] M01 et M02 sont fidèles aux captures (grille de 3 colonnes, boutons ronds, puces).

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-07-metier-catalogue -m "merge: lot 7 - accueil et catalogue métier"` · `git tag lot-07-valide`
