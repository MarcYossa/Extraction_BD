# Règles du projet Extraction BD (à respecter par tout agent de développement)

## Contexte
Plateforme interne des BCPME : un administrateur technique crée, teste et publie des **extractions** (requêtes de lecture paramétrées) que les utilisateurs métier exécutent **sans écrire de SQL**. Cette phase se développe sur une base Oracle **locale de test** (schéma fictif `AMP_TEST`), pas sur Amplitude.

## Environnement
- Windows, **PowerShell**. Projet : `E:\Documents\Projets_BCPME\extraction-bd`.
- PHP 8.3 (+ extension OCI8), Laravel 13, `yajra/laravel-oci8`, Oracle XE 21c (service `XEPDB1`).
- Interface : Blade + Alpine.js. La technique CSS (Tailwind ou CSS natif) n'est **pas décidée** : ne rien installer sans accord.

## Interdits absolus
- Ne **jamais** appliquer de migration sur Oracle : les migrations ne concernent que la base SQLite applicative (`app`). Ne jamais exécuter les scripts Oracle ni modifier le schéma Oracle.
- Ne **jamais** exécuter les scripts de `database/oracle-test/` (le script `00_setup.sql` supprime des comptes) ni se connecter en `sysdba`.
- Utiliser **uniquement** le compte `APP_RO` (lecture seule). Ne jamais lui ajouter de droits.
- Ne **jamais** lire, afficher, copier ou modifier `.env` ; ne jamais committer de secret.
- Pas de suppression de fichiers hors du projet, pas de `git push --force`, pas d'installation de paquet sans accord explicite.

## Règles de sécurité du code
- SQL : variables liées uniquement ; ne jamais concaténer une saisie utilisateur dans une requête.
- Toute requête du catalogue passe par `App\Services\Extraction\SqlGuard` avant exécution.
- Affichage : échappement Blade `{{ }}` ; export CSV : neutraliser les formules (`=`, `+`, `-`, `@`).
- Messages d'erreur génériques pour l'utilisateur ; le détail va dans `storage/logs/laravel.log`.
- Plafond de résultats : `config/extraction.php` (`max_rows`). Toute exécution est journalisée (`ExecutionLogger`).

## Structure existante à réutiliser
- `app/Services/Extraction/` : `SqlGuard`, `ExtractionRunner`, `ExecutionLogger`.
- `storage/app/private/extraction/` : `queries.json` (catalogue), `execution_log.json` (journal) – non versionnés.
- Le format de `queries.json` est défini par `docs/design/catalogue/catalogue-queries-json.md` et le schéma `docs/design/catalogue/queries.schema.json` ; un catalogue de départ est fourni dans `queries.example.json`. Toute extraction enregistrée doit respecter ce schéma **et** les règles de validation du document.
- `SocleTestController` et la vue `socle-test` sont **temporaires** : ils seront remplacés.

## Respect des maquettes
- Références : `docs/design/design-tokens.md`, `docs/design/ecrans-admin-technique.md`, `docs/design/ecrans-utilisateur-metier.md` et les captures `docs/design/admin/*.png` et `docs/design/metier/*.png`.
- **Un seul gabarit** de page (menu latéral + barre du haut) dont le menu et les libellés dépendent du rôle ; les composants communs (formulaire de paramètres, tableau de résultats, cartes, pastilles) sont **partagés** entre les écrans administrateur et métier.
- **Ne pas inventer** de couleur, de police, de taille ou d'espacement : utiliser les variables CSS de la fiche de styles.
- Reproduire les textes français des maquettes, **sauf** les notes de prototype (ex. « Démonstration… » sur la connexion).
- Si une valeur manque (état d'erreur, écran absent, mobile), **poser la question** plutôt que d'improviser.

## Rôles et autorisations
- Deux rôles : **administrateur technique** et **utilisateur métier**.
- L'utilisateur métier ne voit et n'exécute **que** les extractions au statut `Active` ; il n'a **aucun accès** aux routes `/admin` : contrôle **côté serveur** (middleware ou politique), pas seulement un menu masqué.
- Chaque exécution et chaque export est journalisé avec l'identifiant réel de l'utilisateur.

## Méthode de travail
1. **Un écran par tâche et par branche Git** (`feat/ecran-s03-liste-extractions`). Commits en français préfixés : `feat:`, `fix:`, `docs:`, `chore:`.
2. Commencer par un **plan court** et attendre la validation avant d'écrire du code.
3. Ouvrir la page dans le navigateur intégré, la **comparer à la capture** et lister les écarts avant de conclure.
4. Tests : `vendor\bin\phpunit` doit rester vert (dont `SqlGuardTest`) ; ajouter des tests pour toute nouvelle logique.

## Définition de « terminé » pour un écran
Fidèle à la capture (écarts listés) · états vide, chargement et erreur gérés · textes en français · tests verts · aucune règle ci-dessus enfreinte · commit propre.
