# Lot 4 – Administration : accueil et liste des extractions

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-04-admin-liste` · **Taille** : S · **README** : étape 10 (partie 1)

## Objectif
Réaliser les écrans **S02** (accueil administrateur) et **S03** (liste des extractions), fidèles aux captures, alimentés par le catalogue réel.

## Prérequis
Lot 3 validé ; `queries.json` installé depuis l'exemple (4 extractions). Décision **D10** (pas de suppression).

## Documents et captures à lire
`docs/design/ecrans-admin-technique.md` (S02, S03) · `docs/design/admin/02-accueil-admin.png` · `docs/design/admin/03-liste-extractions.png`.

## Tâches
1. [ ] Remplacer la page provisoire `/admin` par l'**accueil S02** : carte d'accueil (« Pilotez les extractions publiées. », boutons « Nouvelle extraction » et « Voir les extractions »), carte « Dernières modifications » (les 5 extractions les plus récemment modifiées).
2. [ ] **Liste S03** (`GET /admin/extractions`) : titre, sous-titre, bouton « Nouvelle extraction », recherche par code ou intitulé, filtre de statut (Tous, Active, Brouillon, Inactive), compteur (« 4 extractions »), tableau (code en puce, titre, description, pastille de statut, dernière modification au format « 24 sept. 2026 », lien « Modifier »).
3. [ ] **État vide** et « aucun résultat de recherche » (construits avec les composants, D16).
4. [ ] Les liens « Nouvelle extraction » et « Modifier » pointent vers `/admin/extractions/create` et `/admin/extractions/{code}/edit` (pages provisoires « Bientôt » jusqu'au lot 5). **Aucun bouton de suppression.**
5. [ ] Contrôleurs `AdminHomeController` et `Admin\ExtractionAdminController@index` ; lecture par `CatalogueRepository` ; recherche et filtre **côté serveur** (paramètres `q` et `statut`).
6. [ ] **Tests** `Feature/AdminListTest` : admin voit la liste ; user = 403 ; recherche ; filtre ; liste vide ; ordre des « dernières modifications ».

## Fichiers à créer ou modifier
`app/Http/Controllers/AdminHomeController.php`, `app/Http/Controllers/Admin/ExtractionAdminController.php`, `resources/views/admin/home.blade.php`, `resources/views/admin/extractions/index.blade.php`, `routes/web.php`, `tests/Feature/AdminListTest.php`.

## Commits prévus
1. `feat: accueil administrateur (S02)`
2. `feat: liste des extractions avec recherche et filtre (S03)`
3. `feat: états vides de la liste`
4. `test: accueil et liste d'administration`

## Résultat combiné à retourner
- **Captures de S02 et S03 côte à côte avec les maquettes** et la liste des écarts (même s'ils sont mineurs).
- Captures de la liste filtrée (par statut et par recherche) et de l'état vide.
- Confirmation que les 4 extractions du catalogue apparaissent avec les bons statuts (3 actives, 1 brouillon).
- Sortie des tests.

## Checklist de validation (Marc)
- [ ] S02 et S03 sont fidèles aux captures (menu actif, pastilles, puces de code, dates).
- [ ] La recherche « clients » trouve `CLI_001` ; le filtre « Brouillon » affiche `CRT_001`.
- [ ] `sonia.d` obtient une page 403 sur `/admin/extractions`.
- [ ] Il n'existe aucun bouton ni route de suppression.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-04-admin-liste -m "merge: lot 4 - accueil et liste d'administration"` · `git tag lot-04-valide`
