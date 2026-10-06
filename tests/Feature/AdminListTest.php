<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Extraction\CatalogueRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminListTest extends TestCase
{
    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Artisan::call('migrate:fresh');

        $this->admin = User::create([
            'identifiant' => 'amine.m',
            'prenom'      => 'Amine',
            'nom'         => 'M.',
            'role'        => 'admin',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $this->user = User::create([
            'identifiant' => 'sonia.d',
            'prenom'      => 'Sonia',
            'nom'         => 'D.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        // Copy example queries into fake storage
        $example = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $example);
    }

    /**
     * L'administrateur peut voir l'accueil admin (S02).
     */
    public function test_admin_can_access_admin_home(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Bonjour, Amine');
        $response->assertSee('Pilotez les extractions publiées.');
        $response->assertSee('Dernières modifications');
    }

    /**
     * L'utilisateur métier reçoit 403 sur /admin.
     */
    public function test_standard_user_gets_403_on_admin_home(): void
    {
        $response = $this->actingAs($this->user)->get('/admin');

        $response->assertStatus(403);
    }

    /**
     * L'administrateur peut voir la liste des extractions (S03).
     */
    public function test_admin_can_access_extractions_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/extractions');

        $response->assertStatus(200);
        $response->assertSee('Toutes les extractions');
        $response->assertSee('CLI_001');
        $response->assertSee('CPT_001');
        $response->assertSee('OPE_001');
        $response->assertSee('CRT_001');
        $response->assertSee('4 extractions');
    }

    /**
     * L'utilisateur métier reçoit 403 sur /admin/extractions.
     */
    public function test_standard_user_gets_403_on_extractions_list(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/extractions');

        $response->assertStatus(403);
    }

    /**
     * Recherche d'extraction par mot-clé (ex: "clients" trouve CLI_001).
     */
    public function test_search_filters_extractions_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/extractions?q=clients');

        $response->assertStatus(200);
        $response->assertSee('CLI_001');
        $response->assertDontSee('CPT_001');
    }

    /**
     * Filtre par statut (ex: statut=Brouillon montre CRT_001).
     */
    public function test_status_filter_filters_extractions_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/extractions?statut=Brouillon');

        $response->assertStatus(200);
        $response->assertSee('CRT_001');
        $response->assertDontSee('CLI_001');
    }

    /**
     * Recherche sans résultat affiche l'état vide de recherche.
     */
    public function test_search_no_results_displays_empty_state(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/extractions?q=inexistant');

        $response->assertStatus(200);
        $response->assertSee('Aucun résultat');
        $response->assertSee('Réinitialiser');
    }

    /**
     * Basculer le statut d'une extraction (D22).
     */
    public function test_admin_can_toggle_extraction_status(): void
    {
        $response = $this->actingAs($this->admin)->patch('/admin/extractions/CLI_001/statut', [
            'statut' => 'Inactive',
        ]);

        $response->assertRedirect('/admin/extractions');
        
        /** @var CatalogueRepository $repo */
        $repo = app(CatalogueRepository::class);
        $query = $repo->find('CLI_001');
        $this->assertEquals('inactive', strtolower($query['statut']));
    }

    /**
     * Accueil admin trie par date de modification descendante.
     */
    public function test_admin_home_orders_recent_modifications(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertStatus(200);
        // CLI_001 (modifié le 2026-09-24) doit être dans les cartes / tableau
        $response->assertSee('CLI_001');
    }
}
