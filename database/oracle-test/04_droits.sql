-- Droits de lecture seule pour le compte applicatif APP_RO
ALTER SESSION SET CURRENT_SCHEMA = SYS;

GRANT SELECT ON amp_test.agence    TO app_ro;
GRANT SELECT ON amp_test.client    TO app_ro;
GRANT SELECT ON amp_test.compte    TO app_ro;
GRANT SELECT ON amp_test.operation TO app_ro;
GRANT SELECT ON amp_test.credit    TO app_ro;

-- Controle du volume charge
SELECT 'AGENCE' AS table_name, COUNT(*) AS nb_lignes FROM amp_test.agence
UNION ALL SELECT 'CLIENT',    COUNT(*) FROM amp_test.client
UNION ALL SELECT 'COMPTE',    COUNT(*) FROM amp_test.compte
UNION ALL SELECT 'OPERATION', COUNT(*) FROM amp_test.operation
UNION ALL SELECT 'CREDIT',    COUNT(*) FROM amp_test.credit;
