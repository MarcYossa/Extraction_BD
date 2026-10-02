-- Jeu de donnees FICTIF, reproductible (graine fixe). Aucune donnee reelle.
ALTER SESSION SET CURRENT_SCHEMA = amp_test;
EXEC DBMS_RANDOM.SEED(42);

-- 4 agences
INSERT INTO agence VALUES (1, 'AG001', 'Agence Douala Akwa',     'Douala',    DATE '2010-03-15');
INSERT INTO agence VALUES (2, 'AG002', 'Agence Yaounde Centre',  'Yaounde',   DATE '2011-06-01');
INSERT INTO agence VALUES (3, 'AG003', 'Agence Bafoussam',       'Bafoussam', DATE '2013-09-20');
INSERT INTO agence VALUES (4, 'AG004', 'Agence Garoua',          'Garoua',    DATE '2014-02-10');

-- 600 clients : ~14 % de PME, 2 % de grandes entreprises, le reste en particuliers
INSERT INTO client (client_id, num_client, nom, prenom, segment_client, agence_id, date_creation, statut)
SELECT lv,
       'CL' || LPAD(lv, 8, '0'),
       CASE WHEN MOD(lv, 50) = 0 THEN 'SA '
            WHEN MOD(lv, 7)  = 0 THEN 'ETS ' END ||
         DECODE(MOD(lv * 7, 10), 0, 'NGOUKAM', 1, 'TCHOUPO', 2, 'MBARGA', 3, 'ATANGANA', 4, 'FOTSO',
                                 5, 'NKOMO', 6, 'EBOA', 7, 'KAMGA', 8, 'ONANA', 'MOUKOKO'),
       CASE WHEN MOD(lv, 50) = 0 OR MOD(lv, 7) = 0 THEN NULL
            ELSE DECODE(MOD(lv * 3 + TRUNC(lv / 10), 10), 0, 'Jean', 1, 'Marie', 2, 'Paul', 3, 'Aline',
                        4, 'Serge', 5, 'Carine', 6, 'Patrick', 7, 'Sandra', 8, 'Eric', 'Nadege') END,
       CASE WHEN MOD(lv, 50) = 0 THEN 'GRANDE_ENTREPRISE'
            WHEN MOD(lv, 7)  = 0 THEN 'PME'
            ELSE 'PARTICULIER' END,
       1 + MOD(lv, 4),
       DATE '2018-01-01' + MOD(lv * 37, 2000),
       CASE WHEN MOD(lv, 25) = 0 THEN 'INACTIF'
            WHEN MOD(lv, 40) = 0 THEN 'SUSPENDU'
            ELSE 'ACTIF' END
FROM (SELECT LEVEL AS lv FROM dual CONNECT BY LEVEL <= 600);

-- Noms volontairement "difficiles" pour les tests de securite et de recherche
-- (apostrophe, tiret, underscore = joker de LIKE)
UPDATE client SET nom = 'N''GUEMA'      WHERE client_id = 11;
UPDATE client SET nom = 'MBA-ONDO'      WHERE client_id = 13;
UPDATE client SET nom = 'TCHOUPO_TEST'  WHERE client_id = 17;

-- Comptes : 1 compte courant par client + 1 compte epargne pour 1 client sur 3
INSERT INTO compte (compte_id, num_compte, client_id, agence_id, type_compte, devise, solde, date_ouverture, statut)
SELECT ROWNUM,
       'CM' || LPAD(ROWNUM, 14, '0'),
       x.client_id,
       x.agence_id,
       x.type_compte,
       CASE WHEN MOD(x.client_id, 20) = 0 THEN 'EUR' ELSE 'XAF' END,
       ROUND(DBMS_RANDOM.VALUE(0, 5000000), 2),
       x.date_creation + MOD(x.client_id * 3, 30) + CASE WHEN x.type_compte = 'EPARGNE' THEN 60 ELSE 0 END,
       CASE WHEN x.statut = 'INACTIF' THEN 'CLOTURE'
            WHEN x.statut = 'SUSPENDU' OR MOD(ROWNUM, 31) = 0 THEN 'BLOQUE'
            ELSE 'OUVERT' END
FROM (
  SELECT c.client_id, c.agence_id, c.date_creation, c.statut, t.type_compte
  FROM client c
  JOIN (SELECT 'COURANT' AS type_compte FROM dual
        UNION ALL
        SELECT 'EPARGNE' FROM dual) t
    ON (t.type_compte = 'COURANT' OR MOD(c.client_id, 3) = 0)
  ORDER BY c.client_id, t.type_compte
) x;

-- 100 000 operations (de janvier 2025 a septembre 2026) : sert a tester le plafond de resultats.
-- Les comptes dont l'identifiant est multiple de 9 n'ont AUCUN mouvement apres mai 2025 (besoin B06).
DECLARE
  v_n NUMBER;
BEGIN
  SELECT COUNT(*) INTO v_n FROM compte;

  INSERT INTO operation (operation_id, compte_id, date_operation, type_operation, montant, libelle, canal)
  SELECT g.lv,
         g.cpt,
         DATE '2025-01-01' + CASE WHEN MOD(g.cpt, 9) = 0 THEN MOD(g.lv * 31, 150)
                                  ELSE MOD(g.lv * 31, 640) END,
         CASE WHEN MOD(g.lv, 5) IN (1, 4) THEN 'CREDIT' ELSE 'DEBIT' END,
         ROUND(POWER(DBMS_RANDOM.VALUE, 3) * 5000000 + 500, 0),
         DECODE(MOD(g.lv, 5), 0, 'Retrait DAB', 1, 'Virement recu', 2, 'Paiement carte',
                              3, 'Frais de tenue de compte', 'Versement especes'),
         DECODE(MOD(g.lv, 4), 0, 'GUICHET', 1, 'DAB', 2, 'MOBILE', 'WEB')
  FROM (SELECT lv, 1 + MOD(lv * 7919, v_n) AS cpt
        FROM (SELECT LEVEL AS lv FROM dual CONNECT BY LEVEL <= 100000)) g;

  COMMIT;
END;
/

-- 400 credits / engagements (besoin B05)
INSERT INTO credit (credit_id, num_dossier, client_id, agence_id, type_engagement, montant_accorde,
                    encours, devise, date_octroi, date_echeance, statut)
SELECT y.lv,
       'CR' || LPAD(y.lv, 10, '0'),
       y.client_id,
       y.agence_id,
       y.type_engagement,
       y.montant,
       CASE WHEN y.statut = 'SOLDE' THEN 0
            ELSE ROUND(y.montant * DBMS_RANDOM.VALUE(0.2, 1), -3) END,
       'XAF',
       y.date_octroi,
       y.date_echeance,
       y.statut
FROM (
  SELECT x.lv, x.client_id, x.agence_id, x.type_engagement, x.montant, x.date_octroi, x.date_echeance,
         CASE WHEN x.date_echeance < DATE '2026-09-30' AND MOD(x.lv, 3) <> 0 THEN 'SOLDE'
              WHEN MOD(x.lv, 11) = 0 THEN 'IMPAYE'
              WHEN MOD(x.lv, 23) = 0 THEN 'CONTENTIEUX'
              ELSE 'EN_COURS' END AS statut
  FROM (
    SELECT g.lv,
           c.client_id,
           c.agence_id,
           DECODE(MOD(g.lv, 5), 0, 'CREDIT_CONSO', 1, 'CREDIT_IMMO', 2, 'CREDIT_TRESORERIE',
                                3, 'CREDIT_EQUIPEMENT', 'CAUTION') AS type_engagement,
           ROUND(DBMS_RANDOM.VALUE(500000, 50000000), -3) AS montant,
           DATE '2022-01-01' + MOD(g.lv * 29, 1500) AS date_octroi,
           ADD_MONTHS(DATE '2022-01-01' + MOD(g.lv * 29, 1500), 12 * (1 + MOD(g.lv, 5))) AS date_echeance
    FROM (SELECT LEVEL AS lv FROM dual CONNECT BY LEVEL <= 400) g
    JOIN client c ON c.client_id = 1 + MOD(g.lv * 13, 600)
  ) x
) y;
COMMIT;

EXEC DBMS_STATS.GATHER_SCHEMA_STATS(ownname => 'AMP_TEST');