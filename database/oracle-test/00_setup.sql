-- =====================================================================
-- Projet Extraction BD - Etape 3 : schema de test + compte lecture seule
-- Lancement (depuis ce dossier) :
--   set ORACLE_SID=XE
--   sqlplus -S "/ as sysdba" @00_setup.sql <MDP_PROPRIETAIRE> <MDP_LECTURE>
-- 1er argument = mot de passe du schema AMP_TEST (proprietaire des donnees)
-- 2e argument = mot de passe du compte APP_RO (lecture seule, utilise par Laravel)
-- Le script peut etre relance : il recree tout a zero.
-- =====================================================================
SET ECHO OFF
SET VERIFY OFF
SET FEEDBACK ON
SET LINESIZE 120
SET PAGESIZE 50
WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK
SPOOL setup.log

ALTER SESSION SET CONTAINER = XEPDB1;

@@01_utilisateurs.sql
@@02_tables.sql
@@03_donnees.sql
@@04_droits.sql

SPOOL OFF
EXIT
