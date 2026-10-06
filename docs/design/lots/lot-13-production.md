# Lot 13 – Préparation à la production (Amplitude)

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-13-production` · **Taille** : M · **README** : étape 14

## Objectif
Préparer le passage de la base de test vers **Amplitude** : rendre le schéma configurable, fournir la configuration, les procédures d'exploitation et la recette. Ce lot comporte une **partie A** (réalisée par l'agent) et une **partie B** (réalisée par Marc avec la DOSI). **Aucune connexion à Amplitude n'est faite par l'agent.**

## Prérequis
Lot 12 validé. Décision **D20** (schéma Oracle en production) tranchée : l'agent la rappelle dans son plan et attend la réponse.

## Documents à lire
`docs/design/catalogue/catalogue-queries-json.md` (décision 10) · `config/extraction.php` · le rapport du lot 12 · `README.md`.

## Partie A – À réaliser par l'agent
1. [ ] **Schéma configurable** selon D20 :
   - *Option « schéma fixé à la connexion »* : variable `DB_SCHEMA` appliquée par `ALTER SESSION SET CURRENT_SCHEMA` à l'ouverture de la connexion Oracle (investiguer la méthode avec `yajra/laravel-oci8`) ; les requêtes s'écrivent sans préfixe.
   - *Option « préfixe explicite »* : substitution contrôlée d'un marqueur de schéma.
   Dans les deux cas, commande **`catalogue:migrer-schema`** : mode **simulation** par défaut (affiche les différences), puis application avec sauvegarde préalable de `queries.json`. Tests unitaires.
2. [ ] **Configuration de production** : `.env.production.example` commenté (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` en HTTPS, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES`, journaux quotidiens, `LOG_LEVEL=warning`, plafond, délai) **sans aucun secret**.
3. [ ] **Garde-fous de production** : le seeder des comptes de développement refuse de s'exécuter hors `local` (test) ; `/dev/styleguide` renvoie 404 ; `database/oracle-test/` n'est pas déployé ; `php artisan about` n'affiche aucun secret.
4. [ ] **Sauvegarde et restauration** : commande `catalogue:sauvegarder` (copie datée de `queries.json` et des journaux, conservation de 30 jours) et procédure de restauration testée.
5. [ ] **Archivage des journaux** : commande `journaux:archiver` (archive mensuelle de `execution_log.json` et `auth_log.json`, rotation, aucune perte) ; planification des commandes `results:purger`, `journaux:archiver` et `catalogue:sauvegarder` (Planificateur de tâches Windows ou cron) documentée.
6. [ ] **Optimisation** : documenter et tester `npm run build`, `php artisan config:cache`, `route:cache`, `view:cache`, `composer install --no-dev --optimize-autoloader` ; vérifier que l'application fonctionne avec ces caches.
7. [ ] **Santé** : exploiter la route de santé de Laravel (`/up`) et ajouter un contrôle de la connexion Oracle (sans exposer d'information sensible).
8. [ ] **Documents** : `docs/exploitation/guide-deploiement.md` (prérequis serveur, déploiement, mise à jour, retour arrière), `docs/exploitation/guide-exploitation.md` (surveillance, incidents types : Oracle indisponible, délai dépassé, disque plein, mot de passe `APP_RO` changé, extraction en échec), `docs/recette/plan-de-recette.md` (scénarios par rôle avec les extractions et les valeurs attendues).
9. [ ] **Remise à zéro de la recette** : `CRT_001` repasse en brouillon tant que le domaine des crédits n'est pas confirmé ; liste des extractions à adapter aux tables d'Amplitude.

## Partie B – À réaliser par Marc avec la DOSI (l'agent prépare les documents)
- [ ] Documentation du **schéma réel d'Amplitude** et des vues à exposer.
- [ ] Création d'un **compte Oracle en lecture seule** (équivalent d'`APP_RO`) limité aux vues nécessaires (principe du moindre privilège, de préférence des vues plutôt que les tables).
- [ ] **Réécriture et test des requêtes** sur les données d'Amplitude (ou d'un environnement de préproduction).
- [ ] Hébergement, système, **serveur web**, **HTTPS** (certificat, mandataire inverse), pare-feu vers Oracle, **client Oracle** compatible.
- [ ] **Valeurs définitives** du plafond de lignes et du délai maximal, politique de conservation des journaux, sauvegardes.
- [ ] **Recette** avec de vrais utilisateurs métier, formation, validation finale.

### Questions à poser à la DOSI (l'agent les reprend dans `docs/exploitation/questions-dosi.md`)
1. Quel serveur, quel système et quel serveur web hébergeront l'application ? Quelle version de PHP est autorisée ?
2. Quel client Oracle (version, Instant Client ou complet) est installé ou installable ?
3. Quelles tables ou vues d'Amplitude peut-on interroger ? Existe-t-il des vues d'extraction déjà validées ?
4. Un compte Oracle en lecture seule dédié peut-il être créé ? Avec quelles restrictions (vues, plages horaires, quotas) ?
5. Quels sont le délai maximal et le volume de lignes acceptables sur la base de production ? Peut-on utiliser un réplica ou une base de préproduction ?
6. Quelle politique d'authentification est exigée (compte local, annuaire, authentification unique) ?
7. Quelles exigences de journalisation et d'audit s'appliquent (contenu, durée de conservation, accès) ?
8. Quel mécanisme de sauvegarde et de supervision est attendu ? Qui est l'exploitant ?
9. HTTPS, nom de domaine interne et règles réseau : qui les fournit ?
10. Quelles données sont **sensibles** et ne doivent jamais être extraites ou affichées ?

## Fichiers à créer ou modifier
`app/Console/Commands/{MigrerSchema,SauvegarderCatalogue,ArchiverJournaux}.php`, `config/database.php`, `config/extraction.php`, `.env.production.example`, `docs/exploitation/*`, `docs/recette/plan-de-recette.md`, tests.

## Commits prévus
1. `feat: schéma Oracle configurable et commande de migration du catalogue`
2. `chore: configuration de production et garde-fous`
3. `feat: sauvegarde du catalogue et archivage des journaux`
4. `feat: contrôle de santé incluant Oracle`
5. `docs: guides de déploiement et d'exploitation`
6. `docs: plan de recette et questions pour la DOSI`
7. `test: garde-fous de production et migration du schéma`

## Résultat combiné à retourner
- **Démonstration de `catalogue:migrer-schema`** : simulation puis application sur une copie, avec les différences (avant/après) et la sauvegarde créée.
- **Test de restauration** : sauvegarde, altération volontaire, restauration, `catalogue:valider` OK.
- **Application lancée avec les caches de production** : captures et sortie des commandes.
- Les **documents** : guide de déploiement, guide d'exploitation, plan de recette, `.env.production.example`, questions pour la DOSI.
- Liste de ce qui reste **bloquant** côté DOSI (partie B) avec l'interlocuteur à contacter.
- Sortie de `vendor\bin\phpunit` et de `composer audit`.

## Checklist de validation (Marc)
- [ ] Je peux changer de schéma par la configuration sans modifier le code ni une extraction à la main.
- [ ] Le seeder de développement refuse de tourner en production.
- [ ] La restauration du catalogue fonctionne.
- [ ] Les questions pour la DOSI sont envoyées ; les réponses sont consignées dans `docs/exploitation/`.
- [ ] Le plan de recette est compris et chiffré avec les utilisateurs métier.

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-13-production -m "merge: lot 13 - préparation à la production"` · `git tag lot-13-valide`
Après la **recette réussie et la validation de la DOSI** : `git tag v1.0.0`.
