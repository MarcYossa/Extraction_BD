# Lot 1 – Design system et gabarits

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-01-design-system` · **Taille** : M · **README** : étape 12 (partie 1)

## Objectif
Construire une fois pour toutes les **briques visuelles** (jetons, composants Blade, gabarits) fidèles aux maquettes, avant tout écran. Aucune logique métier.

## Prérequis
Lot 0 validé. Décisions **D5, D6, D7** validées.

## Documents et captures à lire
`docs/design/design-tokens.md` (en entier) · les 12 captures (`admin/` et `metier/`).

## Tâches
1. [ ] Installer et configurer **Vite** (CSS et JS), **Alpine.js** importé dans `resources/js/app.js`. Aucun CDN. Vérifier `npm run build`. (Si npm échoue sur un certificat : Avast intercepte le HTTPS, voir la configuration `cafile` déjà faite pour npm.)
2. [ ] Créer `resources/css/` : `tokens.css` (toutes les variables de la fiche de styles), `base.css` (réinitialisation, typographie, **police auto-hébergée** déposée au lot 0), `layout.css`, `components.css`. **Aucune valeur de couleur en dur en dehors de `tokens.css`.**
3. [ ] Composant d'icônes `x-icon` (fichiers SVG dans `resources/svg/`) : grille, liste, plus, engrenage, déconnexion, loupe, crayon, chevrons (haut, bas, gauche, droite), croix, base de données, lecture, téléchargement, coche, avertissement, poignée de glisser-déposer, œil.
4. [ ] **Composants Blade anonymes** dans `resources/views/components/` : `button` (primaire, secondaire, lien, avec icône), `input`, `select`, `textarea`, `toggle` (Oui/Non), `checkbox`, `form-field` (étiquette, `*` rouge, message d'erreur), `badge-statut` (Active, Brouillon, Inactive, Test réussi), `chip-code`, `card`, `alert` (succès, erreur, avertissement, info), `modal` (Alpine), `data-table`, `avatar`, `page-header` (sur-titre, titre, sous-titre, action à droite), `stat-bandeau` (bandeau « Paramètres utilisés »), `code-editor` (bloc sombre `requete.sql`, sans coloration pour l'instant).
5. [ ] **Gabarits** : `layouts/app.blade.php` (menu latéral de 315 px + barre du haut + contenu ; le menu et les libellés dépendent du rôle, avec un utilisateur **factice** pour l'instant ; l'entrée « Paramètres » est masquée, D16) et `layouts/guest.blade.php` (deux colonnes de la connexion).
6. [ ] Page `/dev/styleguide` (**uniquement** si `APP_ENV=local`, sinon 404) qui affiche tous les composants et leurs états : normal, survol, focus, désactivé, erreur, chargement.
7. [ ] Test `tests/Feature/StyleguideTest.php` : 200 en local, 404 sinon.

## Fichiers à créer ou modifier
`vite.config.js`, `package.json`, `resources/css/*`, `resources/js/app.js`, `resources/svg/*`, `resources/views/components/*`, `resources/views/layouts/*`, `resources/views/dev/styleguide.blade.php`, `routes/web.php`, `tests/Feature/StyleguideTest.php`.

## Commits prévus
1. `chore: installation de Vite et d'Alpine.js`
2. `feat: jetons de design, police et styles de base`
3. `feat: composant d'icônes`
4. `feat: composants de formulaire (champ, liste, interrupteur, case à cocher)`
5. `feat: composants d'affichage (bouton, carte, pastille, tableau, alerte, modale)`
6. `feat: gabarits de page avec menu selon le rôle`
7. `feat: page de démonstration des composants`
8. `test: accès au guide de style`

## Résultat combiné à retourner
- **Inventaire des composants** : tableau « nom | variantes | propriétés | états couverts ».
- **Captures de `/dev/styleguide`** (en entier, en plusieurs captures si nécessaire) et du gabarit avec le menu administrateur, puis avec le menu métier.
- **Preuve « zéro couleur en dur »** : résultat de `grep -rnE "#[0-9A-Fa-f]{3,8}" resources/css resources/views` (seul `tokens.css` doit apparaître).
- Sortie de `npm run build` (taille des fichiers produits) et de `vendor\bin\phpunit`.
- Écarts avec les maquettes (police, rayons, ombres) et valeurs estimées à confirmer dans Figma.

## Checklist de validation (Marc)
- [ ] Menu latéral de 315 px, couleurs, boutons et pastilles identiques aux captures (comparer à l'œil côte à côte).
- [ ] Le menu administrateur et le menu métier ont les bonnes entrées ; « Paramètres » est masquée.
- [ ] `/dev/styleguide` répond 404 avec `APP_ENV=production`.
- [ ] Le grep de couleurs ne montre que `tokens.css`.
- [ ] Aucune adresse `http` externe dans le HTML produit (pas de CDN).

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-01-design-system -m "merge: lot 1 - design system et gabarits"` · `git tag lot-01-valide`
