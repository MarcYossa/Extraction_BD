<?php

namespace Tests\Unit;

use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\LogReader;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_retourne_vide_si_fichiers_inexistants(): void
    {
        $reader = new LogReader(
            null,
            'extraction/non_existant.json',
            'extraction/non_existant_auth.json'
        );

        $execPaginator = $reader->getExecutionLogs();
        $this->assertInstanceOf(LengthAwarePaginator::class, $execPaginator);
        $this->assertCount(0, $execPaginator->items());
        $this->assertEquals(0, $execPaginator->total());

        $authPaginator = $reader->getAuthLogs();
        $this->assertInstanceOf(LengthAwarePaginator::class, $authPaginator);
        $this->assertCount(0, $authPaginator->items());
        $this->assertEquals(0, $authPaginator->total());
    }

    public function test_tolere_fichier_vide_ou_corrompu(): void
    {
        Storage::disk('local')->put('extraction/empty.json', '');
        Storage::disk('local')->put('extraction/corrupt.json', '{invalid json syntax ...');

        $reader = new LogReader(null, 'extraction/empty.json', 'extraction/corrupt.json');

        $execPaginator = $reader->getExecutionLogs();
        $this->assertCount(0, $execPaginator->items());

        $authPaginator = $reader->getAuthLogs();
        $this->assertCount(0, $authPaginator->items());
    }

    public function test_tolere_entrees_mal_formees_dans_le_tableau(): void
    {
        $data = [
            'chaine_invalide',
            null,
            123,
            [
                'date'        => '2026-09-27T10:00:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'CPT_001',
                'statut'      => 'succes',
            ],
            [
                // Sans date ni code
                'utilisateur' => 'inconnu',
            ],
        ];

        Storage::disk('local')->put('extraction/exec.json', json_encode($data));
        $reader = new LogReader(null, 'extraction/exec.json');

        $paginator = $reader->getExecutionLogs();
        // Les entrées non-tableaux sont ignorées, les entrées tableaux sont conservées avec valeurs par défaut
        $this->assertCount(2, $paginator->items());
    }

    public function test_tri_antechronologique(): void
    {
        $data = [
            ['date' => '2026-09-25T10:00:00Z', 'code' => 'CPT_001'],
            ['date' => '2026-09-27T12:00:00Z', 'code' => 'CPT_002'],
            ['date' => '2026-09-26T08:00:00Z', 'code' => 'CPT_003'],
        ];

        Storage::disk('local')->put('extraction/exec.json', json_encode($data));
        $reader = new LogReader(null, 'extraction/exec.json');

        $items = $reader->getExecutionLogs()->items();
        $this->assertEquals('CPT_002', $items[0]['code']);
        $this->assertEquals('CPT_003', $items[1]['code']);
        $this->assertEquals('CPT_001', $items[2]['code']);
    }

    public function test_filtres_utilisateur_statut_mode_extraction(): void
    {
        $data = [
            [
                'date'        => '2026-09-27T10:00:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'CPT_001',
                'mode'        => 'execution',
                'statut'      => 'succes',
            ],
            [
                'date'        => '2026-09-27T11:00:00Z',
                'utilisateur' => 'amine.m',
                'code'        => 'CPT_001',
                'mode'        => 'test',
                'statut'      => 'succes',
            ],
            [
                'date'        => '2026-09-27T12:00:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'CLI_001',
                'mode'        => 'export_csv',
                'statut'      => 'echec',
            ],
        ];

        Storage::disk('local')->put('extraction/exec.json', json_encode($data));
        $reader = new LogReader(null, 'extraction/exec.json');

        // Filtre utilisateur
        $resUser = $reader->getExecutionLogs(['utilisateur' => 'sonia.d']);
        $this->assertCount(2, $resUser->items());

        // Filtre extraction
        $resCode = $reader->getExecutionLogs(['extraction' => 'CPT_001']);
        $this->assertCount(2, $resCode->items());

        // Filtre mode
        $resMode = $reader->getExecutionLogs(['mode' => 'export_csv']);
        $this->assertCount(1, $resMode->items());
        $this->assertEquals('CLI_001', $resMode->items()[0]['code']);

        // Filtre statut
        $resStatut = $reader->getExecutionLogs(['statut' => 'echec']);
        $this->assertCount(1, $resStatut->items());
        $this->assertEquals('CLI_001', $resStatut->items()[0]['code']);
    }

    public function test_filtre_recherche_textuelle(): void
    {
        $data = [
            [
                'date'        => '2026-09-27T10:00:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'CPT_001',
                'titre'       => 'Soldes des comptes',
            ],
            [
                'date'        => '2026-09-27T11:00:00Z',
                'utilisateur' => 'amine.m',
                'code'        => 'CLI_002',
                'titre'       => 'Liste des clients actifs',
            ],
        ];

        Storage::disk('local')->put('extraction/exec.json', json_encode($data));
        $reader = new LogReader(null, 'extraction/exec.json');

        // Recherche par titre
        $resTitre = $reader->getExecutionLogs(['recherche' => 'soldes']);
        $this->assertCount(1, $resTitre->items());
        $this->assertEquals('CPT_001', $resTitre->items()[0]['code']);

        // Recherche par code
        $resCode = $reader->getExecutionLogs(['recherche' => 'cli_']);
        $this->assertCount(1, $resCode->items());
        $this->assertEquals('CLI_002', $resCode->items()[0]['code']);
    }

    public function test_pagination_serveur_50_entrees(): void
    {
        $data = [];
        for ($i = 1; $i <= 120; $i++) {
            $data[] = [
                'date'        => Carbon::parse('2026-09-01')->addMinutes($i)->toIso8601String(),
                'utilisateur' => 'sonia.d',
                'code'        => 'CPT_001',
            ];
        }

        Storage::disk('local')->put('extraction/exec.json', json_encode($data));
        $reader = new LogReader(null, 'extraction/exec.json');

        $page1 = $reader->getExecutionLogs([], page: 1, perPage: 50);
        $this->assertEquals(120, $page1->total());
        $this->assertCount(50, $page1->items());
        $this->assertEquals(1, $page1->currentPage());
        $this->assertEquals(3, $page1->lastPage());

        $page2 = $reader->getExecutionLogs([], page: 2, perPage: 50);
        $this->assertCount(50, $page2->items());

        $page3 = $reader->getExecutionLogs([], page: 3, perPage: 50);
        $this->assertCount(20, $page3->items());
    }

    public function test_lecture_et_filtres_auth_logs(): void
    {
        $data = [
            [
                'timestamp'   => '2026-09-27T10:00:00Z',
                'event'       => 'login_success',
                'identifiant' => 'amine.m',
                'ip'          => '127.0.0.1',
            ],
            [
                'timestamp'   => '2026-09-27T11:00:00Z',
                'event'       => 'login_failed',
                'identifiant' => 'hacker',
                'ip'          => '192.168.1.100',
            ],
            [
                'timestamp'   => '2026-09-27T12:00:00Z',
                'event'       => 'logout',
                'identifiant' => 'amine.m',
                'ip'          => '127.0.0.1',
            ],
        ];

        Storage::disk('local')->put('extraction/auth.json', json_encode($data));
        $reader = new LogReader(null, null, 'extraction/auth.json');

        $authPaginator = $reader->getAuthLogs();
        $this->assertEquals(3, $authPaginator->total());

        // Tri antéchronologique vérifié
        $this->assertEquals('logout', $authPaginator->items()[0]['event']);

        // Filtre échec
        $echecs = $reader->getAuthLogs(['statut' => 'echec']);
        $this->assertEquals(1, $echecs->total());
        $this->assertEquals('hacker', $echecs->items()[0]['identifiant']);
    }
}
