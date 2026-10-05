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
│   │   └── Controllers/
│   │       ├── Controller.php
│   │       └── SocleTestController.php       # Temporaire (validation socle)
│   ├── Models/
│   │   └── User.php
│   └── Services/
│       └── Extraction/
│           ├── ExecutionLogger.php            # Journal JSON des executions
│           ├── ExtractionRunner.php           # Execution SQL parametree + plafond
│           └── SqlGuard.php                   # Garde-fou anti-injection
│
├── config/
│   ├── database.php                           # Connexion Oracle configuree
│   └── extraction.php                         # max_rows, timeout, chemins JSON
│
├── database/
│   └── oracle-test/                           # Base de donnees de test Oracle
│       ├── 00_setup.sql                       # Script maitre (lancer en SYSDBA)
│       ├── 01_utilisateurs.sql                # Creation AMP_TEST + APP_RO
│       ├── 02_tables.sql                      # Tables metier (5 tables)
│       ├── 03_donnees.sql                     # 600 clients, 100K operations, 400 credits
│       ├── 04_droits.sql                      # SELECT sur les 5 tables pour APP_RO
│       ├── 05_verification.sql                # Verification droits + test lecture/ecriture
│       └── 06_requetes_mvp.sql                # Requetes de reference B01-B07
│
├── docs/
│   ├── Charte_Projet_Extraction_BD_V1.2.docx
│   └── Architecture_Projet_Extraction_BD_V1.0.docx
│
├── resources/views/
│   ├── socle-test.blade.php                   # Vue temporaire (validation socle B01)
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

| Outil | Version |
|-------|---------|
| PHP | 8.2+ |
| Composer | 2.x |
| Oracle Instant Client | 21.x (pour OCI8) |
| Oracle Database XE | 21c (XEPDB1) |
| Node.js + npm | 20+ |

### Installation

```bash
# 1. Cloner le dépôt
git clone <url> extraction-bd && cd extraction-bd

# 2. Dépendances PHP
composer install

# 3. Dépendances JavaScript
npm install

# 4. Configuration
cp .env.example .env
php artisan key:generate

# 5. Créer la base Oracle de test
cd database/oracle-test
sqlplus -S "/ as sysdba" @00_setup.sql <MDP_AMP_TEST> <MDP_APP_RO>
cd ../..

# 6. Vérifier la connexion Oracle
php tools/test_laravel_db.php
```

---

## 10. Configuration

### Variables `.env` clés

```dotenv
APP_NAME=ExtractionBD
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=oracle
DB_HOST=localhost
DB_PORT=1521
DB_DATABASE=XEPDB1
DB_USERNAME=APP_RO
DB_PASSWORD=<votre_mdp>
DB_CHARSET=AL32UTF8
DB_SERVER_VERSION=12c

EXTRACTION_MAX_ROWS=10000
EXTRACTION_TIMEOUT_SECONDS=60
```

---

## 11. Lancement en développement

```bash
# Terminal 1 : serveur PHP
php artisan serve

# Terminal 2 : assets (si modifiés)
npm run dev
```

| URL | Description |
|-----|-------------|
| `http://localhost:8000` | Page d'accueil |
| `http://localhost:8000/socle-test` | Test du socle B01 (local uniquement) |
| `http://localhost:8000/socle-test/csv?agence=1&date_debut=2018-01-01&date_fin=2023-12-31&statut=OUVERT` | Export CSV B01 |

---

## 12. Tests

```bash
# Tous les tests
php artisan test

# Unitaires seuls (pas d'Oracle requis)
php artisan test --testsuite=Unit

# Feature (Oracle requis)
php artisan test --testsuite=Feature
```

| Fichier | Cas | Description |
|---------|-----|-------------|
| `tests/Unit/SqlGuardTest.php` | 17 | 5 requêtes autorisées + 12 refusées |
| `tests/Feature/ExampleTest.php` | 1 | Test de base |

---

## 13. Étapes de développement du MVP

> Les étapes **1 à 7** sont déjà réalisées (socle technique validé).

---

### ✅ Étape 1 — Initialisation du projet Laravel
- [x] Laravel 13 + PHP 8.x + yajra/laravel-oci8
- [x] Configuration `.env` Oracle XE
- [x] `config/extraction.php`

### ✅ Étape 2 — Base de données Oracle de test
- [x] `01_utilisateurs.sql` — AMP_TEST + APP_RO
- [x] `02_tables.sql` — 5 tables avec contraintes et index
- [x] `03_donnees.sql` — 600 clients, 100 000 opérations, 400 crédits
- [x] `04_droits.sql` — SELECT uniquement pour APP_RO
- [x] `05_verification.sql` — vérification droits
- [x] `06_requetes_mvp.sql` — requêtes B01–B07 avec bind variables

### ✅ Étape 3 — Couche sécurité Oracle
- [x] Validation du jeu de données (setup.log)
- [x] Tests d'injection SQL avec bind variables en SQL*Plus
- [x] Vérification écriture impossible via APP_RO

### ✅ Étape 4 — Services applicatifs
- [x] `SqlGuard` — filtre SELECT uniquement
- [x] `ExtractionRunner` — exécution paramétrée + plafond
- [x] `ExecutionLogger` — journalisation JSON thread-safe

### ✅ Étape 5 — Tests unitaires SqlGuard
- [x] 17 cas de test (requêtes autorisées + refusées)

### ✅ Étape 6 — Contrôleur + vue de validation du socle
- [x] `SocleTestController` — formulaire B01, validation, affichage, CSV
- [x] Vue `socle-test.blade.php` — formulaire + tableau de résultats
- [x] Routes `/socle-test` et `/socle-test/csv`

### ✅ Étape 7 — Validation du socle technique
- [x] Connexion Oracle Laravel ↔ Oracle XE confirmée
- [x] Extraction B01 fonctionnelle avec plafond
- [x] Export CSV fonctionnel (BOM UTF-8)
- [x] Journal d'exécution JSON opérationnel

---

### 🔲 Étape 8 — Authentification et gestion des rôles

**Objectif :** Sécuriser l'accès avec deux rôles : `admin` et `user`.

**Actions :**
- [ ] Migration `create_users_table` + colonne `role` (`admin`/`user`) + colonne `agence_id` optionnelle
- [ ] Seeder de l'administrateur par défaut
- [ ] Formulaire de connexion `/login` avec CSRF
- [ ] Middleware `EnsureAuthenticated` — protège toutes les routes authentifiées
- [ ] Middleware `EnsureIsAdmin` — protège les routes `/admin/*`
- [ ] Déconnexion automatique après inactivité (`SESSION_LIFETIME`)
- [ ] Journalisation des connexions (succès et échecs)

**Fichiers à créer :**
```
database/migrations/YYYY_create_users_table.php
database/seeders/AdminUserSeeder.php
app/Http/Middleware/EnsureIsAdmin.php
app/Http/Controllers/Auth/LoginController.php
resources/views/auth/login.blade.php
```

---

### 🔲 Étape 9 — Catalogue d'extractions JSON

**Objectif :** Définir le format et le service de gestion du catalogue `queries.json`.

**Format `storage/app/private/extraction/queries.json` :**
```json
[
  {
    "id": "B01",
    "titre": "Comptes ouverts par agence, période et statut",
    "description": "Liste les comptes d'une agence ouverts sur une période donnée.",
    "publie": true,
    "sql": "SELECT a.nom_agence, c.num_compte ... WHERE c.agence_id = ? AND ...",
    "parametres": [
      { "nom": "agence",     "label": "Agence",        "type": "integer", "required": true },
      { "nom": "date_debut", "label": "Date de début", "type": "date",    "required": true },
      { "nom": "date_fin",   "label": "Date de fin",   "type": "date",    "required": true },
      { "nom": "statut",     "label": "Statut",        "type": "select",  "required": true,
        "options": ["OUVERT", "BLOQUE", "CLOTURE"] }
    ]
  }
]
```

**Actions :**
- [ ] `QueryCatalog.php` — lecture/écriture du JSON + validation du schéma
- [ ] `QueryBuilder.php` — construction de la requête SQL depuis le catalogue
- [ ] Enrichir `ExtractionRunner` pour accepter un objet `QueryDefinition`
- [ ] Créer `queries.json` avec les 8 extractions B01–B08

**Fichiers à créer :**
```
app/Services/Extraction/QueryCatalog.php
app/Services/Extraction/QueryBuilder.php
storage/app/private/extraction/queries.json
```

---

### 🔲 Étape 10 — Espace administration

**Objectif :** Interface CRUD pour gérer le catalogue d'extractions.

**Routes à créer :**
| Méthode | URL | Action |
|---------|-----|--------|
| GET | `/admin/extractions` | Liste des extractions |
| GET\|POST | `/admin/extractions/create` | Créer une extraction |
| GET\|PUT | `/admin/extractions/{id}/edit` | Modifier une extraction |
| POST | `/admin/extractions/{id}/test` | Tester en live |
| PATCH | `/admin/extractions/{id}/toggle` | Publier / Dépublier |
| DELETE | `/admin/extractions/{id}` | Supprimer |
| GET | `/admin/logs` | Journal des exécutions |

**Fichiers à créer :**
```
app/Http/Controllers/Admin/ExtractionAdminController.php
resources/views/admin/extractions/index.blade.php
resources/views/admin/extractions/create.blade.php
resources/views/admin/extractions/edit.blade.php
resources/views/admin/logs/index.blade.php
```

---

### 🔲 Étape 11 — Espace utilisateur métier

**Objectif :** Interface permettant à un utilisateur authentifié de lancer des extractions.

**Routes à créer :**
| Méthode | URL | Action |
|---------|-----|--------|
| GET | `/dashboard` | Tableau de bord (extractions publiées) |
| GET | `/extractions/{id}` | Formulaire paramétrique (auto-généré) |
| POST | `/extractions/{id}/execute` | Exécuter + afficher résultats |
| GET | `/extractions/{id}/csv` | Télécharger CSV |
| GET | `/mes-extractions` | Historique personnel |

**Fichiers à créer :**
```
app/Http/Controllers/ExtractionController.php
resources/views/dashboard.blade.php
resources/views/extractions/show.blade.php
resources/views/extractions/result.blade.php
resources/views/mes-extractions.blade.php
```

---

### 🔲 Étape 12 — Interface utilisateur (UI/UX)

**Objectif :** Interface web propre, professionnelle et responsive.

- [ ] Layout principal `layouts/app.blade.php` (header, navigation, footer)
- [ ] Layout admin `layouts/admin.blade.php`
- [ ] Intégration Alpine.js (toggle, confirmations, affichage conditionnel)
- [ ] Page de connexion avec design professionnel
- [ ] Tableau de résultats avec pagination côté client
- [ ] Indicateur de chargement pendant l'exécution Oracle
- [ ] Bannière d'avertissement si résultat tronqué
- [ ] Design responsive (mobile first)
- [ ] Favicon et métadonnées SEO

---

### 🔲 Étape 13 — Tests complets

**Objectif :** Couverture de tests complète pour valider le MVP.

- [ ] `Unit/QueryCatalogTest.php` — lecture, écriture, validation schéma JSON
- [ ] `Unit/QueryBuilderTest.php` — construction SQL avec paramètres valides/invalides
- [ ] `Unit/ExtractionRunnerTest.php` — plafond, détection truncation
- [ ] `Feature/AuthTest.php` — connexion, déconnexion, accès refusé sans auth
- [ ] `Feature/ExtractionB01Test.php` — exécution B01 complète (Oracle requis)
- [ ] `Feature/AdminExtractionTest.php` — CRUD extractions (admin seul)
- [ ] `Feature/CsvExportTest.php` — BOM, headers, protection injection Excel
- [ ] `Feature/ExecutionLogTest.php` — enregistrement journal

```bash
php artisan test --coverage
```

---

### 🔲 Étape 14 — Préparation à la mise en production (Amplitude)

**Objectif :** Connecter la plateforme à la base Amplitude réelle après accord DOSI.

- [ ] Documenter le schéma cible Amplitude (colonnes, types) avec la DOSI
- [ ] Adapter les requêtes SQL du catalogue (`AMP_TEST.*` → `SCHEMA_AMPLITUDE.*`)
- [ ] Externaliser les noms de schéma dans `.env` (`DB_SCHEMA=AMP_TEST`)
- [ ] Tester les requêtes en environnement de recette sur Amplitude
- [ ] Activer HTTPS (certificat SSL, reverse proxy)
- [ ] Configurer timeout Oracle selon SLA DOSI
- [ ] Rédiger le guide d'exploitation
- [ ] Former les administrateurs et utilisateurs métier
- [ ] Validation finale DOSI

---

## 14. Glossaire

| Terme | Définition |
|-------|------------|
| **Amplitude** | Core Banking System Oracle de l'établissement |
| **AMP_TEST** | Schéma Oracle de test (données 100 % fictives) |
| **APP_RO** | Compte Oracle applicatif Laravel — SELECT uniquement |
| **Bind variable** | Paramètre lié (`?`) transmis séparément du SQL — prévient l'injection |
| **BOM UTF-8** | `\xEF\xBB\xBF` — en-tête CSV pour ouverture correcte dans Excel |
| **CBS** | Core Banking System — logiciel central de gestion bancaire |
| **Catalogue** | `queries.json` — liste des extractions disponibles |
| **DOSI** | Direction/Département de l'Organisation et des Systèmes d'Information |
| **Extraction** | Requête SQL paramétrée publiée dans le catalogue |
| **Journal** | `execution_log.json` — chaque exécution enregistrée (qui, quoi, quand, volume) |
| **OCI8** | Extension PHP pour Oracle (`yajra/laravel-oci8`) |
| **Plafond** | Nombre max de lignes retournées (`EXTRACTION_MAX_ROWS`) |
| **PME** | Petite et Moyenne Entreprise — segment client |
| **SqlGuard** | Service Laravel filtrant les requêtes (SELECT uniquement) |
| **XEPDB1** | Pluggable Database Oracle Express Edition (développement) |
| **XAF** | Franc CFA — devise principale des comptes |

---

*Projet de stage — BCPME — 2026 | Laravel 13 + Oracle XE + PHP 8.x*
