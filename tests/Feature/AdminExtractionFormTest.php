<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Extraction\CatalogueRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminExtractionFormTest extends TestCase
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

        $example = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $example);
    }

    /**
     * L'administrateur accède au formulaire de création S04.
     */
    public function test_admin_can_access_create_form(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/extractions/create');

        $response->assertStatus(200);
        $response->assertSee('Créer une extraction');
        $response->assertSee('Informations générales');
        $response->assertSee('Requête SQL');
        $response->assertSee('Paramètres');
        $response->assertSee('Colonnes du résultat');
    }

    /**
     * L'utilisateur métier reçoit 403 sur le formulaire de création et d'édition.
     */
    public function test_standard_user_gets_403_on_form_pages(): void
    {
        $resCreate = $this->actingAs($this->user)->get('/admin/extractions/create');
        $resCreate->assertStatus(403);

        $resEdit = $this->actingAs($this->user)->get('/admin/extractions/CLI_001/edit');
        $resEdit->assertStatus(403);
    }

    /**
     * Route de vérification SQL : cas OK.
     */
    public function test_verifier_endpoint_returns_success_for_valid_sql(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/extractions/verifier', [
            'requete_sql' => "SELECT c.num_compte AS num_compte, c.statut AS statut FROM amp_test.compte c WHERE c.agence_id = :agence",
            'parametres'  => [
                ['nom' => 'agence', 'libelle' => 'Agence', 'type' => 'texte'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'valide'  => true,
            'message' => 'La requête respecte les règles de lecture.',
        ]);
    }

    /**
     * Route de vérification SQL : cas d'erreur (mot-clé interdit, paramètre manquant, doublon colonne).
     */
    public function test_verifier_endpoint_detects_errors(): void
    {
        // 1. Mot-clé interdit (DELETE)
        $res1 = $this->actingAs($this->admin)->postJson('/admin/extractions/verifier', [
            'requete_sql' => "DELETE FROM amp_test.compte",
            'parametres'  => [],
        ]);
        $res1->assertJson(['valide' => false]);

        // 2. Paramètre utilisé dans le SQL mais non déclaré
        $res2 = $this->actingAs($this->admin)->postJson('/admin/extractions/verifier', [
            'requete_sql' => "SELECT c.num_compte FROM amp_test.compte c WHERE c.agence_id = :agence",
            'parametres'  => [],
        ]);
        $res2->assertJson(['valide' => false]);

        // 3. Doublons de colonnes dans le SELECT
        $res3 = $this->actingAs($this->admin)->postJson('/admin/extractions/verifier', [
            'requete_sql' => "SELECT c.num_compte AS code, a.nom_agence AS code FROM amp_test.compte c JOIN amp_test.agence a ON a.agence_id = c.agence_id",
            'parametres'  => [],
        ]);
        $res3->assertJson(['valide' => false]);
    }

    /**
     * Enregistrement d'une nouvelle extraction (store) en brouillon.
     */
    public function test_admin_can_store_new_extraction(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/extractions', [
            'code'        => 'CPT_099',
            'titre'       => 'Test Nouvelle Extraction',
            'description' => 'Description d\'une nouvelle extraction de test',
            'requete_sql' => "SELECT c.num_compte AS num_compte FROM amp_test.compte c WHERE c.agence_id = :agence",
            'parametres'  => [
                [
                    'nom'         => 'agence',
                    'libelle'     => 'Agence',
                    'type'        => 'texte',
                    'obligatoire' => '1',
                ],
            ],
            'colonnes'    => [
                ['cle' => 'num_compte', 'libelle' => 'N° compte', 'format' => 'code'],
            ],
            'action'      => 'enregistrer',
        ]);

        $response->assertRedirect('/admin/extractions');

        /** @var CatalogueRepository $repo */
        $repo = app(CatalogueRepository::class);
        $newExt = $repo->find('CPT_099');

        $this->assertNotNull($newExt);
        $this->assertEquals('Test Nouvelle Extraction', $newExt['titre']);
        $this->assertEquals('brouillon', $newExt['statut']);
        $this->assertEquals(1, $newExt['version']);
    }

    /**
     * Refus si le code est déjà utilisé lors de la création.
     */
    public function test_store_fails_if_code_already_exists(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/extractions', [
            'code'        => 'CLI_001', // Déjà existant
            'titre'       => 'Doublon Code',
            'description' => 'Test de doublon de code',
            'requete_sql' => "SELECT cl.num_client FROM amp_test.client cl",
            'parametres'  => [],
        ]);

        $response->assertSessionHasErrors('code');
    }

    /**
     * Modification d'une extraction active (update) : rétrogradation en brouillon et increment version (D11).
     */
    public function test_update_active_extraction_reverts_to_brouillon_and_increments_version(): void
    {
        /** @var CatalogueRepository $repo */
        $repo = app(CatalogueRepository::class);
        $initial = $repo->find('CLI_001');

        $this->assertEquals('active', $initial['statut']);
        $this->assertEquals(2, $initial['version']);

        $response = $this->actingAs($this->admin)->put('/admin/extractions/CLI_001', [
            'titre'       => 'Recherche de clients (Modifiée)',
            'description' => 'Description mise à jour',
            'requete_sql' => "SELECT cl.num_client AS num_client FROM amp_test.client cl WHERE cl.statut = :statut",
            'parametres'  => [
                [
                    'nom'         => 'statut',
                    'libelle'     => 'Statut',
                    'type'        => 'texte',
                    'obligatoire' => '1',
                ],
            ],
            'colonnes'    => [
                ['cle' => 'num_client', 'libelle' => 'N° client', 'format' => 'code'],
            ],
            'action'      => 'enregistrer',
        ]);

        $response->assertRedirect('/admin/extractions');

        $updated = $repo->find('CLI_001');
        $this->assertEquals('Recherche de clients (Modifiée)', $updated['titre']);
        $this->assertEquals('brouillon', $updated['statut']); // Rétrogradé (D11)
        $this->assertEquals(3, $updated['version']); // Increment +1
        $this->assertNull($updated['dernier_test']); // Remis à null
    }
}
