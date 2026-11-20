# Lot 6 – Administration : test, publication, désactivation

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-06-admin-test-publication` · **Taille** : L · **README** : étape 10 (partie 3) · **Écrans** : S05, S06, S07

## Objectif
Permettre de **tester** une extraction (aperçu de 100 lignes), puis de la **publier**, la **désactiver** ou la **réactiver**, en respectant la règle « pas de publication sans test réussi de la version courante ». Construire les **composants partagés** réutilisés par l'espace métier.

## Prérequis
Lot 5 validé. Décisions **D8, D13, D21 et D22** validées.

## Documents et captures à lire
`docs/design/ecrans-admin-technique.md` (S05, S06, S07) · `docs/design/catalogue/catalogue-queries-json.md` (sections 6 et 7) · `docs/design/admin/06-modale-tester.png`, `07-test-parametres.png`, `08-test-resultat.png`.

## Tâches
1. [ ] **`ResultStore`** (D13) : conserve le résultat d'une exécution (colonnes, lignes, paramètres, code, version, durée, tronqué) dans `storage/app/private/results/{jeton}.json`, **15 minutes**, lié à l'utilisateur, jeton aléatoire de 40 caractères ; accès par un autre utilisateur ou jeton expiré = 404 ; purge des fichiers expirés (commande `results:purger` et purge opportuniste) ; jamais de contenu dans les journaux.
2. [ ] **Composants partagés** : `x-extraction-params-form` (champs générés d'après `parametres` : date, texte, nombre, liste avec option « Toutes », interrupteur ; valeurs par défaut ; `*` rouge ; erreurs de validation sous les champs ; date en français), `x-results-table` (en-têtes de `colonnes`, formats, **pagination côté client de 50 lignes** en Alpine, état vide), `x-params-bandeau` (« PARAMÈTRES UTILISÉS » : libellés + valeurs lisibles : dates jj/mm/aaaa, libellé d'option, Oui/Non).
3. [ ] **S05 – Modale « Tester cette extraction ? »** : s'ouvre depuis « Tester avant publication » du formulaire (le bouton enregistre d'abord) ; boutons *Annuler* et *Lancer le test*.
4. [ ] **S06** `GET /admin/extractions/{code}/test` : lien « ‹ Retour à l'édition », sur-titre « APERÇU AVANT PUBLICATION », titre et description, carte « Paramètres d'exécution », encart « Exécution sécurisée », bouton « Exécuter le test », note « L'aperçu est limité aux 100 premières lignes. »
5. [ ] **Exécution du test** `POST /admin/extractions/{code}/test` : `ParameterResolver` → `QueryCompiler` → `ExtractionRunner` (limite 100, délai maximal) ; **total exact par un `COUNT(*)` supplémentaire** (D21) ; résultat dans le `ResultStore` ; enregistrement de `dernier_test` (version courante, date, auteur, `reussi`/`echec`, nombre de lignes, durée) ; journal `mode: test`. En cas d'échec (délai, erreur Oracle) : message générique, `dernier_test.resultat = echec`, détail dans `laravel.log`.
6. [ ] **S07** `GET /admin/extractions/{code}/test/resultat/{jeton}` : « ‹ Retour aux paramètres » (valeurs conservées), sur-titre « APERÇU DE TEST », titre, « ✓ Exécution terminée avec succès », bandeau des paramètres, carte « Résultats » (« 1 248 lignes retournées · aperçu limité à 100 lignes », pastille « Test réussi », tableau). **Bouton « Publier cette extraction » ajouté** (D22) lorsque le test a réussi.
7. [ ] **Statut** `PATCH /admin/extractions/{code}/statut` avec `action` = `publier`, `desactiver` ou `reactiver` :
   - *publier* : seulement si `dernier_test` est `reussi` **et** porte la `version` courante ; renseigne `publie_le` et `publie_par` ;
   - *desactiver* : retire du catalogue métier ;
   - *reactiver* : seulement si `dernier_test` est valide pour la version courante.
   Fenêtre de confirmation ; refus avec message explicite sinon ; **action rapide** « Activer / Désactiver » ajoutée dans la liste S03 (D22).
8. [ ] **Journal** : publication, désactivation et réactivation sont journalisées (`mode` : `publication`, `desactivation`, `reactivation`).
9. [ ] **Tests** : `Feature/AdminTestPublishTest` (test réussi enregistré, publication refusée sans test, refusée si la version a changé depuis le test, acceptée sinon, désactivation, réactivation, accès `user` = 403, validation des paramètres réaffichée, échec simulé de l'exécution) ; `Unit/ResultStoreTest` (expiration, autre utilisateur, jeton inconnu, purge).

## Fichiers à créer ou modifier
`app/Services/Extraction/ResultStore.php`, `app/Console/Commands/PurgerResultats.php`, `app/Http/Controllers/Admin/ExtractionTestController.php`, `app/Http/Controllers/Admin/ExtractionStatutController.php`, `resources/views/components/{extraction-params-form,results-table,params-bandeau}.blade.php`, `resources/views/admin/extractions/{test,resultat}.blade.php`, `routes/web.php`, `tests/Feature/AdminTestPublishTest.php`, `tests/Unit/ResultStoreTest.php`.

## Commits prévus
1. `feat: conservation temporaire des résultats d'exécution`
2. `feat: composants partagés (formulaire de paramètres, tableau de résultats, bandeau)`
3. `feat: modale de test et page des paramètres d'exécution`
4. `feat: exécution du test avec aperçu de 100 lignes et total exact`
5. `feat: page de résultat du test`
6. `feat: publication, désactivation et réactivation avec règle du test réussi`
7. `feat: action rapide d'activation dans la liste`
8. `test: test, publication et conservation des résultats`

## Résultat combiné à retourner
- **Démonstration complète** : créer (lot 5) → vérifier → tester → publier ; **captures de S05, S06 et S07** côte à côte avec les maquettes, plus : test en échec, validation de paramètres, publication refusée sans test (message affiché).
- **Test de référence** : `CPT_001`, agence 1, 2018-01-01 → 2023-12-31, comptes clôturés exclus → total **192**, aperçu de 100 lignes, **2 pages** de 50.
- **Extrait de `queries.json`** après publication (`statut`, `publie_*`, `dernier_test`) et **extrait du journal** (`mode: test`, `publication`).
- Preuve du `ResultStore` : contenu d'un fichier de résultat (sans donnée métier réelle ici), expiration et purge démontrées avec une durée réduite.
- Sortie de `vendor\bin\phpunit` (dont tests Oracle du groupe `oracle`).

## Checklist de validation (Marc)
- [ ] S05, S06 et S07 sont fidèles aux captures ; les boutons ajoutés (Publier, Activer/Désactiver) sont cohérents avec le style.
- [ ] Publier sans test, ou après modification, est **refusé**.
- [ ] Le test de `CPT_001` donne 192 lignes au total et 100 en aperçu, paginées par 50.
- [ ] Un autre utilisateur ne peut pas ouvrir l'adresse d'un résultat (404).
- [ ] Une extraction désactivée disparaît de la liste active ; la réactiver la remet.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-06-admin-test-publication -m "merge: lot 6 - test et publication"` · `git tag lot-06-valide`
