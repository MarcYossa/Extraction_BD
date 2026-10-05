# Plan de développement par lots – Extraction BD

> **Objet** : découper ce qui reste à développer (README, étapes 8 à 14) en **14 lots** (0 à 13) à exécuter avec Antigravity. Chaque lot a sa branche Git, ses commits par étape, un **résultat combiné à retourner** et une **porte de validation** avant le lot suivant.
> **Fichiers** : un fichier par lot dans `docs/design/lots/` (à donner à l'agent avec les captures indiquées). Règles de l'agent : `AGENTS.md`. Choix techniques : `decisions.md`.

## 1. Cycle d'un lot

1. **Vous** vérifiez les décisions du lot dans `decisions.md` (état « Validée ») et que `git status` est propre sur `main`.
2. **Vous** ouvrez Antigravity sur le dossier du projet et collez le contenu du fichier du lot, en joignant les captures demandées.
3. **L'agent** présente un **plan de 10 lignes maximum**. Vous répondez « OK » ou vous corrigez.
4. **L'agent** crée la branche du lot, implémente par étapes et fait **un commit par étape**.
5. **L'agent** remet son **rapport de fin de lot** (modèle dans `AGENTS.md`).
6. **Vous** parcourez la **checklist de validation** du fichier du lot. Un seul point non conforme : retour à l'agent avec la liste des corrections.
7. **Vous** fusionnez et étiquetez (commandes de la section 4), puis passez au lot suivant.

## 2. Vue d'ensemble

Taille relative : **S** petite, **M** moyenne, **L** grosse (à découper en plusieurs sessions si l'agent perd le fil).

| Lot | Titre | Étape du README | Écrans | Taille | Dépend de |
|---|---|---|---|---|---|
| 0 | Préparation du dépôt, des assets et des règles | – | – | S | – |
| 1 | Design system et gabarits | 12 (partie 1) | tous (socle) | M | 0 |
| 2 | Authentification et rôles | 8 | S01 | M | 1 |
| 3 | Moteur du catalogue | 9 | – (services) | L | 2 |
| 4 | Administration : accueil et liste | 10 (partie 1) | S02, S03 | S | 3 |
| 5 | Administration : création et modification | 10 (partie 2) | S04 | L | 4 |
| 6 | Administration : test, publication, désactivation | 10 (partie 3) | S05, S06, S07 | L | 5 |
| 7 | Espace métier : accueil et catalogue | 11 (partie 1) | M01, M02 | S | 6 |
| 8 | Espace métier : exécution, résultats, export CSV | 11 (partie 2) | M03, M04 | L | 7 |
| 9 | Extractions complémentaires B03, B06, B07, B08 | 9 | (via l'interface) | S | 8 |
| 10 | Journaux d'exécution et historique personnel | 10 et 11 | à concevoir | M | 9 |
| 11 | Finitions UI/UX et adaptation responsive | 12 (partie 2) | tous | M | 10 |
| 12 | Tests complets, sécurité, documentation | 13 | – | M | 11 |
| 13 | Préparation à la production (Amplitude) | 14 | – | M | 12 |

```
0 → 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12 → 13
        (les lots 4 à 8 réutilisent les composants du lot 1 et le moteur du lot 3)
```

**Pourquoi cet ordre**
- Le **design system avant les écrans** (lot 1) évite de recoder les mêmes boutons et tableaux dix fois, et garantit la fidélité aux maquettes.
- Le **moteur du catalogue** (lot 3) est testable sans interface : on valide d'abord que les 4 extractions donnent les bons chiffres sur Oracle.
- L'**administration avant le métier** : sans extraction publiée, l'espace métier est vide.
- Les **tests sont écrits dans chaque lot** (pas seulement au lot 12, contrairement au README) : un défaut est détecté quand il apparaît.

## 3. Écarts entre le README et les décisions validées (à corriger au lot 0)

| # | README | Décision retenue | Lot |
|---|---|---|---|
| 1 | PHP 8.2+ | **PHP 8.3 minimum** (exigence de Laravel 13) | 0 |
| 2 | Instant Client 21.x obligatoire | Le client de l'installation Oracle XE suffit en développement ; un client compatible sera à prévoir en production | 0 |
| 3 | Étape 8 : migration `users` (base non précisée), `DB_CONNECTION=oracle` | Comptes dans **SQLite** (connexion `app`, par défaut) ; Oracle reste en lecture seule (D1) | 2 |
| 4 | Étape 9 : format simple (`id: B01`, `sql` avec `?`, types `integer`/`select`) | **Format validé** du catalogue (codes `CPT_001`, statuts, paramètres nommés, colonnes) | 3 |
| 5 | « Le MVP couvre 8 besoins » | **4 extractions d'abord** (B01, B02, B04, B05), puis B03, B06, B07, B08 au lot 9 | 3, 9 |
| 6 | Étape 10 : route `DELETE` | **Pas de suppression**, désactivation seulement (D10) | 4 |
| 7 | `/mes-extractions`, `/admin/logs` | Pas de maquette : construits au lot 10 (D16) | 10 |
| 8 | Étape 11 : exécution par `POST` puis export `GET` (re-exécution) | Exécution **unique**, résultat conservé 15 min, export depuis ce résultat (D13) | 6, 8 |
| 9 | Étape 12 : « mobile first », SEO | **Ordinateur d'abord** (maquettes) ; pas de SEO pour un intranet, mais favicon (D17) | 11 |
| 10 | Étape 12 : pagination côté client | Adoptée (D8) | 6 |
| 11 | Étape 13 : tous les tests à la fin, `--coverage` | Tests **dans chaque lot** ; couverture chiffrée seulement avec PCOV (D18) | tous, 12 |
| 12 | Étape 14 : timeout Oracle en fin de projet | **Appliqué dès le lot 3** (le paramètre existe déjà et n'est pas utilisé) | 3 |
| 13 | `DB_SCHEMA` externalisé | Décision au lot 13 (D20) | 13 |
| 14 | Prérequis `npm run dev` sans choix CSS | CSS natif + Vite + Alpine, sans CDN (D5) | 1 |

## 4. Règles Git

**Début de lot** (fait par l'agent, depuis `main` propre) :
```powershell
git status                      # doit être propre
git checkout -b lot-NN-nom      # le nom exact est dans le fichier du lot
```

**Pendant le lot** : un commit par étape, message en français préfixé (`feat:`, `fix:`, `docs:`, `chore:`, `test:`). Les messages proposés sont dans chaque fichier de lot.

**Fin de lot, après votre validation** (fait par vous) :
```powershell
git checkout main
git merge --no-ff lot-NN-nom -m "merge: lot N - titre du lot"
git tag lot-NN-valide
git log --oneline -n 12         # contrôle
```

**Si le lot est refusé** : restez sur la branche du lot, l'agent corrige et ajoute des commits `fix:`. Ne fusionnez qu'une fois validé.

**Retour arrière** (si un lot fusionné pose problème) : `git revert -m 1 <hash du merge>` (ne jamais réécrire l'historique).

## 5. Porte de validation (valable pour tous les lots)

Avant de fusionner, vous vérifiez :
1. Le **rapport de fin de lot** est complet (9 rubriques) et ses chiffres sont cohérents avec ce que vous voyez.
2. `vendor\bin\phpunit` est vert chez vous (pas seulement dans le rapport) ; les tests Oracle aussi si le lot en comporte.
3. Les **écrans du lot** ressemblent aux captures ; les écarts listés par l'agent sont acceptables.
4. `git status` est propre ; aucun fichier `.env`, `vendor`, `node_modules` ni JSON de `storage` dans les commits (`git show --stat`).
5. Les points de la **checklist propre au lot** sont tous cochés.
6. `composer audit` ne signale rien de nouveau.

## 6. Ce qui n'est pas confié à l'agent

- Les décisions de `decisions.md` (c'est vous, avec votre encadrant).
- Le contact avec la **DOSI** (schéma d'Amplitude, délai, HTTPS, recette) : lot 13.
- La **fusion** dans `main` et les étiquettes.
- La relecture finale de sécurité (l'agent prépare la checklist, vous la signez).
