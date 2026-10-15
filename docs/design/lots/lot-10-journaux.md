# Lot 10 – Journaux d'exécution et historique personnel

> **Instruction à l’agent** : lis d’abord `AGENTS.md`, `docs/design/plan-developpement-lots.md` (sections 4 et 5) et `docs/design/decisions.md`. Travaille **uniquement** sur ce lot. Commence par un **plan de 10 lignes maximum** et attends l’accord de Marc avant d’écrire du code.

**Branche** : `lot-10-journaux` · **Taille** : M · **README** : étapes 10 (`/admin/logs`) et 11 (`/mes-extractions`)

## Objectif
Donner à l'administrateur un **journal consultable** (exécutions, tests, exports, publications, connexions) et à chaque utilisateur métier son **historique personnel** avec relance.

## Prérequis
Lot 9 validé. Décisions **D15 et D16** validées.

## Documents à lire
`docs/design/ecrans-admin-technique.md` (S03, motif de liste à réutiliser) · `docs/design/design-tokens.md` · `app/Services/Extraction/ExecutionLogger.php` et le service du journal des connexions du lot 2 . `docs/design/metier/5- Historique.png`. `docs/design/metier/5.1 - Historique.png`. `docs/design/admin/9- Journal-activités_details.png`. `docs/design/admin/9-1-Journal-activités_details.png`

## Tâches
1. [ ] **`LogReader`** : lecture de `execution_log.json` et `auth_log.json` ; filtres (période, utilisateur, extraction, mode, statut) ; tri par date décroissante ; **pagination côté serveur** de 50 entrées ; tolère un fichier vide ou une entrée mal formée sans planter.
2. [ ] **Mesure de performance** (outil de test, sans toucher aux vrais journaux) : temps de lecture avec **5 000** puis **20 000** entrées synthétiques. Si le temps dépasse 1 seconde à 20 000 entrées, **proposer** le passage en lignes JSON avec rotation mensuelle (D15) et **attendre la validation** avant de l'implémenter.
3. [ ] **`GET /admin/logs`** (administrateur) : deux onglets, « Exécutions » et « Connexions ». Colonnes des exécutions : date et heure, utilisateur, extraction (code + titre), mode (pastille : Test, Exécution, Export CSV, Publication…), paramètres (résumé « 3 paramètres » + détail dans une fenêtre), statut (Succès / Erreur), lignes (avec mention « tronqué »), durée. Colonnes des connexions : date, identifiant saisi, résultat, adresse IP.
4. [ ] **`GET /mes-extractions`** (utilisateur métier) : **uniquement ses propres** exécutions et exports ; colonnes : date, extraction, paramètres (résumé), lignes, durée, statut ; action **« Relancer »** qui ouvre `/extractions/{code}` avec les paramètres pré-remplis (désactivée avec une explication si l'extraction n'est plus active).
5. [ ] **Isolation** : un utilisateur ne voit jamais l'activité d'un autre, **y compris en modifiant les paramètres d'URL**. Le filtre « utilisateur » est imposé côté serveur.
6. [ ] **Menus** : entrée « Journal » dans le menu administrateur et « Mes extractions » dans le menu métier (ajouts hors maquette, à valider visuellement).
7. [ ] **Confidentialité** : le journal ne contient et n'affiche **aucun résultat d'extraction** ; les paramètres saisis (par exemple un nom de client) ne sont visibles que par l'administrateur et par l'utilisateur concerné.
8. [ ] **États** : journal vide, filtre sans résultat, entrée en erreur (message générique).
9. [ ] **Tests** : `Unit/LogReaderTest` (filtres, pagination, fichier vide, entrée corrompue) ; `Feature/AdminLogsTest` (accès `user` = 403) ; `Feature/MesExtractionsTest` (isolation entre deux utilisateurs, relance, extraction désactivée) ; test de performance marqué `#[Group('lent')]`, hors exécution par défaut.

## Fichiers à créer ou modifier
`app/Services/Extraction/LogReader.php`, `app/Http/Controllers/Admin/LogController.php`, `app/Http/Controllers/Metier/MesExtractionsController.php`, `resources/views/admin/logs/index.blade.php`, `resources/views/metier/mes-extractions.blade.php`, `resources/views/layouts/*` (menus), `routes/web.php`, tests.

## Commits prévus
1. `feat: lecture filtrée et paginée des journaux`
2. `test: mesure de performance du journal`
3. `feat: journal d'administration (exécutions et connexions)`
4. `feat: historique personnel avec relance`
5. `feat: entrées de menu du journal et de l'historique`
6. `test: journaux, isolation des utilisateurs`

## Résultat combiné à retourner
- **Captures** du journal (onglets Exécutions et Connexions, avec filtres), de la fenêtre des paramètres, de « Mes extractions » et des états vides.
- **Mesures de performance** à 5 000 et 20 000 entrées, et la recommandation qui en découle.
- **Preuve d'isolation** : test automatisé et démonstration manuelle (`sonia.d` ne voit rien d'`amine.m`, même en changeant l'adresse).
- Sortie des tests.
- La liste des **choix de présentation** pris faute de maquette, à valider.

## Checklist de validation (Marc)
- [ ] Après quelques exécutions et exports, chacun apparaît dans le journal avec le bon utilisateur et le bon mode.
- [ ] `sonia.d` ne peut pas ouvrir `/admin/logs` (403) et ne voit que ses lignes dans « Mes extractions ».
- [ ] « Relancer » pré-remplit exactement les paramètres de l'exécution d'origine.
- [ ] Aucune donnée de résultat n'apparaît dans les journaux.
- [ ] Le style est cohérent avec la liste des extractions (S03).

## Fin de lot (Marc)
`git checkout main` · `git merge --no-ff lot-10-journaux -m "merge: lot 10 - journaux et historique personnel"` · `git tag lot-10-valide`
