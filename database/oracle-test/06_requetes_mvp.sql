-- Requetes de reference des 4 extractions du MVP, avec VARIABLES LIEES (bind variables)
-- Elles preparent le contenu futur de queries.json.
-- A lancer connecte en APP_RO :
--   sqlplus -S app_ro/<MDP_LECTURE>@localhost:1521/XEPDB1 @06_requetes_mvp.sql
-- total_lignes = nombre total de lignes du resultat, meme si l'apercu n'en montre que 5
-- (utile pour le futur plafond de resultats).
SET LINESIZE 200
SET PAGESIZE 60
SET FEEDBACK ON
COLUMN nom_agence FORMAT A22
COLUMN num_compte FORMAT A18
COLUMN num_client FORMAT A12
COLUMN nom FORMAT A16
COLUMN prenom FORMAT A10
COLUMN num_dossier FORMAT A14
COLUMN type_engagement FORMAT A18
WHENEVER SQLERROR CONTINUE

VARIABLE agence   NUMBER
VARIABLE d1       VARCHAR2(10)
VARIABLE d2       VARCHAR2(10)
VARIABLE statut   VARCHAR2(12)
VARIABLE nom      VARCHAR2(100)
VARIABLE num      VARCHAR2(12)
VARIABLE seuil    NUMBER
VARIABLE type_op  VARCHAR2(6)
VARIABLE type_eng VARCHAR2(20)

PROMPT ===== B01 : comptes ouverts par agence, periode et statut =====
EXEC :agence := 1
EXEC :d1 := '2018-01-01'
EXEC :d2 := '2023-12-31'
EXEC :statut := 'OUVERT'
SELECT a.nom_agence, c.num_compte, c.type_compte, c.devise, c.date_ouverture, c.statut,
       COUNT(*) OVER () AS total_lignes
FROM amp_test.compte c
JOIN amp_test.agence a ON a.agence_id = c.agence_id
WHERE c.agence_id = :agence
  AND c.date_ouverture BETWEEN TO_DATE(:d1, 'YYYY-MM-DD') AND TO_DATE(:d2, 'YYYY-MM-DD')
  AND c.statut = :statut
ORDER BY c.date_ouverture
FETCH FIRST 5 ROWS ONLY;

PROMPT ===== B02 : recherche client par agence, statut et nom (debut du nom) =====
EXEC :agence := 4
EXEC :statut := 'ACTIF'
EXEC :nom := 'TCHOUPO%'
SELECT c.num_client, c.nom, c.prenom, c.segment_client, c.statut, COUNT(*) OVER () AS total_lignes
FROM amp_test.client c
WHERE c.agence_id = :agence
  AND c.statut = :statut
  AND UPPER(c.nom) LIKE UPPER(:nom)
ORDER BY c.nom, c.prenom
FETCH FIRST 5 ROWS ONLY;

PROMPT ----- B02 (variante) : recherche par identifiant client exact
EXEC :num := 'CL00000011'
SELECT c.num_client, c.nom, c.prenom, c.agence_id, c.statut
FROM amp_test.client c
WHERE c.num_client = :num;

PROMPT ----- B02 (test securite 1) : nom avec apostrophe, doit trouver N'GUEMA
EXEC :agence := 4
EXEC :statut := 'ACTIF'
EXEC :nom := 'N''GUEMA%'
SELECT c.num_client, c.nom, c.agence_id FROM amp_test.client c
WHERE c.agence_id = :agence AND c.statut = :statut AND UPPER(c.nom) LIKE UPPER(:nom);

PROMPT ----- B02 (test securite 2) : tentative d'injection, doit renvoyer 0 ligne
EXEC :nom := 'X'' OR ''1''=''1'
SELECT c.num_client, c.nom, c.agence_id FROM amp_test.client c
WHERE c.agence_id = :agence AND c.statut = :statut AND UPPER(c.nom) LIKE UPPER(:nom);

PROMPT ===== B04 : operations par agence, periode, seuil de montant et type =====
EXEC :agence := 1
EXEC :d1 := '2025-01-01'
EXEC :d2 := '2026-09-30'
EXEC :seuil := 100000
EXEC :type_op := 'DEBIT'
SELECT a.nom_agence, o.operation_id, c.num_compte, o.date_operation, o.type_operation, o.montant, o.canal,
       COUNT(*) OVER () AS total_lignes
FROM amp_test.operation o
JOIN amp_test.compte c ON c.compte_id = o.compte_id
JOIN amp_test.agence a ON a.agence_id = c.agence_id
WHERE c.agence_id = :agence
  AND o.date_operation BETWEEN TO_DATE(:d1, 'YYYY-MM-DD') AND TO_DATE(:d2, 'YYYY-MM-DD')
  AND o.montant >= :seuil
  AND o.type_operation = :type_op
ORDER BY o.montant DESC
FETCH FIRST 5 ROWS ONLY;

PROMPT ----- B04 (test volume) : seuil 0 sur toute la periode, total_lignes doit depasser 5000
EXEC :seuil := 0
SELECT COUNT(*) AS total_lignes
FROM amp_test.operation o
JOIN amp_test.compte c ON c.compte_id = o.compte_id
WHERE c.agence_id = :agence
  AND o.date_operation BETWEEN TO_DATE(:d1, 'YYYY-MM-DD') AND TO_DATE(:d2, 'YYYY-MM-DD')
  AND o.montant >= :seuil;

PROMPT ===== B05 : credits / engagements par agence, periode, statut et type =====
EXEC :agence := 1
EXEC :d1 := '2022-01-01'
EXEC :d2 := '2026-09-30'
EXEC :statut := 'EN_COURS'
EXEC :type_eng := 'CREDIT_CONSO'
SELECT a.nom_agence, cr.num_dossier, cl.num_client, cr.type_engagement, cr.montant_accorde, cr.encours,
       cr.date_octroi, cr.date_echeance, cr.statut, COUNT(*) OVER () AS total_lignes
FROM amp_test.credit cr
JOIN amp_test.agence a  ON a.agence_id = cr.agence_id
JOIN amp_test.client cl ON cl.client_id = cr.client_id
WHERE cr.agence_id = :agence
  AND cr.date_octroi BETWEEN TO_DATE(:d1, 'YYYY-MM-DD') AND TO_DATE(:d2, 'YYYY-MM-DD')
  AND cr.statut = :statut
  AND cr.type_engagement = :type_eng
ORDER BY cr.date_octroi
FETCH FIRST 5 ROWS ONLY;

PROMPT ===== Bonus : le schema couvre aussi B06 (comptes sans mouvement) et B07 (clients PME) =====
EXEC :agence := 1
EXEC :d1 := '2025-07-01'
SELECT COUNT(*) AS comptes_sans_mouvement_depuis_juillet_2025
FROM amp_test.compte c
WHERE c.agence_id = :agence
  AND c.statut = 'OUVERT'
  AND NOT EXISTS (SELECT 1 FROM amp_test.operation o
                  WHERE o.compte_id = c.compte_id
                    AND o.date_operation >= TO_DATE(:d1, 'YYYY-MM-DD'));

EXEC :statut := 'ACTIF'
SELECT COUNT(*) AS clients_pme_actifs
FROM amp_test.client c
WHERE c.agence_id = :agence AND c.segment_client = 'PME' AND c.statut = :statut;

EXIT
