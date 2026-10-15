<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ResultStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class ExtractionExecutionTest extends TestCase
{
    protected User $user;
    protected User $admin;
    protected User $otherUser;
    protected ResultStore $resultStore;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Artisan::call('migrate:fresh');

        $this->user = User::create([
            'identifiant' => 'sonia.d',
            'prenom'      => 'Sonia',
            'nom'         => 'D.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $this->admin = User::create([
            'identifiant' => 'amine.m',
            'prenom'      => 'Amine',
            'nom'         => 'M.',
            'role'        => 'admin',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $this->otherUser = User::create([
            'identifiant' => 'autre.user',
            'prenom'      => 'Autre',
            'nom'         => 'User',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        // Charger le catalogue d'exemple
        $example = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $example);

        $this->resultStore = new ResultStore();
    }

    /**
     * Un invité qui tente d'exécuter une extraction est renvoyé vers login.
     */
    public function test_invite_redirige_vers_login_a_lexecution(): void
    {
        $response = $this->post('/extractions/CPT_001/execute', [
            'agence'     => '1',
            'date_debut' => '2020-01-01',
            'date_fin'   => '2023-12-31',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Un administrateur qui tente d'exécuter une extraction métier est redirigé vers /admin (D14).
     */
    public function test_admin_redirige_vers_admin_a_lexecution(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/extractions/CPT_001/execute', [
                'agence'     => '1',
                'date_debut' => '2020-01-01',
                'date_fin'   => '2023-12-31',
            ]);

        $response->assertRedirect('/admin');
    }

    /**
     * Tenter d'exécuter une extraction en brouillon (CRT_001) renvoie 404.
     */
    public function test_brouillon_retourne_404_a_lexecution(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/extractions/CRT_001/execute', []);

        $response->assertStatus(404);
    }

    /**
     * Validation des paramètres : erreurs renvoyées sous les champs si les paramètres obligatoires manquent.
     *
     * Dans queries.example.json (CPT_001) :
     * - agence   : liste + option_toutes  → vide autorisé (NULL envoyé à Oracle)
     * - date_fin : obligatoire mais defaut "aujourd_hui" → jamais vide
     * - date_debut : obligatoire sans défaut → seul champ qui génère une erreur
     */
    public function test_validation_parametres_obligatoires_manquants(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/extractions/CPT_001')
            ->post('/extractions/CPT_001/execute', [
                'agence'     => '',
                'date_debut' => '',
                'date_fin'   => '',
            ]);

        $response->assertRedirect('/extractions/CPT_001');
        $response->assertSessionHasErrors(['date_debut']);
        // agence est facultatif (liste + option_toutes) ; date_fin a un défaut "aujourd_hui"
        $response->assertSessionDoesntHaveErrors(['agence', 'date_fin']);
    }

    /**
     * Affichage d'un résultat M04 pour son propre jeton.
     */
    public function test_affichage_resultat_m04_reussi(): void
    {
        $jeton = $this->resultStore->store(
            utilisateur: 'sonia.d',
            code: 'CPT_001',
            version: 1,
            parametres: ['agence' => '1'],
            colonnes: [
                ['cle' => 'num_compte', 'libelle' => 'N° Compte', 'format' => 'code'],
                ['cle' => 'client', 'libelle' => 'Client', 'format' => 'texte'],
            ],
            lignes: [
                ['num_compte' => '0001', 'client' => 'Jean'],
                ['num_compte' => '0002', 'client' => 'Paul'],
            ],
            dureeMs: 25,
            tronque: false,
            totalLignes: 2,
            parametresAffiches: ['Agence' => 'Agence Centre']
        );

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/resultats/{$jeton}");

        $response->assertStatus(200);
        $response->assertSee('EXTRACTION EXÉCUTÉE', false);
        $response->assertSee('Exécution terminée avec succès', false);
        $response->assertSee('PARAMÈTRES UTILISÉS', false);
        $response->assertSee('2 lignes retournées', false);
        $response->assertSee('Exporter en CSV', false);
    }

    /**
     * Affichage d'un résultat avec plafond atteint (D21).
     */
    public function test_affichage_resultat_plafond_atteint_d21(): void
    {
        $jeton = $this->resultStore->store(
            utilisateur: 'sonia.d',
            code: 'OPE_001',
            version: 1,
            parametres: ['agence' => '1'],
            colonnes: [['cle' => 'id', 'libelle' => 'ID']],
            lignes: array_fill(0, 10, ['id' => 1]),
            dureeMs: 50,
            tronque: true,
            totalLignes: 10,
            parametresAffiches: ['Agence' => 'Agence 1']
        );

        $response = $this->actingAs($this->user)
            ->get("/extractions/OPE_001/resultats/{$jeton}");

        $response->assertStatus(200);
        $response->assertSee('10 lignes (plafond atteint)', false);
        $response->assertSeeText('Résultat limité : affinez vos critères');
    }


    /**
     * Jeton appartenant à un autre utilisateur affiche la page expiré.
     */
    public function test_resultat_autre_utilisateur_affiche_expire(): void
    {
        $jeton = $this->resultStore->store(
            utilisateur: 'autre.user',
            code: 'CPT_001',
            version: 1,
            parametres: ['agence' => '1'],
            colonnes: [['cle' => 'id', 'libelle' => 'ID']],
            lignes: [['id' => 1]],
            dureeMs: 10
        );

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/resultats/{$jeton}");

        $response->assertSee('Ce résultat a expiré', false);
    }

    /**
     * Jeton expiré ou inconnu affiche la page expiré.
     */
    public function test_jeton_inconnu_affiche_page_expire(): void
    {
        $inconnu = str_repeat('z', 40);

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/resultats/{$inconnu}");

        $response->assertSee('Ce résultat a expiré', false);
    }

    /**
     * Simulation d'un délai maximal dépassé : message explicite renvoyé.
     */
    public function test_simulation_timeout_affiche_message_delai_depasse(): void
    {
        $mockRunner = $this->createMock(ExtractionRunner::class);
        $mockRunner->method('run')
            ->willThrowException(new \RuntimeException('ORA-01013: user requested cancel of current operation'));

        $this->app->instance(ExtractionRunner::class, $mockRunner);

        $response = $this->actingAs($this->user)
            ->from('/extractions/CPT_001')
            ->post('/extractions/CPT_001/execute', [
                'agence'           => '1',
                'date_debut'       => '2020-01-01',
                'date_fin'         => '2023-12-31',
                'inclure_clotures' => '0',
            ]);

        $response->assertRedirect('/extractions/CPT_001');
        $response->assertSessionHas('error');

        $error = session('error');
        $this->assertStringContainsString("dépassé le délai de", $error);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tests avec base Oracle réelle (groupe 'oracle')
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Cas de référence CPT_001 sur base Oracle : agence 1, 2018-01-01 à 2023-12-31, non clôturés = 192 lignes.
     */
    #[Group('oracle')]
    public function test_reference_cpt_001_agence_1_retourne_192_lignes(): void
    {
        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible.');
        }

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

        // Suivre la redirection
        $resultPage = $this->actingAs($this->user)->get($targetUrl);
        $resultPage->assertStatus(200);
        $resultPage->assertSee('192 lignes retournées', false);
    }

    /**
     * Cas de référence CLI_001 sur base Oracle : agence 4, ACTIF, nom TCHOUPO = 26 lignes.
     */
    #[Group('oracle')]
    public function test_reference_cli_001_agence_4_tchoupo_retourne_26_lignes(): void
    {
        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible.');
        }

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
    #[Group('oracle')]
    public function test_reference_ope_001_plafond_10000_lignes(): void
    {
        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base Oracle non disponible.');
        }

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

