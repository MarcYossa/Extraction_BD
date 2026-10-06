<?php

namespace Tests\Unit;

use App\Services\Extraction\CatalogueRepository;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueRepositoryTest extends TestCase
{
    protected CatalogueRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->repository = new CatalogueRepository();
    }

    public function test_load_raw_returns_empty_structure_if_file_missing(): void
    {
        $data = $this->repository->loadRaw();

        $this->assertArrayHasKey('extractions', $data);
        $this->assertEmpty($data['extractions']);
    }

    public function test_save_and_find_extraction_atomically(): void
    {
        $extraction = [
            'code' => 'CLI_001',
            'titre' => 'Clients par agence',
            'description' => 'Liste des clients',
            'statut' => 'active',
            'version' => 1,
            'requete_sql' => ['SELECT * FROM client'],
            'parametres' => [],
            'colonnes' => [],
        ];

        $this->repository->save($extraction, 'amine.m');

        $found = $this->repository->find('CLI_001');
        $this->assertNotNull($found);
        $this->assertEquals('Clients par agence', $found['titre']);
        $this->assertEquals('amine.m', $found['modifie_par']);
    }

    public function test_actives_filters_only_active_extractions(): void
    {
        $this->repository->save([
            'code' => 'EXP_001',
            'titre' => 'Extraction 1',
            'statut' => 'active',
            'version' => 1,
        ]);

        $this->repository->save([
            'code' => 'EXP_002',
            'titre' => 'Extraction 2',
            'statut' => 'brouillon',
            'version' => 1,
        ]);

        $actives = $this->repository->actives();

        $this->assertCount(1, $actives);
        $this->assertEquals('EXP_001', $actives[0]['code']);
    }
}
