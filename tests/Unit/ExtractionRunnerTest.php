<?php

namespace Tests\Unit;

use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\SqlGuard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ExtractionRunnerTest extends TestCase
{
    public function test_rejette_requete_non_lecture_seule_avant_connexion(): void
    {
        $runner = new ExtractionRunner();

        $this->expectException(InvalidArgumentException::class);
        $runner->run('DELETE FROM amp_test.compte');
    }

    public function test_rejette_requete_avec_commentaires_sql(): void
    {
        $runner = new ExtractionRunner();

        $this->expectException(InvalidArgumentException::class);
        $runner->run('SELECT * FROM amp_test.compte -- tentative commentaire');
    }

    public function test_execution_avec_tronquature_et_limite(): void
    {
        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible.');
        }

        $runner = new ExtractionRunner();
        // Demande une limite de 5 lignes pour vérifier que le retour est plafonné et tronqué
        $res = $runner->run('SELECT num_compte FROM amp_test.compte ORDER BY num_compte', [], maxRows: 5);

        $this->assertCount(5, $res['rows']);
        $this->assertEquals(5, $res['count']);
        $this->assertTrue($res['truncated']);
        $this->assertEquals(5, $res['max_rows']);
        $this->assertContains('num_compte', $res['columns']);
        $this->assertIsInt($res['duration_ms']);
    }
}
