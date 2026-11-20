# Lot 11 – Finitions UI/UX et adaptation responsive

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-11-finitions-ui` · **Taille** : M · **README** : étape 12 (partie 2)

## Objectif
Compléter ce que les maquettes ne montrent pas (états, erreurs, accessibilité, petits écrans) et faire une **revue de fidélité globale** de tous les écrans.

## Prérequis
Lot 10 validé. Décisions **D16 et D17** validées.

## Documents à lire
`docs/design/design-tokens.md` · `docs/design/ecrans-*.md` · les 12 captures · le rapport du lot 9 (difficultés de l'interface).

## Tâches
1. [ ] **Pages d'erreur** stylées avec le gabarit : 403, 404, 419 (« page expirée »), 429 (« trop de requêtes »), 500, 503, et la page « session expirée » ; messages en français sans détail technique ; lien de retour adapté au rôle.
2. [ ] **États manquants** : vérifier chaque écran (vide, chargement, erreur, succès) et compléter avec les composants existants. Tableau récapitulatif écran × état dans le rapport.
3. [ ] **Messages de retour** (flash) harmonisés : composant unique, durée d'affichage, fermeture au clavier.
4. [ ] **Accessibilité** : étiquettes liées aux champs, `aria-label` sur les boutons-icônes, focus **visible** partout, navigation complète au clavier (menu, modales avec piège de focus et fermeture par Échap, tableaux), `lang="fr"`, titre `<title>` propre à chaque page, `prefers-reduced-motion`.
5. [ ] **Contrastes** (WCAG AA : 4,5:1 pour le petit texte). Calculs de départ faits sur les couleurs mesurées dans les maquettes, **à vérifier** :

| Couleur | Fond | Rapport | Verdict |
|---|---|---|---|
| Texte d'aide `#9DA9B5` | blanc | 2,39 | insuffisant |
| Texte discret `#71849A` (en-têtes de colonnes, sous-titres) | blanc / fond de page | 3,84 / 3,60 | insuffisant |
| Description `#657C94` | blanc | 4,32 | limite |
| Sur-titre vert `#66813E` | blanc / fond de page | 4,40 / 4,13 | limite |
| Pastille Brouillon `#9C661D` sur `#FFF2DB` | – | 4,38 | limite |
| Pastille Inactive `#687686` sur `#EDF0F3` | – | 4,06 | insuffisant |
| Texte courant `#425A73`, lien `#476D13`, menu `#C9D7C1`, pastille Active, puce de code | – | 4,7 à 9,8 | conforme |

   **Ne rien changer sans l'accord de Marc** : toute correction s'écarte des maquettes. L'agent propose pour chaque cas insuffisant une valeur plus sombre la plus proche et en montre l'effet.
6. [ ] **Adaptation responsive** (D17), l'ordinateur restant la référence : ≥ 1280 px identique aux maquettes ; 1024 à 1279 px marges réduites ; **< 1024 px** : menu latéral repliable (bouton de menu), grille du catalogue 3 → 2 → 1 colonnes, tableaux défilants horizontalement dans leur carte, formulaires sur une colonne. Contrôle aux largeurs 1920, 1440, 1280, 1024, 768 et 390 px.
7. [ ] **Éléments communs** : favicon (logo BCPME), format des dates et nombres français partout, composants de chargement unifiés, entrée « Paramètres » toujours masquée (D16) sauf décision contraire.
8. [ ] **Revue de fidélité globale** : checklist écran par écran (S01 à S07, M01 à M04, plus les écrans ajoutés) comparant à la capture ; chaque écart classé « accepté » ou « à corriger », puis corriger.
9. [ ] **Tests** : `Feature/ErrorPagesTest` (403, 404, 419 stylées et sans détail technique), test vérifiant que chaque page rendue contient `lang="fr"` et un `<title>` non vide.

## Fichiers à créer ou modifier
`resources/views/errors/*.blade.php`, `resources/css/*`, `resources/js/*`, `resources/views/layouts/*`, composants Blade existants, `tests/Feature/ErrorPagesTest.php`.

## Commits prévus
1. `feat: pages d'erreur stylées`
2. `feat: états manquants et messages de retour harmonisés`
3. `feat: accessibilité (étiquettes, focus, clavier, titres)`
4. `feat: adaptation responsive du menu, des grilles et des tableaux`
5. `fix: écarts de fidélité relevés à la revue`
6. `test: pages d'erreur et attributs de page`

## Résultat combiné à retourner
- **Matrice écran × état × largeur** avec une capture par case utile (au minimum : tous les écrans à 1920 px et à 390 px, plus les états d'erreur et vides).
- **Tableau des contrastes** vérifié avec les valeurs exactes et les corrections **proposées** (non appliquées sans accord).
- **Rapport d'accessibilité** : résultats de Lighthouse ou d'un outil équivalent par page principale si l'agent peut l'exécuter ; sinon checklist manuelle.
- **Revue de fidélité** : tableau des écarts « accepté / corrigé ».
- Sortie de `vendor\bin\phpunit`.

## Checklist de validation (Marc)
- [ ] Je navigue **sans souris** dans tout le parcours métier (connexion → catalogue → exécution → résultat → export).
- [ ] À 390 px, le menu se replie et rien ne déborde de l'écran.
- [ ] Les pages 403 et 404 sont stylées et sans détail technique.
- [ ] Les corrections de contraste proposées sont décidées (acceptées ou refusées) une par une.
- [ ] Le rendu à 1920 px reste fidèle aux maquettes.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-11-finitions-ui -m "merge: lot 11 - finitions UI/UX et responsive"` · `git tag lot-11-valide`
