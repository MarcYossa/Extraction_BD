<?php

namespace Tests\Oracle;

use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ParameterResolver;
use App\Services\Extraction\QueryCompiler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('oracle')]
class ExtractionsComplementairesTest extends TestCase
{
    protected CatalogueRepository $repository;
    protected ParameterResolver $resolver;
    protected QueryCompiler $compiler;
    protected ExtractionRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible : ' . $e->getMessage());
        }

        // On injecte les définitions de B03, B06, B07 dans le stockage de test
        Storage::fake('local');
        $catalogue = [
            'schema_version' => 1,
            'modifie_le'     => '2026-10-21T10:00:00Z',
            'extractions'    => [
                [
                    'code'        => 'OPE_002',
                    'titre'       => 'Opérations par compte',
                    'statut'      => 'active',
                    'version'     => 1,
                    'requete_sql' => [
                        "SELECT c.num_compte AS num_compte, o.date_operation AS date_operation, o.type_operation AS type_operation,",
                        "       o.montant AS montant, o.libelle AS libelle, o.canal AS canal",
                        "FROM amp_test.operation o",
                        "JOIN amp_test.compte c ON c.compte_id = o.compte_id",
                        "WHERE c.num_compte = :num_compte",
                        "  AND o.date_operation BETWEEN :date_debut AND :date_fin",
                        "ORDER BY o.date_operation, o.operation_id"
                    ],
                    'parametres'  => [
                        ['nom' => 'num_compte', 'type' => 'texte', 'obligatoire' => true],
                        ['nom' => 'date_debut', 'type' => 'date', 'obligatoire' => true],
                        ['nom' => 'date_fin', 'type' => 'date', 'obligatoire' => true],
                    ],
                    'colonnes'    => [],
                ],
                [
                    'code'        => 'CPT_002',
                    'titre'       => 'Comptes sans mouvement',
                    'statut'      => 'active',
                    'version'     => 1,
                    'requete_sql' => [
                        "SELECT a.nom_agence AS agence, c.num_compte AS num_compte, c.type_compte AS type_compte,",
                        "       c.date_ouverture AS date_ouverture, c.solde AS solde, c.devise AS devise",
                        "FROM amp_test.compte c",
                        "JOIN amp_test.agence a ON a.agence_id = c.agence_id",
                        "WHERE c.statut = 'OUVERT'",
                        "  AND (:agence IS NULL OR c.agence_id = :agence)",
                        "  AND NOT EXISTS (SELECT 1 FROM amp_test.operation o",
                        "                  WHERE o.compte_id = c.compte_id",
                        "                    AND o.date_operation >= :date_reference)",
                        "ORDER BY a.nom_agence, c.num_compte"
                    ],
                    'parametres'  => [
                        ['nom' => 'agence', 'type' => 'liste', 'obligatoire' => false],
                        ['nom' => 'date_reference', 'type' => 'date', 'obligatoire' => true],
                    ],
                    'colonnes'    => [],
                ],
                [
                    'code'        => 'CLI_002',
                    'titre'       => 'Clients PME actifs',
                    'statut'      => 'active',
                    'version'     => 1,
                    'requete_sql' => [
                        "SELECT cl.num_client AS num_client, TRIM(cl.prenom || ' ' || cl.nom) AS nom_raison_sociale,",
                        "       cl.segment_client AS segment_client, a.nom_agence AS agence, cl.statut AS statut",
                        "FROM amp_test.client cl",
                        "JOIN amp_test.agence a ON a.agence_id = cl.agence_id",
                        "WHERE cl.segment_client = 'PME'",
                        "  AND cl.statut = 'ACTIF'",
                        "  AND (:agence IS NULL OR cl.agence_id = :agence)",
                        "ORDER BY a.nom_agence, cl.nom, cl.num_client"
                    ],
                    'parametres'  => [
                        ['nom' => 'agence', 'type' => 'liste', 'obligatoire' => false],
                    ],
                    'colonnes'    => [],
                ],
            ],
        ];

        Storage::disk('local')->put('extraction/queries.json', json_encode($catalogue));

        $this->repository = new CatalogueRepository();
        $this->resolver   = new ParameterResolver();
        $this->compiler   = new QueryCompiler();
        $this->runner     = new ExtractionRunner();
    }

    protected function runExtraction(string $code, array $rawInputs): array
    {
        $extraction = $this->repository->find($code);
        $this->assertNotNull($extraction, "Extraction {$code} introuvable.");

        $resolved = $this->resolver->resolve($extraction['parametres'], $rawInputs);
        $compiled = $this->compiler->compile($extraction['requete_sql'], $extraction['parametres'], $resolved);

        return $this->runner->run($compiled['sql'], $compiled['bindings']);
    }

    public function test_ope_002_compte_specifique_retourne_125_operations(): void
    {
        $res = $this->runExtraction('OPE_002', [
            'num_compte' => 'CM00000000000001',
            'date_debut' => '2025-01-01',
            'date_fin'   => '2026-12-31',
        ]);

        $this->assertCount(125, $res['rows']);
    }

    public function test_ope_002_compte_inexistant_retourne_zero_ligne(): void
    {
        $res = $this->runExtraction('OPE_002', [
            'num_compte' => 'INEXISTANT_99999',
            'date_debut' => '2025-01-01',
            'date_fin'   => '2026-12-31',
        ]);

        $this->assertCount(0, $res['rows']);
    }

    public function test_cpt_002_agence_1_retourne_18_comptes_sans_mouvement(): void
    {
        $res = $this->runExtraction('CPT_002', [
            'agence'         => '1',
            'date_reference' => '2025-07-01',
        ]);

        $this->assertCount(18, $res['rows']);
    }

    public function test_cpt_002_toutes_agences_retourne_81_comptes_sans_mouvement(): void
    {
        $res = $this->runExtraction('CPT_002', [
            'agence'         => '',
            'date_reference' => '2025-07-01',
        ]);

        $this->assertCount(81, $res['rows']);
    }

    public function test_cli_002_agence_1_retourne_19_clients_pme(): void
    {
        $res = $this->runExtraction('CLI_002', [
            'agence' => '1',
        ]);

        $this->assertCount(19, $res['rows']);
    }
}
