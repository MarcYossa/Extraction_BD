# Extraction-BD — Plateforme sécurisée d'extraction paramétrée de données métier

> **Stagiaire :** NGOUKAM YOSSA Marc  
> **Encadrant :** M. MOUAFFO MOISE  
> **Superviseur :** M. NDJOMO EKO (DOSI)  
> **Version :** MVP — Laravel 13 · Oracle XE (XEPDB1) · PHP 8.x

---

## Table des matières

1. [Contexte et problématique](#1-contexte-et-problématique)
2. [Objectifs du projet](#2-objectifs-du-projet)
3. [Périmètre fonctionnel MVP](#3-périmètre-fonctionnel-mvp)
4. [Architecture technique](#4-architecture-technique)
5. [Structure du projet](#5-structure-du-projet)
6. [Base de données Oracle de test](#6-base-de-données-oracle-de-test)
7. [Extractions disponibles (MVP)](#7-extractions-disponibles-mvp)
8. [Sécurité](#8-sécurité)
9. [Prérequis & Installation](#9-prérequis--installation)
10. [Configuration](#10-configuration)
11. [Lancement en développement](#11-lancement-en-développement)
12. [Tests](#12-tests)
13. [Étapes de développement du MVP](#13-étapes-de-développement-du-mvp)
14. [Glossaire](#14-glossaire)

---

## 1. Contexte et problématique

**Amplitude** est le système d'information bancaire (Core Banking System) utilisé par l'établissement. Il stocke l'ensemble des données métier : comptes clients, opérations, crédits, agences, etc.

Les équipes métier (commerciaux, analystes, contrôle de gestion) ont régulièrement besoin d'extraire des données d'Amplitude pour effectuer des analyses, produire des rapports ou alimenter des tableaux de bord. Aujourd'hui, ces extractions sont réalisées de façon non structurée (accès SQL direct, scripts ad hoc, exports manuels), ce qui génère :

- des **risques de sécurité** (accès non contrôlé, écriture accidentelle possible) ;
- une **absence de traçabilité** des extractions effectuées ;
- une **dépendance à la DSI** pour toute demande d'extraction ;
- un risque d'**injection SQL** via des requêtes mal protégées ;
- une **absence de plafond de volume**, pouvant saturer la base.

### Problématique centrale

> Comment mettre à disposition des utilisateurs métier une interface web simple, sécurisée et traçable permettant d'interroger la base Amplitude en lecture seule, sans risque pour l'intégrité des données ni surcharge du système ?

---

## 2. Objectifs du projet

### Objectif général

Concevoir, développer et mettre en œuvre une **plateforme web sécurisée d'extraction paramétrée** de données métier à partir de la base de données Oracle Amplitude.

### Objectifs spécifiques

| # | Objectif |
|---|----------|
| OS1 | Fournir une interface web permettant aux utilisateurs métier de lancer des extractions sans connaître SQL |
| OS2 | Garantir la lecture seule : aucune modification de la base Amplitude n'est possible |
| OS3 | Sécuriser les paramètres d'entrée contre l'injection SQL (bind variables obligatoires) |
| OS4 | Journaliser chaque exécution (utilisateur, paramètres, volume, durée, statut) |
| OS5 | Plafonner le volume de résultats pour maîtriser l'impact sur la base |
| OS6 | Permettre l'export des résultats au format CSV (compatible Excel avec BOM UTF-8) |
| OS7 | Proposer un espace d'administration pour définir, tester et publier les extractions |
| OS8 | Permettre une transition fluide entre la base de test (Oracle XE) et Amplitude en production |

---

## 3. Périmètre fonctionnel MVP

Le MVP couvre les **8 besoins fonctionnels** identifiés dans la charte de projet :

| ID | Besoin métier | Tables concernées |
|----|---------------|-------------------|
| **B01** | Comptes ouverts par agence, période et statut | `compte`, `agence` |
| **B02** | Recherche client par agence, statut et nom (début de nom) | `client`, `agence` |
| **B03** | Opérations par compte sur une période | `operation`, `compte` |
| **B04** | Opérations par agence, période, seuil de montant et type | `operation`, `compte`, `agence` |
| **B05** | Crédits/engagements par agence, période, statut et type | `credit`, `agence`, `client` |
| **B06** | Comptes sans mouvement depuis une date donnée | `compte`, `operation`, `agence` |
| **B07** | Clients PME actifs par agence | `client`, `agence` |
| **B08** | Soldes agrégés par agence et type de compte | `compte`, `agence` |

### Espaces fonctionnels

```
+-----------------------+------------------------------------------+
|   ESPACE ADMIN        |         ESPACE METIER                    |
|  (Administrateur)     |       (Utilisateur)                      |
+-----------------------+------------------------------------------+
| Creer extraction      | S'authentifier                           |
| Configurer params     | Rechercher une extraction publiee        |
| Tester en live        | Renseigner les parametres                |
| Publier/desactiver    | Lancer l'execution                       |
| Voir les journaux     | Consulter les resultats                  |
|                       | Exporter en CSV                          |
+-----------------------+------------------------------------------+
```

---

## 4. Architecture technique

### Flux de données

```
Navigateur (HTML/CSS/JS/Alpine.js)
        |  HTTP / HTTPS
        v
Laravel 13 (PHP 8.x)          <- Backend applicatif
    |  OCI8 / yajra-oci8
    v
Oracle XE XEPDB1              <- Base de test (dev)
    |-- AMP_TEST.*            <- Donnees fictives
    |-- APP_RO                <- Compte lecture seule

          (cible production)
Oracle Amplitude              <- Donnees reelles (apres accord DOSI)
```

### Composants techniques

| Composant | Technologie | Rôle |
|-----------|-------------|------|
| **Frontend** | Blade + HTML5 + CSS3 + JavaScript + Alpine.js | Formulaires, affichage résultats, administration |
| **Backend** | PHP 8.x + Laravel 13 + OCI8 | Auth, sessions, validation, SQL paramétré, export |
| **Métadonnées** | Fichiers JSON (storage/app/private/) | Catalogue extractions (`queries.json`), journal (`execution_log.json`) |
| **Base de données** | Oracle XE 21c / XEPDB1 | Source de données (lecture seule via `APP_RO`) |
| **Communication** | HTTP/HTTPS + requêtes SQL paramétrées | Navigateur <-> serveur <-> base |

### Principes d'architecture

- **Séparation des couches** : présentation / services applicatifs / métadonnées / données métier
- **Compte de base en lecture seule** (`APP_RO`) : aucun droit `INSERT`, `UPDATE`, `DELETE`, `CREATE`
- **Double garde-fou SQL** : filtre applicatif (`SqlGuard`) + droits restreints en base
- **Plafond de résultats** configurable (`EXTRACTION_MAX_ROWS`, défaut : 10 000 lignes)
- **Timeout configurable** (`EXTRACTION_TIMEOUT_SECONDS`, défaut : 60 s)
- **Catalogue d'extractions JSON** : les requêtes sont définies par l'administrateur dans `queries.json`, jamais par l'utilisateur

---

## 5. Structure du projet

```
extraction-bd/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                         # Espace administration technique
│   │   │   ├── Metier/                        # Espace utilisateur métier
│   │   │   └── Controller.php
│   ├── Models/
│   │   └── User.php
│   └── Services/
│       └── Extraction/
│           ├── CsvExporter.php                # Export CSV sécurisé (BOM UTF-8, formules)
│           ├── ExecutionLogger.php            # Journal JSON des exécutions
│           ├── ExtractionRunner.php           # Exécution SQL paramétrée + plafond
│           ├── ParameterResolver.php          # Validation et typage des paramètres
│           ├── QueryCompiler.php              # Compilation des requêtes paramétrées
│           ├── ResultStore.php                # Conservation 15 min des résultats
│           └── SqlGuard.php                   # Garde-fou anti-injection
│
├── config/
│   ├── database.php                           # Connexion Oracle configurée
│   └── extraction.php                         # max_rows, timeout, chemins JSON
│
├── database/
│   └── oracle-test/                           # Base de données de test Oracle
│       ├── 00_setup.sql                       # Script maître (lancer en SYSDBA)
│       ├── 01_utilisateurs.sql                # Création AMP_TEST + APP_RO
│       ├── 02_tables.sql                      # Tables métier (5 tables)
│       ├── 03_donnees.sql                     # 600 clients, 100K opérations, 400 crédits
│       ├── 04_droits.sql                      # SELECT sur les 5 tables pour APP_RO
│       ├── 05_verification.sql                # Vérification droits + test lecture/écriture
│       └── 06_requetes_mvp.sql                # Requêtes de référence B01-B07
│
├── docs/
│   ├── Charte_Projet_Extraction_BD_V1.2.docx
│   └── Architecture_Projet_Extraction_BD_V1.0.docx
│
├── resources/views/
│   ├── admin/                                 # Vues de l'administration technique
│   ├── components/                            # Composants Blade partagés
│   ├── metier/                                # Vues de l'espace métier
│   └── welcome.blade.php

│
├── routes/web.php
├── storage/app/private/extraction/
│   ├── queries.json                           # Catalogue des extractions
│   └── execution_log.json                     # Journal des executions
│
├── tests/
│   ├── Unit/SqlGuardTest.php                  # 17 cas de test SqlGuard
│   └── Feature/ExampleTest.php
│
├── tools/test_laravel_db.php
├── .env / .env.example
└── composer.json
```

---

## 6. Base de données Oracle de test

La base de test simule le schéma Amplitude. Elle est **100 % fictive**, reproductible (graine `DBMS_RANDOM.SEED(42)`) et ne contient aucune donnée réelle.

### Schéma AMP_TEST — Modèle entité-relation

```
AGENCE (1)----< CLIENT (1)----< COMPTE (1)----< OPERATION
                  |                |
                  +------< CREDIT <+
```

### Volumes de données

| Table | Lignes | Description |
|-------|--------|-------------|
| `agence` | 4 | Douala Akwa, Yaoundé Centre, Bafoussam, Garoua |
| `client` | 600 | ~84 % particuliers · ~14 % PME · ~2 % grandes entreprises |
| `compte` | ~800 | 1 courant/client + 1 épargne pour 1 client sur 3 |
| `operation` | 100 000 | Jan. 2025 → Sep. 2026 · 4 canaux : GUICHET, DAB, MOBILE, WEB |
| `credit` | 400 | 5 types d'engagement · 4 statuts |

### Description des tables

#### `agence`
```sql
agence_id     NUMBER(6)      PK
code_agence   VARCHAR2(10)   UNIQUE  -- AG001 à AG004
nom_agence    VARCHAR2(100)
ville         VARCHAR2(60)
date_ouverture DATE
```

#### `client`
```sql
client_id      NUMBER(10)    PK
num_client     VARCHAR2(12)  UNIQUE  -- format: CL00000001
nom            VARCHAR2(100)
prenom         VARCHAR2(100)
segment_client VARCHAR2(20)  -- 'PARTICULIER' | 'PME' | 'GRANDE_ENTREPRISE'
agence_id      NUMBER(6)     FK -> agence
date_creation  DATE
statut         VARCHAR2(10)  -- 'ACTIF' | 'INACTIF' | 'SUSPENDU'
```

#### `compte`
```sql
compte_id      NUMBER(10)    PK
num_compte     VARCHAR2(20)  UNIQUE  -- format: CM00000000000001
client_id      NUMBER(10)    FK -> client
agence_id      NUMBER(6)     FK -> agence
type_compte    VARCHAR2(10)  -- 'COURANT' | 'EPARGNE'
devise         VARCHAR2(3)   -- 'XAF' (défaut) ou 'EUR'
solde          NUMBER(18,2)
date_ouverture DATE
statut         VARCHAR2(10)  -- 'OUVERT' | 'BLOQUE' | 'CLOTURE'
```

#### `operation`
```sql
operation_id   NUMBER(12)    PK
compte_id      NUMBER(10)    FK -> compte
date_operation DATE
type_operation VARCHAR2(6)   -- 'DEBIT' | 'CREDIT'
montant        NUMBER(18,2)  -- toujours > 0
libelle        VARCHAR2(100)
canal          VARCHAR2(20)  -- GUICHET | DAB | MOBILE | WEB
```

> **Cas de test B06 :** les comptes dont `compte_id % 9 = 0` n'ont **aucun mouvement après mai 2025**.

#### `credit`
```sql
credit_id        NUMBER(10)   PK
num_dossier      VARCHAR2(14) UNIQUE  -- format: CR0000000001
client_id        NUMBER(10)   FK -> client
agence_id        NUMBER(6)    FK -> agence
type_engagement  VARCHAR2(20) -- CREDIT_CONSO | CREDIT_IMMO | CREDIT_TRESORERIE
                               -- CREDIT_EQUIPEMENT | CAUTION
montant_accorde  NUMBER(18,2)
encours          NUMBER(18,2) -- 0 si statut = SOLDE
devise           VARCHAR2(3)  -- 'XAF'
date_octroi      DATE
date_echeance    DATE
statut           VARCHAR2(12) -- 'EN_COURS' | 'SOLDE' | 'IMPAYE' | 'CONTENTIEUX'
```

### Comptes Oracle créés

| Compte | Rôle |
|--------|------|
| `AMP_TEST` | Propriétaire du schéma (CREATE TABLE, données fictives) |
| `APP_RO` | Compte applicatif Laravel — **SELECT uniquement sur les 5 tables** |

### Lancer la base de test

```powershell
# Depuis database/oracle-test/
set ORACLE_SID=XE
sqlplus -S "/ as sysdba" @00_setup.sql <MDP_AMP_TEST> <MDP_APP_RO>

# Exemple :
sqlplus -S "/ as sysdba" @00_setup.sql OracleOwner12 OracleLect12
```

### Vérifier les droits et volumes

```sql
-- Connecté en APP_RO
sqlplus -S app_ro/<MDP>@localhost:1521/XEPDB1 @05_verification.sql
```

Résultats attendus : 5 lignes SELECT dans `user_tab_privs`, lecture OK, écriture rejetée.

---

## 7. Extractions disponibles (MVP)

Toutes les requêtes utilisent des **bind variables** (`?` Laravel / `:var` SQL\*Plus). Le fichier [`06_requetes_mvp.sql`](database/oracle-test/06_requetes_mvp.sql) contient les requêtes de référence.

### B01 — Comptes ouverts par agence, période et statut
**Params :** `agence_id` · `date_debut` · `date_fin` · `statut` (OUVERT/BLOQUE/CLOTURE)  
**Colonnes :** `nom_agence`, `num_compte`, `type_compte`, `devise`, `date_ouverture`, `statut`

### B02 — Recherche client par agence, statut et nom
**Params :** `agence_id` · `statut` · `nom` (préfixe, ex. `TCHOUPO%`)  
**Colonnes :** `num_client`, `nom`, `prenom`, `segment_client`, `statut`  
> Recherche insensible à la casse, sécurisée contre les apostrophes (`N'GUEMA`) et les noms spéciaux (`MBA-ONDO`).

### B03 — Opérations par compte sur une période
**Params :** `num_compte` · `date_debut` · `date_fin`  
**Colonnes :** `date_operation`, `type_operation`, `montant`, `libelle`, `canal`

### B04 — Opérations par agence, période, seuil et type
**Params :** `agence_id` · `date_debut` · `date_fin` · `seuil` · `type_operation` (DEBIT/CREDIT)  
**Colonnes :** `nom_agence`, `operation_id`, `num_compte`, `date_operation`, `montant`, `canal`

### B05 — Crédits/engagements par agence, période, statut et type
**Params :** `agence_id` · `date_debut` · `date_fin` · `statut` · `type_engagement`  
**Colonnes :** `nom_agence`, `num_dossier`, `num_client`, `montant_accorde`, `encours`, `date_octroi`, `statut`

### B06 — Comptes sans mouvement depuis une date (Bonus)
**Params :** `agence_id` · `date_reference`  
**Résultat :** Nombre de comptes OUVERTS sans opération depuis la date de référence

### B07 — Clients PME actifs par agence (Bonus)
**Params :** `agence_id`  
**Résultat :** Nombre de clients PME actifs dans l'agence

### B08 — Soldes agrégés par agence et type de compte
**Params :** `agence_id`  
**Résultat :** Somme des soldes par type de compte (COURANT / EPARGNE)

---

## 8. Sécurité

La sécurité repose sur une **défense en profondeur** à 6 niveaux :

### Niveau 1 — Base de données (APP_RO)
Le compte Oracle `APP_RO` n'a que `CREATE SESSION` et `SELECT` sur les 5 tables. Oracle rejette toute écriture.

### Niveau 2 — SqlGuard (applicatif)
Le service `app/Services/Extraction/SqlGuard.php` refuse toute requête qui :
- Ne commence pas par `SELECT` ou `WITH`
- Contient un commentaire SQL (`--` ou `/* */`)
- Contient un point-virgule (multi-instructions)
- Contient `FOR UPDATE`
- Contient des mots-clés DML/DDL/DCL/PL-SQL : `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `GRANT`, `BEGIN`, `EXECUTE`, `DBMS_*`, `UTL_*`, etc.

### Niveau 3 — Bind variables
Toutes les requêtes SQL utilisent des **paramètres liés** (`?`). Aucune valeur utilisateur n'est concaténée dans le SQL.

### Niveau 4 — Validation Laravel
Chaque paramètre est validé par `Validator` : types, plages, listes fermées (`in:`), cohérence des dates (`after_or_equal`).

### Niveau 5 — Protection CSV (injection Excel)
Les valeurs commençant par `=`, `+`, `-`, `@` sont précédées d'une apostrophe (méthode `csvSafe()`).

### Niveau 6 — Plafond de résultats
`ExtractionRunner` limite automatiquement à `EXTRACTION_MAX_ROWS` lignes et signale les résultats tronqués.

---

## 9. Prérequis & Installation

### Prérequis

| Outil | Version requise | Rôle |
|---|---|---|
| **PHP** | 8.2+ (recommandé 8.3) avec extensions `pdo_sqlite`, `oci8` | Moteur applicatif |
| **Composer** | 2.x | Gestionnaire de dépendances PHP |
| **Oracle Database XE** | 21c (service `XEPDB1`) | Base de données métier (schéma `AMP_TEST`) |
| **Oracle Instant Client** | 21.x / 19.x | Bibliothèque cliente pour l'extension OCI8 |
| **Node.js & npm** | 20+ | Compilation des assets Vite & Alpine.js |

### Installation pas à pas

```bash
# 1. Cloner le dépôt et se positionner dans le dossier
git clone <url_du_depot> extraction-bd
cd extraction-bd

# 2. Installer les dépendances PHP
composer install

# 3. Installer les dépendances front-end et compiler les assets
npm install
npm run build

# 4. Configuration de l'environnement
cp .env.example .env
php artisan key:generate

# 5. Initialiser la base de données applicative SQLite
# (Note : cette migration concerne STRICTEMENT SQLite local, Oracle n'est jamais touché)
touch database/app.sqlite
php artisan migrate

# 6. Initialiser le catalogue d'extractions initial (si non existant)
mkdir -p storage/app/private/extraction
cp docs/design/catalogue/queries.example.json storage/app/private/extraction/queries.json

# 7. Créer un compte administrateur initial (mot de passe min. 12 caractères)
php artisan app:creer-utilisateur --identifiant=admin.technique --prenom=Admin --nom=Technique --role=admin --password=MotDePasseInitial12!

# 8. (Optionnel) Créer un compte utilisateur métier de test
php artisan app:creer-utilisateur --identifiant=sonia.d --prenom=Sonia --nom=D. --role=user --password=MotDePasseMetier12!
```

---

## 10. Configuration

### Configuration `.env` minimale

```dotenv
APP_NAME=ExtractionBD
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base applicative (SQLite local)
DB_CONNECTION=sqlite
DB_DATABASE=database/app.sqlite

# Connexion Oracle (Lecture seule APP_RO)
ORACLE_HOST=localhost
ORACLE_PORT=1521
ORACLE_DATABASE=XEPDB1
ORACLE_SERVICE_NAME=XEPDB1
ORACLE_USERNAME=APP_RO
ORACLE_PASSWORD=VotreMotDePasseAppRo
ORACLE_CHARSET=AL32UTF8

# Plafonds et délais de sécurité
EXTRACTION_MAX_ROWS=10000
EXTRACTION_TIMEOUT_SECONDS=60
```

---

## 11. Lancement de l'Application

```bash
# Lancement du serveur de développement
php artisan serve
```

L'application est immédiatement accessible sur `http://localhost:8000`.

| URL | Description | Rôle requis |
|---|---|---|
| `http://localhost:8000/login` | Page d'authentification unifiée | Public (invité) |
| `http://localhost:8000/dashboard` | Accueil de l'espace métier | `user` |
| `http://localhost:8000/catalogue` | Catalogue des extractions actives | `user` |
| `http://localhost:8000/mes-extractions` | Historique personnel et relance | `user` |
| `http://localhost:8000/admin` | Tableau de bord technique | `admin` |
| `http://localhost:8000/admin/extractions` | Gestion CRUD du catalogue d'extractions | `admin` |
| `http://localhost:8000/admin/logs` | Journal d'audit des exécutions et connexions | `admin` |

### Documentation et Guides disponibles
- [Guide Administrateur Technique](docs/guides/guide-administrateur.md)
- [Guide Utilisateur Métier](docs/guides/guide-utilisateur.md)
- [Checklist de Sécurité Applicative](docs/securite/checklist-securite.md)

---

## 12. Tests Automatisés

Le projet dispose d'une suite de **232 tests automatisés** garantissant le non-régression, la conformité de sécurité et l'intégrité fonctionnelle.

Trois suites de tests distinctes sont configurées dans `phpunit.xml` :

```bash
# 1. Suite Unit : tests unitaires sans dépendance externe (71 tests)
vendor\bin\phpunit --testsuite Unit

# 2. Suite Feature : tests applicatifs, matrice des rôles, sécurité, contrôleurs (145 tests)
vendor\bin\phpunit --testsuite Feature

# 3. Suite Oracle : tests de conformité des 8 extractions MVP sur Oracle XE (16 tests)
# (Nécessite Oracle XE local avec le compte APP_RO configuré)
vendor\bin\phpunit --testsuite Oracle

# 4. Exécution complète des suites applicatives (216 tests)
vendor\bin\phpunit --testsuite Unit,Feature

# 5. Vérification de la matrice rôles × routes (29 routes testées)
vendor\bin\phpunit tests/Feature/RoutesMatrixTest.php
```

---

## 13. Étapes de développement du MVP

L'ensemble des étapes du MVP ont été livrées et validées :

### ✅ Étape 1 — Initialisation du projet Laravel
- [x] Laravel 13 + PHP 8.x + `yajra/laravel-oci8`
- [x] Configuration `.env` Oracle XE (compte `APP_RO`)
- [x] Fichier de configuration `config/extraction.php`

### ✅ Étape 2 — Base de données Oracle de test
- [x] Schéma `AMP_TEST` (5 tables avec contraintes et index)
- [x] Jeu de données de test (600 clients, 100 000 opérations, 400 crédits)
- [x] Compte applicatif `APP_RO` : droits restreints à `SELECT` uniquement
- [x] Vérification d'intégrité et interdiction totale d'écriture

### ✅ Étape 3 — Couche sécurité Oracle
- [x] Validation du jeu de données (`setup.log`)
- [x] Tests d'injection SQL avec bind variables
- [x] Vérification de l'isolation du compte `APP_RO`

### ✅ Étape 4 — Services applicatifs
- [x] `SqlGuard` : analyseur syntaxique garantissant les requêtes en lecture seule
- [x] `QueryCompiler` : compilation des requêtes nommées vers des variables liées positionnelles
- [x] `ExtractionRunner` : exécution sécurisée avec plafond et gestion du timeout
- [x] `ExecutionLogger` : journalisation thread-safe (`flock`) avec protection volumétrique
- [x] `AuthLogger` : journalisation d'audit des connexions

### ✅ Étape 5 — Tests unitaires SqlGuard & Services
- [x] Tests d'acceptation et de rejet `SqlGuard`
- [x] Tests de compilation `QueryCompiler` (gestion des apostrophes doublées)

### ✅ Étape 6 — Gestion des Résultats et Export CSV
- [x] `ResultStore` : stockage temporaire des résultats pendant 15 minutes avec jeton unique
- [x] `CsvExporter` : streaming CSV avec BOM UTF-8 et neutralisation des formules Excel
- [x] Commande Artisan `app:purge-resultats` pour le nettoyage automatisé

### ✅ Étape 7 — Authentification et Gestion des Rôles
- [x] Authentification par identifiant unique (`prenom.nom`) et mot de passe (min. 12 car.)
- [x] Protection contre les attaques par force brute (5 tentatives/min)
- [x] Deux rôles étanches : `admin` et `user`
- [x] Matrice automatisée rôles × routes (`RoutesMatrixTest`)

### ✅ Étape 8 — Catalogue d'Extractions JSON
- [x] Format structuré conforme au schéma `queries.schema.json`
- [x] `CatalogueRepository` : lecture et écriture sécurisée du catalogue
- [x] Implémentation complète des extractions de référence B01 à B08

### ✅ Étape 9 — Espace d'Administration Technique
- [x] Liste des extractions avec recherche et filtres par statut (`/admin/extractions`)
- [x] Formulaire complet de création/édition d'extractions (`/admin/extractions/create`)
- [x] Cycle de vie garanti : création/modification en `brouillon` -> test live obligatoire -> `actif`
- [x] Module de test en direct avec aperçu des 100 premières lignes et `COUNT(*)` exact (D21)
- [x] Désactivation et réactivation sans suppression physique (D10)
- [x] Journal d'activité d'administration avec filtres et export CSV (`/admin/logs`)

### ✅ Étape 10 — Espace Utilisateur Métier
- [x] Tableau de bord d'accueil (`/dashboard`)
- [x] Catalogue avec recherche instantanée insensible à la casse et aux accents (`/catalogue`)
- [x] Formulaire paramétrique auto-généré selon les types de champs (`/extractions/{code}`)
- [x] Tableau paginé de résultats (50 lignes/page) avec avertissement de plafond atteint (D21)
- [x] Téléchargement immédiat en CSV compatible Microsoft Excel
- [x] Historique personnel avec bouton « Relancer » et critères pré-remplis (`/mes-extractions`)

### ✅ Étape 11 — Design System & Interface Utilisateur
- [x] Respect rigoureux des maquettes Figma et tokens CSS (`design-tokens.md`)
- [x] Palette de couleurs BCPME, typographies et composants partagés (cartes, pastilles, tables)
- [x] Composant d'icône SVG `x-icon` sans dépendance externe
- [x] Interactivité réactive Alpine.js sans build lourd

### ✅ Étape 12 — Qualité, Sécurité et Documentation
- [x] 232 tests automatisés (Unit, Feature, Oracle)
- [x] En-têtes HTTP de sécurité (`nosniff`, `frame-ancestors 'none'`, CSP stricte sans CDN)
- [x] Protection CSRF sur toutes les requêtes modifiantes
- [x] Protection XSS validée (Blade + JSON Hex dans Alpine)
- [x] Inaccessibilité des fichiers sensibles (`.env`, `queries.json`, `app.sqlite`, journaux)
- [x] Rate limiting sur l'authentification (5/min) et les exécutions/exports (30/min)
- [x] Audit des dépendances vierge (`composer audit`, `npm audit`)
- [x] Revue critique des 10 points sensibles et application des correctifs retenus
- [x] Rédaction du README final, des guides d'utilisation et de la checklist de sécurité

---

## 14. Glossaire

| Terme | Définition |
|---|---|
| **Amplitude** | Core Banking System Oracle de l'établissement |
| **AMP_TEST** | Schéma Oracle de test (données 100 % fictives) |
| **APP_RO** | Compte Oracle applicatif Laravel — `SELECT` uniquement |
| **Bind variable** | Paramètre lié (`?`) transmis séparément du SQL — prévient toute injection SQL |
| **BOM UTF-8** | `\xEF\xBB\xBF` — en-tête CSV garantissant l'ouverture sans distorsion d'accents dans Excel |
| **Catalogue** | `queries.json` — fichier répertoriant l'ensemble des définitions d'extractions |
| **DOSI** | Direction de l'Organisation et des Systèmes d'Information |
| **Extraction** | Requête SQL paramétrée et publiée dans le catalogue |
| **OCI8** | Extension native PHP pour la communication haute performance avec Oracle Database |
| **Plafond** | Volume maximal de lignes retournées par extraction (`EXTRACTION_MAX_ROWS`, défaut 10 000) |
| **SqlGuard** | Service applicatif vérifiant que chaque requête est strictement en lecture seule |

---

*Plateforme Extraction BD — BCPME — 2026 | Laravel 13 + Oracle XE + PHP 8.x*

