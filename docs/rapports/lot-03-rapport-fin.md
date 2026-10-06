# Rapport de fin de lot – Lot 3 : Moteur du catalogue

**Branche :** `lot-03-moteur-catalogue`
**Date :** 2026-10-06
**Statut :** Terminé

---

## 1. Objectif

Implémenter le moteur de lecture, validation et exécution des extractions basées sur `queries.json` : chargement du catalogue, résolution des paramètres, compilation SQL, exécution sécurisée sur Oracle, formatage des résultats et journalisation.

---

## 2. Tâches réalisées

| # | Commit | Description |
|---|--------|-------------|
| 1 | c04887a | Ajout de opis/json-schema pour la validation de schéma JSON |
| 2 | 8047103 | CatalogueRepository : lecture/écriture atomique avec verrouillage |
| 3 | 3f5d62b | CatalogueValidator : validation schéma + 10 règles croisées |
| 4 | 4df27a2 | ParameterResolver : résolution et validation des paramètres |
| 5 | 6b417d6 | QueryCompiler : conversion :nom en ?, gestion TO_DATE |
| 6 | 54c7e8a | ExtractionRunner : exécution avec plafond de lignes et timeout |
| 7 | f5415ec | ColumnFormatter : formatage écran/CSV + ExecutionLogger enrichi |
| 8 | 4d8c2d9 | Commandes Artisan catalogue:valider et extraction:executer |
| 9 | 9e5cee4 | Fix : correction du nom de parametre type_credit → type_engagement dans le test CRT_001 |

---

## 3. Architecture des services

`
app/Services/Extraction/
|-- CatalogueRepository.php   - Lecture/écriture atomique de queries.json
|-- CatalogueValidator.php    - Validation JSON Schema + règles métier
|-- ParameterResolver.php     - Résolution et validation des inputs utilisateur
|-- ListOptionsProvider.php   - Options fixes ou issues d une requête SQL
|-- QueryCompiler.php         - Compilation :param → ?, TO_DATE pour les dates
|-- ExtractionRunner.php      - Exécution Oracle avec plafond et timeout
|-- ColumnFormatter.php       - Formatage écran (date, montant) et CSV
|-- ExecutionLogger.php       - Journal d exécution (ID utilisateur, durée, lignes)
└-- SqlGuard.php              - Existant, réutilisé tel quel
`

---

## 4. Tests

63 tests, 113 assertions — OK (0 échec)

| Suite | Tests | Résultat |
|-------|-------|----------|
| SqlGuardTest (unitaire) | 17 | OK |
| CatalogueRepositoryTest (unitaire) | 3 | OK |
| CatalogueValidatorTest (unitaire) | 4 | OK |
| ParameterResolverTest (unitaire) | 9 | OK |
| QueryCompilerTest (unitaire) | 3 | OK |
| ColumnFormatterTest (unitaire) | 4 | OK |
| ExtractionsMvpTest (intégration Oracle) | 8 | OK |
| Auth, Styleguide, Example | 12 | OK |

---

## 5. Règles de sécurité respectées

- Variables liées uniquement : QueryCompiler remplace tous les :param par ?, jamais de concaténation.
- SqlGuard : chaque requête du catalogue est validée avant exécution.
- Plafond de résultats : config/extraction.php max_rows appliqué dans ExtractionRunner.
- Journal : ExecutionLogger enregistre toutes les exécutions (utilisateur, durée, lignes).
- Migrations Oracle : aucune migration exécutée sur Oracle ; toutes les migrations concernent SQLite.
- APP_RO : aucun droit supplémentaire accordé.

---

## 6. Écarts et décisions

| Point | Décision |
|-------|----------|
| opis/json-schema pour la validation | Bibliothèque légère, conforme au schéma JSON Schema Draft-7 |
| CRT_001 en statut brouillon dans queries.example.json | Normal pour un exemple, le test Oracle le charge directement |
| ListOptionsProvider non testé isolément | Testé indirectement via ParameterResolverTest et ExtractionsMvpTest |

---

## 7. Prérequis pour le lot suivant

- Lot 3 validé
- Services disponibles pour les contrôleurs : CatalogueRepository, ExtractionRunner, ColumnFormatter, ExecutionLogger
- Décisions D5-D8 (interface admin/utilisateur) à confirmer selon docs/design/decisions.md
