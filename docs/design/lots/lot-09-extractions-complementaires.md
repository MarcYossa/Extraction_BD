# Lot 9 – Extractions complémentaires B03, B06, B07, B08

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-09-extractions-complementaires` · **Taille** : S · **README** : étape 9 (suite)

## Objectif
Compléter le catalogue avec les besoins B03, B06, B07 et B08 **en les créant par l'interface d'administration** (ce qui sert aussi de recette des lots 5 et 6), puis vérifier leurs résultats contre du SQL direct sur Oracle.

## Prérequis
Lot 8 validé. Le catalogue contient `CPT_001`, `CLI_001`, `OPE_001` (actives) et `CRT_001` (brouillon, à publier pour les tests, puis à repasser en brouillon avant la production car le domaine des crédits n'est pas confirmé).

## Documents à lire
`docs/design/catalogue/catalogue-queries-json.md` (sections 3 à 5 et 9) · `database/oracle-test/06_requetes_mvp.sql` (requêtes de comparaison).

## Règle de ce lot
Les 4 extractions sont créées **par le formulaire d'administration** (navigateur de l'agent si possible ; sinon l'agent fournit le texte à saisir et Marc les crée). **Ne pas modifier `queries.json` à la main.** Chaque extraction est vérifiée, testée, puis publiée.

## Les 4 extractions

### `OPE_002` – Opérations d'un compte sur une période (B03)
- **Description** : Opérations d'un compte bancaire sur une période.
- **Paramètres** : `num_compte` (texte, obligatoire, 20 caractères max, libellé « Numéro de compte »), `date_debut` (date, obligatoire), `date_fin` (date, obligatoire, défaut `aujourd_hui`, après ou égale à `date_debut`).
- **SQL** :
```sql
SELECT c.num_compte AS num_compte, o.date_operation AS date_operation, o.type_operation AS type_operation,
       o.montant AS montant, o.libelle AS libelle, o.canal AS canal
FROM amp_test.operation o
JOIN amp_test.compte c ON c.compte_id = o.compte_id
WHERE c.num_compte = :num_compte
  AND o.date_operation BETWEEN :date_debut AND :date_fin
ORDER BY o.date_operation, o.operation_id
```
- **Colonnes** : `num_compte` (code, « N° compte »), `date_operation` (date, « Date »), `type_operation` (texte, « Sens »), `montant` (montant, « Montant (XAF) »), `libelle` (texte, « Libellé »), `canal` (texte, « Canal »).

### `CPT_002` – Comptes sans mouvement depuis une date (B06)
- **Description** : Comptes ouverts sans aucune opération depuis la date indiquée. La maquette parle d'un « nombre » : on liste les comptes, le compteur « N lignes retournées » donne le nombre.
- **Paramètres** : `agence` (liste issue de la base `SELECT agence_id AS valeur, nom_agence AS libelle FROM amp_test.agence ORDER BY nom_agence`, obligatoire, option « — Toutes les agences — »), `date_reference` (date, obligatoire, libellé « Aucun mouvement depuis le »).
- **SQL** :
```sql
SELECT a.nom_agence AS agence, c.num_compte AS num_compte, c.type_compte AS type_compte,
       c.date_ouverture AS date_ouverture, c.solde AS solde, c.devise AS devise
FROM amp_test.compte c
JOIN amp_test.agence a ON a.agence_id = c.agence_id
WHERE c.statut = 'OUVERT'
  AND (:agence IS NULL OR c.agence_id = :agence)
  AND NOT EXISTS (SELECT 1 FROM amp_test.operation o
                  WHERE o.compte_id = c.compte_id
                    AND o.date_operation >= :date_reference)
ORDER BY a.nom_agence, c.num_compte
```
- **Colonnes** : `agence` (texte), `num_compte` (code, « N° compte »), `type_compte` (texte, « Type »), `date_ouverture` (date, « Date d'ouverture »), `solde` (montant, « Solde »), `devise` (texte, « Devise »).

### `CLI_002` – Clients PME actifs par agence (B07)
- **Description** : Clients du segment PME au statut actif, par agence.
- **Paramètres** : `agence` (liste issue de la base, obligatoire, option « — Toutes les agences — »).
- **SQL** :
```sql
SELECT cl.num_client AS num_client, TRIM(cl.prenom || ' ' || cl.nom) AS nom_raison_sociale,
       cl.segment_client AS segment_client, a.nom_agence AS agence, cl.statut AS statut
FROM amp_test.client cl
JOIN amp_test.agence a ON a.agence_id = cl.agence_id
WHERE cl.segment_client = 'PME'
  AND cl.statut = 'ACTIF'
  AND (:agence IS NULL OR cl.agence_id = :agence)
ORDER BY a.nom_agence, cl.nom, cl.num_client
```
- **Colonnes** : `num_client` (code, « N° client »), `nom_raison_sociale` (texte, « Nom / raison sociale »), `segment_client` (texte, « Segment »), `agence` (texte), `statut` (statut).

### `CPT_003` – Soldes par agence, type de compte et devise (B08)
- **Description** : Nombre de comptes et solde total par agence, type de compte et devise. On regroupe par devise : on n'additionne **jamais** des XAF et des EUR.
- **Paramètres** : `agence` (liste issue de la base, obligatoire, option « — Toutes les agences — »).
- **SQL** :
```sql
SELECT a.nom_agence AS agence, c.type_compte AS type_compte, c.devise AS devise,
       COUNT(*) AS nb_comptes, SUM(c.solde) AS solde_total
FROM amp_test.compte c
JOIN amp_test.agence a ON a.agence_id = c.agence_id
WHERE (:agence IS NULL OR c.agence_id = :agence)
GROUP BY a.nom_agence, c.type_compte, c.devise
ORDER BY a.nom_agence, c.type_compte, c.devise
```
- **Colonnes** : `agence` (texte), `type_compte` (texte, « Type »), `devise` (texte, « Devise »), `nb_comptes` (nombre, « Nombre de comptes »), `solde_total` (montant, « Solde total »).

## Tâches
1. [ ] Créer, vérifier, tester et **publier** les 4 extractions par l'interface ; noter tout écart de comportement de l'interface (formulaire, colonnes, listes issues de la base).
2. [ ] Lancer `php artisan catalogue:valider` : zéro erreur.
3. [ ] Comparer chaque résultat avec du **SQL direct** équivalent (sans passer par l'application), via SQL\*Plus avec `APP_RO`, et consigner les deux résultats.
4. [ ] Publier aussi `CRT_001` pour la recette (à repasser en brouillon avant la production, note dans le rapport).
5. [ ] Exporter le catalogue final en `docs/design/catalogue/queries.recette.json` (sans secret) ; mettre à jour la liste des extractions dans le README.
6. [ ] **Tests Oracle** (groupe `oracle`) : `ExtractionsComplementairesTest` avec les valeurs de référence ci-dessous.

## Valeurs de référence (jeu de test à 4 agences)
| Extraction et paramètres | Attendu |
|---|---|
| `OPE_002` : `CM00000000000001`, 2025-01-01 → 2026-12-31 | **125** opérations |
| `OPE_002` : un numéro de compte inexistant | 0 ligne |
| `CPT_002` : agence 1, aucun mouvement depuis 2025-07-01 | **18** comptes |
| `CPT_002` : toutes agences, même date | **81** comptes |
| `CLI_002` : agence 1 | **19** clients |
| `CLI_002` : toutes agences | **80** clients |
| `CPT_003` : agence 1 | **4 lignes** : COURANT/XAF 120 · EPARGNE/XAF 40 · COURANT/EUR 30 · EPARGNE/EUR 10 (total 200 comptes) |

## Fichiers à créer ou modifier
`docs/design/catalogue/queries.recette.json`, `README.md`, `tests/Feature/ExtractionsComplementairesTest.php`. Aucun code applicatif attendu : si l'interface bloque, l'agent le signale dans le rapport au lieu de contourner.

## Commits prévus
1. `feat: extraction OPE_002, opérations d'un compte (B03)`
2. `feat: extraction CPT_002, comptes sans mouvement (B06)`
3. `feat: extraction CLI_002, clients PME actifs (B07)`
4. `feat: extraction CPT_003, soldes agrégés (B08)`
5. `docs: catalogue de recette et README`
6. `test: valeurs de référence des extractions complémentaires`

## Résultat combiné à retourner
- **Tableau « attendu / application / SQL direct »** pour les 7 valeurs de référence, avec les requêtes SQL directes utilisées.
- Captures de la liste S03 (8 extractions avec leurs statuts) et du catalogue métier (M02).
- Un résultat complet de chaque extraction (M04) : une capture par extraction.
- Sortie de `php artisan catalogue:valider` et des tests `oracle`.
- **Liste des difficultés rencontrées dans l'interface** (par exemple : liste issue de la base, option « Toutes », colonnes calculées) pour correction éventuelle au lot 11.

## Checklist de validation (Marc)
- [ ] Les valeurs de l'application, du SQL direct et du tableau de référence sont **identiques**.
- [ ] `CPT_003` ne mélange jamais XAF et EUR dans un même total.
- [ ] Les 4 nouvelles extractions apparaissent dans le catalogue métier.
- [ ] Le fichier `queries.json` n'a pas été modifié à la main (historique des versions cohérent).

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-09-extractions-complementaires -m "merge: lot 9 - extractions complémentaires"` · `git tag lot-09-valide`
