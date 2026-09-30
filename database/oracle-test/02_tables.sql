-- Schema de test AMP_TEST : structure deduite du fichier de cadrage des besoins
-- (MVP : B01 comptes, B02 clients, B04 operations, B05 credits/engagements)
-- Donnees 100 % fictives. La structure reelle d'Amplitude sera adaptee apres accord de la DOSI.
ALTER SESSION SET CURRENT_SCHEMA = amp_test;

CREATE TABLE agence (
  agence_id       NUMBER(6)      CONSTRAINT pk_agence PRIMARY KEY,
  code_agence     VARCHAR2(10)   NOT NULL CONSTRAINT uq_agence_code UNIQUE,
  nom_agence      VARCHAR2(100)  NOT NULL,
  ville           VARCHAR2(60)   NOT NULL,
  date_ouverture  DATE
);

-- Besoins B02 (identite, statut) et B07 (segment PME)
CREATE TABLE client (
  client_id       NUMBER(10)     CONSTRAINT pk_client PRIMARY KEY,
  num_client      VARCHAR2(12)   NOT NULL CONSTRAINT uq_client_num UNIQUE,
  nom             VARCHAR2(100)  NOT NULL,
  prenom          VARCHAR2(100),
  segment_client  VARCHAR2(20)   NOT NULL
                  CONSTRAINT ck_client_segment CHECK (segment_client IN ('PARTICULIER', 'PME', 'GRANDE_ENTREPRISE')),
  agence_id       NUMBER(6)      NOT NULL
                  CONSTRAINT fk_client_agence REFERENCES agence (agence_id),
  date_creation   DATE           NOT NULL,
  statut          VARCHAR2(10)   DEFAULT 'ACTIF' NOT NULL
                  CONSTRAINT ck_client_statut CHECK (statut IN ('ACTIF', 'INACTIF', 'SUSPENDU'))
);

-- Besoins B01 (comptes ouverts par agence/periode/statut), B06 et B08
CREATE TABLE compte (
  compte_id       NUMBER(10)     CONSTRAINT pk_compte PRIMARY KEY,
  num_compte      VARCHAR2(20)   NOT NULL CONSTRAINT uq_compte_num UNIQUE,
  client_id       NUMBER(10)     NOT NULL
                  CONSTRAINT fk_compte_client REFERENCES client (client_id),
  agence_id       NUMBER(6)      NOT NULL
                  CONSTRAINT fk_compte_agence REFERENCES agence (agence_id),
  type_compte     VARCHAR2(10)   NOT NULL
                  CONSTRAINT ck_compte_type CHECK (type_compte IN ('COURANT', 'EPARGNE')),
  devise          VARCHAR2(3)    DEFAULT 'XAF' NOT NULL,
  solde           NUMBER(18,2)   DEFAULT 0 NOT NULL,
  date_ouverture  DATE           NOT NULL,
  statut          VARCHAR2(10)   DEFAULT 'OUVERT' NOT NULL
                  CONSTRAINT ck_compte_statut CHECK (statut IN ('OUVERT', 'BLOQUE', 'CLOTURE'))
);

-- Besoins B03, B04 (agence + periode + seuil de montant + type) et B06
CREATE TABLE operation (
  operation_id    NUMBER(12)     CONSTRAINT pk_operation PRIMARY KEY,
  compte_id       NUMBER(10)     NOT NULL
                  CONSTRAINT fk_operation_compte REFERENCES compte (compte_id),
  date_operation  DATE           NOT NULL,
  type_operation  VARCHAR2(6)    NOT NULL
                  CONSTRAINT ck_operation_type CHECK (type_operation IN ('DEBIT', 'CREDIT')),
  montant         NUMBER(18,2)   NOT NULL CONSTRAINT ck_operation_montant CHECK (montant > 0),
  libelle         VARCHAR2(100),
  canal           VARCHAR2(20)
);

-- Besoin B05 (credits / engagements par agence, periode, statut et type)
CREATE TABLE credit (
  credit_id        NUMBER(10)    CONSTRAINT pk_credit PRIMARY KEY,
  num_dossier      VARCHAR2(14)  NOT NULL CONSTRAINT uq_credit_dossier UNIQUE,
  client_id        NUMBER(10)    NOT NULL
                   CONSTRAINT fk_credit_client REFERENCES client (client_id),
  agence_id        NUMBER(6)     NOT NULL
                   CONSTRAINT fk_credit_agence REFERENCES agence (agence_id),
  type_engagement  VARCHAR2(20)  NOT NULL
                   CONSTRAINT ck_credit_type CHECK (type_engagement IN
                     ('CREDIT_CONSO', 'CREDIT_IMMO', 'CREDIT_TRESORERIE', 'CREDIT_EQUIPEMENT', 'CAUTION')),
  montant_accorde  NUMBER(18,2)  NOT NULL,
  encours          NUMBER(18,2)  NOT NULL,
  devise           VARCHAR2(3)   DEFAULT 'XAF' NOT NULL,
  date_octroi      DATE          NOT NULL,
  date_echeance    DATE          NOT NULL,
  statut           VARCHAR2(12)  NOT NULL
                   CONSTRAINT ck_credit_statut CHECK (statut IN ('EN_COURS', 'SOLDE', 'IMPAYE', 'CONTENTIEUX'))
);

CREATE INDEX ix_client_agence          ON client (agence_id, statut);
CREATE INDEX ix_client_nom             ON client (UPPER(nom));
CREATE INDEX ix_compte_client          ON compte (client_id);
CREATE INDEX ix_compte_agence_date     ON compte (agence_id, date_ouverture);
CREATE INDEX ix_operation_compte_date  ON operation (compte_id, date_operation);
CREATE INDEX ix_operation_date         ON operation (date_operation);
CREATE INDEX ix_credit_client          ON credit (client_id);
CREATE INDEX ix_credit_agence_date     ON credit (agence_id, date_octroi);
