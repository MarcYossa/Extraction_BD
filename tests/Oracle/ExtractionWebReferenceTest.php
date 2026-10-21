<?php

namespace Tests\Oracle;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('oracle')]
class ExtractionWebReferenceTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible : ' . $e->getMessage());
        }

        Artisan::call('migrate:fresh');

        $this->user = User::create([
            'identifiant' => 'sonia.d',
            'prenom'      => 'Sonia',
            'nom'         => 'D.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        Storage::fake('local');
        $exampleContent = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $exampleContent);
    }

    /**
     * Cas de référence CPT_001 sur base Oracle : agence 1, 2018-01-01 à 2023-12-31, non clôturés = 192 lignes.
     */
    public function test_reference_cpt_001_agence_1_retourne_192_lignes(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/extractions/CPT_001/execute', [
                'agence'           => '1',
                'date_debut'       => '2018-01-01',
                'date_fin'         => '2023-12-31',
                'inclure_clotures' => '0',
            ]);

        $response->assertRedirect();
        $targetUrl = $response->headers->get('Location');
        $this->assertStringContainsString('/extractions/CPT_001/resultats/', $targetUrl);

        $resultPage = $this->actingAs($this->user)->get($targetUrl);
        $resultPage->assertStatus(200);
        $resultPage->assertSee('192 lignes retournées', false);
    }

    /**
     * Cas de référence CLI_001 sur base Oracle : agence 4, ACTIF, nom TCHOUPO = 26 lignes.
     */
    public function test_reference_cli_001_agence_4_tchoupo_retourne_26_lignes(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/extractions/CLI_001/execute', [
                'agence' => '4',
                'statut' => 'ACTIF',
                'nom'    => 'TCHOUPO',
            ]);

        $response->assertRedirect();
        $targetUrl = $response->headers->get('Location');

        $resultPage = $this->actingAs($this->user)->get($targetUrl);
        $resultPage->assertStatus(200);
        $resultPage->assertSee('26 lignes retournées', false);
    }

    /**
     * Cas de référence OPE_001 sur base Oracle : agence 1, 2025-01-01 à 2026-09-30, montant min 0 = 10 000 lignes (plafond).
     */
    public function test_reference_ope_001_plafond_10000_lignes(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/extractions/OPE_001/execute', [
                'agence'          => '1',
                'date_debut'      => '2025-01-01',
                'date_fin'        => '2026-09-30',
                'montant_minimum' => '0',
            ]);

        $response->assertRedirect();
        $targetUrl = $response->headers->get('Location');

        $resultPage = $this->actingAs($this->user)->get($targetUrl);
        $resultPage->assertStatus(200);
        $resultPage->assertSee('10 000 lignes (plafond atteint)', false);
        $resultPage->assertSeeText('Résultat limité : affinez vos critères');
    }
}
