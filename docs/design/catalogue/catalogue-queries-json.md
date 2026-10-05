# Le catalogue des extractions (`queries.json`)

> **Emplacements** : le fichier utilisé par l'application est `storage/app/private/extraction/queries.json` (non versionné, jamais accessible depuis le navigateur). Ce document, le schéma `queries.schema.json` et l'exemple `queries.example.json` se rangent dans `docs/design/catalogue/`.
> **Statut** : proposition de conception, à valider (voir la section 10). L'exemple a été vérifié automatiquement (schéma JSON, cohérence des paramètres, garde-fou SQL) ; il n'a pas pu être exécuté sur Oracle.

## 1. À quoi sert ce fichier ?

Imaginez la **carte d'un restaurant**. Chaque plat est une **extraction** : un nom, une description, et la liste des choix que le client doit faire (cuisson, accompagnement…). Le client ne va jamais en cuisine : il choisit un plat, indique ses préférences, et la cuisine prépare.

- **L'administrateur technique** écrit la carte : il crée les extractions (écrans S03 et S04).
- **L'utilisateur métier** lit la carte (le « Catalogue »), choisit une extraction et renseigne ses **paramètres** (écrans M02 et M03).
- **Le moteur de l'application** est la cuisine : il lit la carte, vérifie les choix, exécute la requête SQL sur Oracle en lecture seule et affiche le résultat.

`queries.json` est donc **la carte**. Tout ce que l'interface affiche (cartes du catalogue, formulaires de paramètres, titres de colonnes) vient de ce fichier, et la requête SQL qui s'exécute aussi.

### Lire un fichier JSON en 30 secondes
- `{ ... }` est un **objet** : une fiche avec des champs `"nom": valeur`.
- `[ ... ]` est une **liste** : plusieurs éléments, séparés par des virgules.
- Les valeurs sont du texte (`"entre guillemets"`), des nombres (`3`), `true` / `false`, ou `null` (« rien »).
- Un JSON **n'accepte pas de commentaires** et une virgule oubliée le rend invalide. C'est une raison de plus pour que l'application écrive ce fichier elle-même (écran S04) plutôt que de le modifier à la main.

## 2. Vue d'ensemble de la structure

```
queries.json
├─ schema_version        version du format du fichier (1)
├─ modifie_le            date de la dernière écriture du fichier
└─ extractions[]         la liste des extractions
   └─ une extraction
      ├─ code, titre, description        ← ce que l'on voit dans les listes et les cartes
      ├─ statut, version                 ← vie de l'extraction (brouillon, active, inactive)
      ├─ requete_sql[]                   ← la requête, écrite ligne par ligne
      ├─ parametres[]                    ← les champs demandés à l'utilisateur
      │  └─ un paramètre : nom, libelle, type, obligatoire, (defaut, source, …)
      ├─ colonnes[]                      ← comment présenter le résultat
      │  └─ une colonne : cle, libelle, format
      ├─ cree_le / cree_par / modifie_le / modifie_par / publie_le / publie_par
      └─ dernier_test                    ← résultat du dernier test (autorise la publication)
```

## 3. Les champs d'une extraction

| Champ | Exemple | Rôle | Règles |
|---|---|---|---|
| `code` | `CPT_001` | Identifiant unique et **définitif**. Affiché en puce monospace. | 3 lettres majuscules + `_` + 3 chiffres. Le préfixe indique le domaine : CLI clients, CPT comptes, OPE opérations, CRT crédits, RIS risques (OPE est ajouté par moi, à valider). |
| `titre` | Comptes ouverts par agence et période | Nom affiché partout. | 3 à 100 caractères. |
| `description` | Comptes ouverts sur une période… | Texte des cartes et du tableau d'administration. | 3 à 300 caractères. |
| `statut` | `active` | Position dans le cycle de vie (section 6). | `brouillon`, `active` ou `inactive`. L'écran affiche « Brouillon », « Active », « Inactive ». |
| `version` | `3` | Numéro de version de la définition. | Entier qui **augmente à chaque enregistrement d'une modification**. Le journal des exécutions le retient : on sait toujours quelle requête a produit quel résultat. |
| `requete_sql` | liste de lignes | La requête de lecture. | `SELECT` uniquement (voir section 9). Une ligne SQL par élément de la liste, pour que le fichier reste lisible. Le moteur les assemble. |
| `parametres` | liste | Les champs du formulaire. | Voir section 4. Peut être vide (extraction sans paramètre). |
| `colonnes` | liste | Titres et formats des colonnes du résultat. | Voir section 5. |
| `cree_le`, `cree_par` | `2026-09-10T10:05:00+01:00`, `amine.m` | Qui a créé l'extraction, quand. | Date au format international ISO 8601 avec le fuseau (+01:00 pour le Cameroun). L'identifiant a la forme `prenom.nom`, comme à la connexion. |
| `modifie_le`, `modifie_par` | idem | Dernière modification. | `modifie_le` alimente la colonne « Dernière modification » (affichée « 24 sept. 2026 »). |
| `publie_le`, `publie_par` | idem ou `null` | Dernière publication. | `null` tant qu'elle n'a jamais été publiée. |
| `dernier_test` | objet ou `null` | Résultat du dernier test (version testée, date, auteur, `reussi` ou `echec`, nombre de lignes, durée). | **Condition de publication** : le test doit avoir réussi **pour la version courante**. |

## 4. Les paramètres

Un paramètre est un champ que l'utilisateur remplit avant d'exécuter. Il apparaît à **deux endroits** : dans la requête, sous la forme `:nom`, et dans la liste `parametres`.

| Champ | Rôle |
|---|---|
| `nom` | Identifiant technique (minuscules, chiffres et `_`, 30 caractères max). **Doit être identique au `:nom` écrit dans la requête.** |
| `libelle` | Texte affiché au-dessus du champ (« Date de début »). |
| `type` | `texte`, `nombre`, `date`, `liste` ou `booleen` (voir ci-dessous). |
| `obligatoire` | `true` : l'utilisateur doit faire un choix. Affiche le `*` rouge. |
| `defaut` | *(facultatif)* valeur préremplie. Pour une date, `"aujourd_hui"` est accepté. |

**Les cinq types**

| Type | Ce que voit l'utilisateur | Options propres | Valeur envoyée à Oracle |
|---|---|---|---|
| `texte` | Champ de saisie | `longueur_max` | Le texte (vide devient `NULL`) |
| `nombre` | Champ numérique | `min`, `max` | Le nombre |
| `date` | Champ date | `apres_ou_egal` (nom d'un autre paramètre date, pour « fin ≥ début ») | La date, automatiquement enveloppée dans `TO_DATE(?, 'YYYY-MM-DD')` |
| `liste` | Liste déroulante | `source` (obligatoire), `option_toutes` | La valeur choisie (texte) |
| `booleen` | Interrupteur Oui / Non | – | `1` ou `0` |

**Les listes déroulantes** ont une `source` de deux sortes :
- `{"type": "fixe", "options": [{"valeur": "ACTIF", "libelle": "Actif"}, …]}` : valeurs écrites dans le catalogue (statuts, types).
- `{"type": "requete", "requete_sql": [...]}` : valeurs lues en base (par exemple les agences). La requête doit renvoyer exactement deux colonnes : **`valeur`** et **`libelle`**.

**L'option « Toutes… »** : si `option_toutes` est renseigné (par exemple `"— Toutes les agences —"`), la liste propose en plus ce choix, qui envoie `NULL`. C'est ce qui rend possible le motif SQL `(:agence IS NULL OR c.agence_id = :agence)` : « si aucune agence n'est choisie, on ne filtre pas ». Un paramètre peut donc être *obligatoire* (l'utilisateur doit choisir) tout en acceptant « Toutes ».

## 5. Les colonnes du résultat

La requête SQL renvoie des colonnes aux noms techniques (`num_client`). L'écran de résultat montre des titres lisibles et une présentation adaptée (« N° CLIENT » dans une puce, « STATUT » avec un point vert). Cette correspondance est décrite par `colonnes`.

| Champ | Rôle |
|---|---|
| `cle` | Nom de la colonne **telle qu'écrite dans le SELECT** (l'alias après `AS`), en minuscules. |
| `libelle` | Titre affiché (la maquette le met en majuscules par le style). |
| `format` | `texte` · `code` (puce monospace) · `nombre` (séparateur de milliers) · `montant` (idem, aligné à droite) · `date` (jj/mm/aaaa) · `statut` (point coloré). |

**Les colonnes sont facultatives** dans le fichier : si elles manquent, le moteur affiche les noms de colonnes de la requête, sans mise en forme particulière. Elles contrôlent aussi les titres du **CSV exporté**.

## 6. Cycle de vie d'une extraction

```
        (création)
            │
            ▼
       ┌─────────┐   test réussi de la version courante   ┌────────┐
       │brouillon│ ─────────────────────────────────────▶ │ active │ ◀─┐
       └─────────┘          (bouton « Publier »)          └────────┘   │ réactiver
            ▲                                                │         │ (nouveau test
            │  modification d'une extraction active          │ désactiver  si modifiée)
            └────────────────────────────────────────────────┤         │
                                                             ▼         │
                                                         ┌─────────┐   │
                                                         │inactive │ ──┘
                                                         └─────────┘
```

- **brouillon** : en cours d'écriture. Visible uniquement par l'administrateur.
- **active** : publiée. **Seules les extractions actives apparaissent dans le catalogue métier.** C'est ce que montrent vos deux jeux de maquettes.
- **inactive** : retirée du catalogue, mais conservée avec son historique.
- **Pas de suppression** : on désactive. Le journal garde ainsi toujours un sens (il référence des codes qui existent encore). La maquette n'a d'ailleurs aucun bouton « Supprimer ».
- **Modifier une extraction active la remet en brouillon**, avec un nouveau numéro de version et un test à refaire. C'est volontaire : on évite qu'une modification en cours d'écriture soit visible des utilisateurs métier.

## 7. Comment l'application utilise le fichier

Quand un utilisateur exécute une extraction, le moteur suit ces étapes :

1. **Lire** l'extraction par son `code` (elle doit être `active` pour un utilisateur métier).
2. **Construire le formulaire** à partir de `parametres` (les listes dynamiques sont remplies par leur requête).
3. **Valider les saisies côté serveur** : champ obligatoire, type, bornes, valeur présente dans la liste, `fin ≥ début`.
4. **Compiler la requête** : chaque `:nom` est remplacé par un `?`, et la valeur correspondante est ajoutée à la liste des valeurs liées, dans l'ordre d'apparition.
5. **Contrôler** la requête avec `SqlGuard` (lecture seule).
6. **Exécuter** avec le plafond de lignes (`EXTRACTION_MAX_ROWS`, ou 100 lignes pour un test).
7. **Présenter** le résultat selon `colonnes`.
8. **Journaliser** : utilisateur, code, version, paramètres, nombre de lignes, durée.

**Exemple de compilation** (extraction `CLI_001`, l'utilisateur choisit l'agence 1, « Tous les statuts », et saisit « tchou ») :

```
Requête du catalogue                          Requête envoyée à Oracle
WHERE (:agence IS NULL OR cl.agence_id = :agence)     WHERE (? IS NULL OR cl.agence_id = ?)
  AND (:statut IS NULL OR cl.statut = :statut)          AND (? IS NULL OR cl.statut = ?)
  AND (:nom IS NULL OR UPPER(cl.nom)                    AND (? IS NULL OR UPPER(cl.nom)
        LIKE UPPER(:nom) || '%')                              LIKE UPPER(?) || '%')

Valeurs liées, dans l'ordre : "1", "1", NULL, NULL, "tchou", "tchou"
```

Un même `:nom` écrit plusieurs fois devient plusieurs `?`, avec la même valeur répétée. **Les valeurs ne sont jamais collées dans le texte SQL** : elles voyagent séparément, ce qui supprime le risque d'injection SQL. Pour un paramètre `date`, le remplacement est `TO_DATE(?, 'YYYY-MM-DD')`. Les `:` situés dans des textes entre apostrophes (`'12:30'`) sont ignorés.

## 8. Correspondance avec les écrans

| Écran | Champs du catalogue utilisés |
|---|---|
| S02 Accueil admin ; S03 Liste des extractions | `code`, `titre`, `description`, `statut`, `modifie_le` |
| S04 Création : section 01 Informations générales | `code`, `titre`, `description`, `statut` |
| S04 : section 02 Requête SQL ; bouton « Vérifier la requête » | `requete_sql` (contrôle par `SqlGuard` et par les règles de la section 9) |
| S04 : section 03 Paramètres | `parametres[]` : `nom`, `libelle`, `type`, `obligatoire` (+ options) |
| S05 → S06, M03 Formulaire d'exécution | `parametres[]` (champs générés), `defaut` |
| S06 / S07 Test ; M04 Résultat | `colonnes[]`, valeurs saisies (bandeau « Paramètres utilisés » : `libelle` + valeur) |
| M01, M02 Cartes du catalogue | `code`, `titre`, `description` des extractions `active` |
| Publication | `dernier_test` (doit être `reussi` pour la `version` courante) |

**Non calculé ici** : « 1 248 lignes retournées » est mesuré à l'exécution, ce n'est pas une donnée du catalogue.

## 9. Règles de validation à l'enregistrement

L'application doit refuser d'enregistrer une extraction qui ne respecte pas **toutes** ces règles (le schéma JSON `queries.schema.json` en contrôle une partie ; les autres demandent du code) :

1. Le fichier respecte le schéma JSON (types, champs obligatoires, valeurs permises).
2. Le `code` est unique et ne change jamais.
3. Chaque `:nom` de la requête correspond à un paramètre déclaré, et inversement ; les noms de paramètres sont uniques.
4. La requête est acceptée par `SqlGuard` : `SELECT` ou `WITH` uniquement, **une seule instruction**, pas de point-virgule, **pas de commentaire** (`--`, `/* */`), pas de mots comme `INSERT`, `DELETE`, `DROP`, `BEGIN`, `FOR UPDATE`, pas de paquets `DBMS_` ou `UTL_`.
5. **Chaque colonne du SELECT a un nom unique** (utiliser `AS`). Le moteur enveloppe la requête dans `SELECT * FROM (…) FETCH FIRST n ROWS ONLY`, ce que deux colonnes de même nom feraient échouer.
6. Les `colonnes` déclarées correspondent aux colonnes du SELECT (même `cle`, dans le même ordre).
7. Une liste de type `requete` renvoie exactement `valeur` et `libelle` ; elle passe aussi par `SqlGuard`.
8. `apres_ou_egal` désigne un autre paramètre de type `date` de la même extraction.
9. Pour passer à `active` : `dernier_test` est `reussi` **et** porte le même numéro de `version`.
10. *Recommandé* : un `ORDER BY` qui donne un ordre stable (obligatoire si on pagine le résultat, voir section 10).

## 10. Décisions prises et points à valider

| # | Décision proposée | Pourquoi | Alternative possible |
|---|---|---|---|
| 1 | Un seul fichier, une liste d'extractions | Simple pour quelques dizaines d'extractions | Un fichier par extraction (fusions Git plus simples, plus de fichiers) |
| 2 | Requête SQL en liste de lignes | Lisible, différences Git claires | Une seule chaîne avec `\n` (plus compact, illisible) |
| 3 | Paramètres nommés `:nom` | Conforme à la maquette (`:date_reference`) ; le moteur convertit en `?` | `?` positionnels (illisibles) |
| 4 | Dates converties automatiquement en `TO_DATE` | L'auteur de la requête ne peut pas se tromper de format | L'auteur écrit lui-même `TO_DATE(:x, …)` |
| 5 | Valeur vide ou « Toutes » → `NULL` | Un seul motif SQL pour « filtre facultatif » | Écrire plusieurs variantes de la requête |
| 6 | Statuts sans accent et en minuscules (`brouillon`, `active`, `inactive`) | Valeurs techniques stables ; l'écran traduit en « Brouillon »… | Stocker le libellé affiché |
| 7 | Pas de suppression, seulement la désactivation | Garde l'historique cohérent | Suppression avec archivage |
| 8 | Modifier une extraction active la remet en brouillon | Sécurité : rien d'inachevé n'atteint les utilisateurs métier | Garder la version active en ligne pendant qu'on prépare la suivante (plus complexe) |
| 9 | Historique = numéro de `version` + journal d'exécution | Suffisant pour retrouver la requête utilisée | Conserver chaque ancienne définition dans un fichier d'historique |
| 10 | Le SQL cite le schéma (`amp_test.compte`) | Fonctionne tel quel aujourd'hui ; le catalogue appartient à un environnement | Fixer le schéma à la connexion (`ALTER SESSION SET CURRENT_SCHEMA`) pour écrire `compte` sans préfixe et réutiliser le même catalogue sur Amplitude |
| 11 | Les colonnes sont décrites dans le catalogue | Titres lisibles, formats, CSV propre | Détection automatique seule (moins de contrôle) |
| 12 | Le SQL n'est jamais envoyé au navigateur d'un utilisateur métier | Il révèle la structure de la base | – |

**À valider avec votre encadrant et la DOSI**
- Les préfixes de codes (CLI, CPT, OPE, CRT, RIS) et leur signification.
- La règle « modifier une extraction active la remet en brouillon ».
- **Pagination** : tant que le résultat n'est pas paginé, tout s'affiche dans la limite du plafond. Si on pagine côté serveur, chaque extraction doit avoir un `ORDER BY` stable.
- Les caractères `%` et `_` saisis dans un champ texte agissent comme jokers dans un `LIKE` (par exemple le client de test `TCHOUPO_TEST`). À traiter avant la mise en production.

## 11. Ajouts à prévoir dans les maquettes

Le catalogue contient des informations que l'écran de création (S04) ne permet pas encore de saisir :

1. **Colonnes du résultat** : une section 04 (ou une détection automatique après « Vérifier la requête », avec modification des titres et formats).
2. **Paramètre de type liste** : choix de la source (valeurs fixes ou requête) et de l'option « Toutes ».
3. **Options d'un paramètre** : valeur par défaut, bornes (nombre), longueur maximale (texte), « après ou égal à » (date).
4. **Types de paramètre** dans la liste déroulante : Texte, Nombre, Date, Liste déroulante, Interrupteur (Oui/Non).
5. **Boutons** « Publier » (actif seulement après un test réussi) et « Désactiver », avec une confirmation quand on modifie une extraction active.

## 12. Mise en œuvre dans le code

À créer dans `app/Services/Extraction/` (en réutilisant `SqlGuard`, `ExtractionRunner` et `ExecutionLogger`, qui existent déjà) :

| Classe | Rôle |
|---|---|
| `CatalogueRepository` | Lire et écrire `queries.json` en **écriture atomique avec verrou** (fichier temporaire puis remplacement) ; un seul administrateur à la fois |
| `CatalogueValidator` | Appliquer le schéma et les règles croisées de la section 9 |
| `ParameterResolver` | Contrôler et convertir les saisies selon le type (section 4) |
| `QueryCompiler` | Remplacer les `:nom` par des `?`, envelopper les dates, construire la liste des valeurs liées (section 7) |
| `ColumnFormatter` | Appliquer les `colonnes` à l'affichage et au CSV |

`ExtractionRunner` devra accepter une requête déjà compilée et une limite de lignes (100 pour un test, `EXTRACTION_MAX_ROWS` sinon). Au premier démarrage, copier `queries.example.json` vers `storage/app/private/extraction/queries.json` donne un catalogue de départ qui fonctionne avec le schéma de test `AMP_TEST`.

## 13. L'exemple fourni

`queries.example.json` contient les **4 extractions du MVP** du fichier de cadrage, écrites pour le schéma de test :

| Code | Besoin | Statut | Ce qu'elle illustre |
|---|---|---|---|
| `CPT_001` | B01 – comptes ouverts par agence et période | active | Liste d'agences lue en base, deux dates, interrupteur « Inclure les comptes clôturés » (comme la maquette) |
| `CLI_001` | B02 – recherche de clients | active | Listes fixes avec « Tous », champ texte facultatif, colonne calculée (prénom + nom) |
| `OPE_001` | B04 – opérations par agence, période et montant | active | Champ nombre avec valeur par défaut et minimum, format `montant` |
| `CRT_001` | B05 – crédits et engagements | **brouillon** | Statut brouillon (jamais testé) : le domaine des crédits n'est pas confirmé avec la DOSI |

Les titres de la maquette (« Liste des clients actifs », « Échéances de crédits ») étaient des exemples d'illustration : l'exemple ci-dessus suit les besoins réels du fichier de cadrage.
