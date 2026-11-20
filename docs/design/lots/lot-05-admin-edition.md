# Lot 5 – Administration : création et modification d'une extraction

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-05-admin-edition` · **Taille** : L · **README** : étape 10 (partie 2) · **Écran** : S04

## Objectif
Permettre à l'administrateur de **créer et modifier** une extraction (informations, requête SQL, paramètres, colonnes) avec un formulaire fidèle à S04, qui n'enregistre que des définitions valides.

## Prérequis
Lot 4 validé. Décisions **D11** (validée) et **D22** (état du champ « Statut » et cycle de vie) validées.

## Documents et captures à lire
`docs/design/ecrans-admin-technique.md` (S04, S05) · `docs/design/catalogue/catalogue-queries-json.md` (sections 3, 4, 5, 9, 11) · `docs/design/admin/04-extraction-infos-generales.png` · `docs/design/admin/05-extraction-requete-parametres.png`.

## Tâches
1. [ ] **Routes** : `GET /admin/extractions/create`, `POST /admin/extractions`, `GET /admin/extractions/{code}/edit`, `PUT /admin/extractions/{code}`, `POST /admin/extractions/verifier` (réponse JSON). Tout passe par `CatalogueValidator` ; l'enregistrement utilise `CatalogueRepository`.
2. [ ] **Formulaire fidèle à S04** : « ‹ Retour aux extractions », titre « Créer une extraction » (« Modifier une extraction » en édition), sous-titre, pastille de statut. **Section 01** : *Code\** (proposé automatiquement : prochain numéro libre du préfixe le plus récent ; **non modifiable** en édition), *Titre\**, *Description\**, *Statut* (**lecture seule**, D22).
3. [ ] **Section 02 – Requête SQL** : bloc sombre `requete.sql` (étiquette « SQL »), zone de saisie monospace avec **surlignage des `:paramètres`** (calque `<pre>` synchronisé en Alpine, **sans bibliothèque**). La saisie est stockée en lignes dans `requete_sql[]`.
4. [ ] **« Vérifier la requête »** (appel JSON, sans exécution sur Oracle) : `SqlGuard`, cohérence `:noms` ↔ paramètres déclarés, noms de colonnes uniques, colonnes déclarées = colonnes du SELECT (analyseur statique `SelectColumnParser`). Affiche « La requête respecte les règles de lecture. » ou la liste des erreurs en français.
5. [ ] **Section 03 – Paramètres** (Alpine) : lignes avec poignée de réorganisation (glisser-déposer **et** boutons ↑ ↓ au clavier), *Nom*, *Libellé*, *Type* (Texte, Nombre, Date, Liste déroulante, Interrupteur Oui/Non), case *Obligatoire*, bouton supprimer (croix), bouton « + Ajouter un paramètre ». **Options propres au type** dans un panneau déplié : texte → longueur maximale ; nombre → minimum, maximum ; date → « après ou égale à » (liste des autres paramètres date) ; liste → source *valeurs fixes* (une ligne `valeur | libellé`) **ou** *requête* (valeur/libellé), option « Toutes… » ; valeur par défaut (`aujourd_hui` pour une date). Bouton « Détecter depuis la requête » : crée les lignes des `:noms` manquants.
6. [ ] **Section 04 – Colonnes du résultat** (ajout hors maquette, section 11 de la spécification) : lignes pré-remplies d'après le SELECT, avec *libellé* et *format* modifiables (texte, code, nombre, montant, date, statut).
7. [ ] **Actions** : *Annuler* ; *Enregistrer en brouillon* (validation complète ; création : `version` 1, `cree_*`, `modifie_*` ; modification : `version` +1, `modifie_*`, `dernier_test` remis à `null`) ; *Tester avant publication ›* (enregistre puis ouvre `/admin/extractions/{code}/test`, page provisoire jusqu'au lot 6).
8. [ ] **Extraction active** (D11) : bandeau d'avertissement et fenêtre de confirmation (« Cette extraction sera retirée du catalogue et repassera en brouillon jusqu'à une nouvelle publication ») ; à l'enregistrement : statut `brouillon`, `version` +1, `dernier_test` à `null`.
9. [ ] **Erreurs** par champ en français (composant `form-field`), saisie conservée, message de succès, protection contre la double soumission.
10. [ ] **Tests** : `Feature/AdminExtractionFormTest` (création, code déjà utilisé, mauvais format de code, SQL refusé, paramètre non déclaré, colonnes en double, modification, extraction active → brouillon + version +1, accès `user` = 403, point d'entrée de vérification cas OK et cas KO) ; `Unit/SelectColumnParserTest` (alias avec `AS`, fonctions comme `TRIM(...)`, colonnes sans alias, doublons, sous-requêtes) ; le fichier écrit doit passer `php artisan catalogue:valider`.

## Fichiers à créer ou modifier
`app/Http/Controllers/Admin/ExtractionAdminController.php`, `app/Services/Extraction/SelectColumnParser.php`, `resources/views/admin/extractions/{create,edit,_form}.blade.php`, `resources/js/parametres-builder.js`, `resources/css/components.css` (si besoin, avec les jetons), `routes/web.php`, `tests/Feature/AdminExtractionFormTest.php`, `tests/Unit/SelectColumnParserTest.php`.

## Commits prévus
1. `feat: formulaire de création, section informations générales`
2. `feat: éditeur de requête avec surlignage des paramètres`
3. `feat: analyse statique des colonnes du SELECT`
4. `feat: vérification de la requête sans exécution`
5. `feat: constructeur de paramètres et options par type`
6. `feat: section colonnes du résultat`
7. `feat: enregistrement en brouillon et modification avec versions`
8. `feat: avertissement lors de la modification d'une extraction active`
9. `test: formulaire d'extraction et analyseur de colonnes`

## Résultat combiné à retourner
- **Captures de S04** (haut de page, requête et paramètres, bas de page) **côte à côte avec les maquettes**, puis les états : erreur de champ, vérification réussie, vérification en échec, panneau d'options d'une liste, section colonnes, avertissement d'extraction active.
- **Scénario guidé** : création d'une extraction de test `CPT_099` avec un paramètre date, une liste fixe et un interrupteur, puis modification ; **extrait de `queries.json`** avant et après (versions, dates, auteur), sans modifier les 4 extractions existantes.
- Sortie de `php artisan catalogue:valider` après chaque enregistrement.
- **Liste des ajouts hors maquette** (section 04 colonnes, options des paramètres, « Détecter depuis la requête ») pour validation visuelle de Marc.
- Sortie de `vendor\bin\phpunit`.

## Checklist de validation (Marc)
- [ ] Je reproduis l'extraction de la maquette (`CPT_006`, deux paramètres) et le résultat ressemble aux captures.
- [ ] Une requête contenant `DELETE`, un `;` ou un commentaire est refusée avec un message clair.
- [ ] Un `:paramètre` non déclaré (ou un paramètre inutilisé) bloque l'enregistrement.
- [ ] Je réordonne des paramètres à la souris **et** au clavier.
- [ ] Je modifie `CPT_001` (active) : elle passe en brouillon, version 4, et disparaîtra du catalogue métier.
- [ ] « Vérifier la requête » n'écrit rien dans `execution_log.json` (aucune exécution Oracle).
- [ ] `php artisan catalogue:valider` répond OK.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-05-admin-edition -m "merge: lot 5 - création et modification d'extractions"` · `git tag lot-05-valide`
