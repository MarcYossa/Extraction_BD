<?php

namespace Tests\Oracle;

use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ParameterResolver;
use App\Services\Extraction\QueryCompiler;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('oracle')]
class ExtractionsMvpTest extends TestCase
{
    protected CatalogueRepository $repository;
    protected ParameterResolver $resolver;
    protected QueryCompiler $compiler;
    protected ExtractionRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        // Si la connexion Oracle n'est pas disponible dans l'environnement de test, passer
        try {
            \Illuminate\Support\Facades\DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible : ' . $e->getMessage());
        }

        // Remplir le stockage avec l'exemple pour les tests
        Storage::fake('local');
        $exampleContent = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $exampleContent);

        $this->repository = new CatalogueRepository();
        $this->resolver   = new ParameterResolver();
        $this->compiler   = new QueryCompiler();
        $this->runner     = new ExtractionRunner();
    }

    /**
     * Cas 1 : CPT_001 agence 1, 2018-01-01 à 2023-12-31, comptes clôturés exclus (192 lignes).
     */
    public function test_cpt_001_excluant_clotures(): void
    {
        $res = $this->runExtraction('CPT_001', [
            'agence'           => '1',
            'date_debut'       => '2018-01-01',
            'date_fin'         => '2023-12-31',
            'inclure_clotures' => false,
        ]);

        $this->assertCount(192, $res['rows']);
        $this->assertFalse($res['truncated']);
    }

    /**
     * Cas 2 : CPT_001 agence 1, 2018-01-01 à 2023-12-31, comptes clôturés inclus (200 lignes).
     */
    public function test_cpt_001_incluant_clotures(): void
    {
        $res = $this->runExtraction('CPT_001', [
            'agence'           => '1',
            'date_debut'       => '2018-01-01',
            'date_fin'         => '2023-12-31',
            'inclure_clotures' => true,
        ]);

        $this->assertCount(200, $res['rows']);
        $this->assertFalse($res['truncated']);
    }

    /**
     * Cas 3 : CLI_001 agence 4, statut ACTIF, nom TCHOUPO (26 lignes).
     */
    public function test_cli_001_agence_4_actif_tchoupo(): void
    {
        $res = $this->runExtraction('CLI_001', [
            'agence' => '4',
            'statut' => 'ACTIF',
            'nom'    => 'TCHOUPO',
        ]);

        $this->assertCount(26, $res['rows']);
        $this->assertFalse($res['truncated']);
    }

    /**
     * Cas 4 : CLI_001 toutes agences, tous statuts, nom N'GUEMA (1 ligne).
     */
    public function test_cli_001_toutes_agences_nguema(): void
    {
        $res = $this->runExtraction('CLI_001', [
            'agence' => '— Toutes les agences —',
            'statut' => '— Tous les statuts —',
            'nom'    => "N'GUEMA",
        ]);

        $this->assertCount(1, $res['rows']);
        $this->assertFalse($res['truncated']);
    }

    /**
     * Cas 5 : CLI_001 injection SQL neutralisée : nom "X' OR '1'='1" (0 ligne).
     */
    public function test_cli_001_sql_injection_attempt_returns_zero_rows(): void
    {
        $res = $this->runExtraction('CLI_001', [
            'agence' => '— Toutes les agences —',
            'statut' => '— Tous les statuts —',
            'nom'    => "X' OR '1'='1",
        ]);

        $this->assertCount(0, $res['rows']);
        $this->assertFalse($res['truncated']);
    }

    /**
     * Cas 6 : CRT_001 agence 1, 2022-01-01 à 2026-09-30, EN_COURS, CREDIT_CONSO (8 lignes).
     */
    public function test_crt_001_agence_1_en_cours_credit_conso(): void
    {
        $res = $this->runExtraction('CRT_001', [
            'agence'          => '1',
            'date_debut'      => '2022-01-01',
            'date_fin'        => '2026-09-30',
            'statut'          => 'EN_COURS',
            'type_engagement' => 'CREDIT_CONSO',
        ]);

        $this->assertCount(8, $res['rows']);
        $this->assertFalse($res['truncated']);
    }

    /**
     * Cas 7 : CRT_001 toutes agences, EN_COURS, tous types, 2022-01-01 à 2026-09-30 (245 lignes).
     */
    public function test_crt_001_toutes_agences_en_cours(): void
    {
        $res = $this->runExtraction('CRT_001', [
            'agence'      => '— Toutes les agences —',
            'date_debut'  => '2022-01-01',
            'date_fin'    => '2026-09-30',
            'statut'      => 'EN_COURS',
            'type_credit' => '— Tous les types de crédit —',
        ]);

        $this->assertCount(245, $res['rows']);
        $this->assertFalse($res['truncated']);
    }

    /**
     * Cas 8 : OPE_001 agence 1, 2025-01-01 à 2026-09-30, min 0, tous types (10 000 lignes, tronqué).
     */
    public function test_ope_001_plafond_10000_lignes(): void
    {
        $res = $this->runExtraction('OPE_001', [
            'agence'         => '1',
            'date_debut'     => '2025-01-01',
            'date_fin'       => '2026-09-30',
            'montant_min'    => 0,
            'type_operation' => '— Tous les types —',
        ]);

        $this->assertCount(10000, $res['rows']);
        $this->assertTrue($res['truncated']);
    }

    /**
     * Helper pour préparer, résoudre, compiler et exécuter une extraction.
     */
    protected function runExtraction(string $code, array $inputs): array
    {
        $extraction = $this->repository->find($code);
        if (! $extraction) {
            $this->fail("Extraction [{$code}] non trouvée.");
        }

        $declaredParams = $extraction['parametres'] ?? [];
        $resolved = $this->resolver->resolve($declaredParams, $inputs);
        $compiled = $this->compiler->compile($extraction['requete_sql'], $declaredParams, $resolved);

        return $this->runner->run($compiled['sql'], $compiled['bindings']);
    }
}
