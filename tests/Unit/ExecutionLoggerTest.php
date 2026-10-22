<?php

namespace Tests\Unit;

use App\Services\Extraction\ExecutionLogger;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExecutionLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_log_execution_ajoute_une_entree_au_journal(): void
    {
        $logger = new ExecutionLogger();

        $logger->logExecution(
            utilisateur: 'amine.m',
            code: 'CPT_001',
            version: 1,
            mode: 'execution',
            parametres: ['agence' => '1'],
            statut: 'succes',
            nbLignes: 15,
            tronque: false,
            dureeMs: 250
        );

        $path = config('extraction.log_file', 'extraction/execution_log.json');
        $this->assertTrue(Storage::disk('local')->exists($path));

        $data = json_decode(Storage::disk('local')->get($path), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        $entry = $data[0];
        $this->assertEquals('amine.m', $entry['utilisateur']);
        $this->assertEquals('CPT_001', $entry['code']);
        $this->assertEquals(1, $entry['version']);
        $this->assertEquals('execution', $entry['mode']);
        $this->assertEquals(['agence' => '1'], $entry['parametres']);
        $this->assertEquals('succes', $entry['statut']);
        $this->assertEquals(15, $entry['nb_lignes']);
        $this->assertFalse($entry['tronque']);
        $this->assertEquals(250, $entry['duree_ms']);
        $this->assertNotEmpty($entry['date']);
    }

    public function test_record_cumule_plusieurs_entrees_sans_ecraser(): void
    {
        $logger = new ExecutionLogger();

        $logger->record(['action' => 'test_1', 'valeur' => 10]);
        $logger->record(['action' => 'test_2', 'valeur' => 20]);

        $path = config('extraction.log_file', 'extraction/execution_log.json');
        $data = json_decode(Storage::disk('local')->get($path), true);

        $this->assertCount(2, $data);
        $this->assertEquals('test_1', $data[0]['action']);
        $this->assertEquals('test_2', $data[1]['action']);
    }
}
