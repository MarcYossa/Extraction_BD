-- A lancer connecte en APP_RO :
--   sqlplus -S app_ro/<MDP_LECTURE>@localhost:1521/XEPDB1 @05_verification.sql
SET LINESIZE 130
SET PAGESIZE 60
SET FEEDBACK ON
COLUMN owner FORMAT A12
COLUMN table_name FORMAT A14
COLUMN privilege FORMAT A20
WHENEVER SQLERROR CONTINUE

SHOW USER;

PROMPT
PROMPT --- 1) Droits du compte : 5 lignes SELECT attendues, et CREATE SESSION seul
SELECT owner, table_name, privilege FROM user_tab_privs ORDER BY table_name;
SELECT privilege FROM session_privs;

PROMPT
PROMPT --- 2) Lecture : doit fonctionner
SELECT a.nom_agence, COUNT(*) AS nb_comptes
FROM amp_test.compte c
JOIN amp_test.agence a ON a.agence_id = c.agence_id
GROUP BY a.nom_agence
ORDER BY nb_comptes DESC;

PROMPT
PROMPT --- 3) Ecriture : les 5 commandes suivantes doivent TOUTES echouer
INSERT INTO amp_test.agence (agence_id, code_agence, nom_agence, ville) VALUES (999, 'X', 'X', 'X');
UPDATE amp_test.compte SET solde = 0 WHERE compte_id = 1;
DELETE FROM amp_test.operation WHERE operation_id = 1;
TRUNCATE TABLE amp_test.credit;
CREATE TABLE test_ecriture (id NUMBER);

EXIT
