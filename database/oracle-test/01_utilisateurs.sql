-- Suppression des comptes s'ils existent deja (relance possible)
BEGIN
  FOR u IN (SELECT username FROM dba_users WHERE username IN ('AMP_TEST', 'APP_RO')) LOOP
    EXECUTE IMMEDIATE 'DROP USER ' || u.username || ' CASCADE';
  END LOOP;
END;
/

-- Proprietaire du jeu de donnees de test (simule le schema metier)
CREATE USER amp_test IDENTIFIED BY "&1";
GRANT CREATE SESSION, CREATE TABLE, CREATE VIEW, UNLIMITED TABLESPACE TO amp_test;

-- Compte applicatif : connexion uniquement, AUCUN droit de creation ni d'ecriture
CREATE USER app_ro IDENTIFIED BY "&2";
GRANT CREATE SESSION TO app_ro;
