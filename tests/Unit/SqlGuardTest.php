<?php

namespace Tests\Unit;

use App\Services\Extraction\SqlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SqlGuardTest extends TestCase
{
    public static function requetesAutorisees(): array
    {
        return [
            'select simple' => ['SELECT 1 FROM dual'],
            'select avec parametre' => ['select * from amp_test.compte where statut = ?'],
            'with' => ['WITH t AS (SELECT 1 a FROM dual) SELECT * FROM t'],
            'mot interdit dans un litteral' => ["SELECT 'DELETE; DROP' AS x FROM dual"],
            'colonnes proches de mots interdits' => ['SELECT date_creation, created_by FROM amp_test.client'],
        ];
    }

    public static function requetesRefusees(): array
    {
        return [
            'delete' => ['DELETE FROM amp_test.operation'],
            'insert' => ["INSERT INTO amp_test.agence VALUES (1, 'x')"],
            'update' => ['UPDATE amp_test.compte SET solde = 0'],
            'truncate' => ['TRUNCATE TABLE amp_test.credit'],
            'plusieurs instructions' => ['SELECT 1 FROM dual; DROP TABLE x'],
            'for update' => ['SELECT * FROM amp_test.compte FOR UPDATE'],
            'commentaire de ligne' => ['SELECT 1 FROM dual -- masque'],
            'commentaire de bloc' => ['SELECT /* x */ 1 FROM dual'],
            'bloc anonyme' => ['BEGIN NULL; END;'],
            'appel de package dbms' => ['SELECT DBMS_RANDOM.VALUE FROM dual'],
            'appel de package utl' => ["SELECT UTL_HTTP.REQUEST('http://exemple') FROM dual"],
            'sous-requete dangereuse' => ['SELECT * FROM (SELECT 1 FROM dual) WHERE 1 = 1 UNION SELECT 1 FROM dual WHERE 1 IN (SELECT 1 FROM dual); DELETE FROM x'],
        ];
    }

    #[DataProvider('requetesAutorisees')]
    public function test_requete_autorisee(string $sql): void
    {
        (new SqlGuard())->assertReadOnly($sql);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('requetesRefusees')]
    public function test_requete_refusee(string $sql): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SqlGuard())->assertReadOnly($sql);
    }
}
