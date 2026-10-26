<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Extraction\CatalogueRepository;
use App\Services\Extraction\ExtractionRunner;
use App\Services\Extraction\ListOptionsProvider;
use App\Services\Extraction\ResultStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTestPublishTest extends TestCase
{
    protected User $admin;
    protected User $user;
    protected CatalogueRepository $repository;

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
        $this->repository = new CatalogueRepository();
    }

    /**
     * L'utilisateur métier reçoit 403 sur toutes les routes de test et statut.
     */
    public function test_user_metier_recoit_403_sur_les_routes_de_test_et_publication(): void
    {
        $this->actingAs($this->user)->get('/admin/extractions/CPT_001/test')->assertStatus(403);
        $this->actingAs($this->user)->post('/admin/extractions/CPT_001/test')->assertStatus(403);
        $this->actingAs($this->user)->get('/admin/extractions/CPT_001/test/resultat/fakejeton')->assertStatus(403);
        $this->actingAs($this->user)->patch('/admin/extractions/CPT_001/statut')->assertStatus(403);
    }

    /**
     * L'administrateur accède au formulaire de test S06.
     */
    public function test_admin_peut_acceder_au_formulaire_de_test_s06(): void
    {
        $optionsMock = $this->createMock(ListOptionsProvider::class);
        $optionsMock->method('getOptions')->willReturn([
            ['valeur' => '1', 'libelle' => 'Agence Principale'],
        ]);
        $this->app->instance(ListOptionsProvider::class, $optionsMock);

        $response = $this->actingAs($this->admin)->get('/admin/extractions/CPT_001/test');

        $response->assertStatus(200);
        $response->assertSeeText("Test de l'extraction CPT_001");
        $response->assertSee("APERÇU AVANT PUBLICATION");
        $response->assertSee("Paramètres d'exécution", false);
        $response->assertSee("Exécution sécurisée");
        $response->assertSee("Exécuter le test");
        $response->assertSee("L'aperçu est limité aux 100 premières lignes.", false);
    }

    /**
     * Les erreurs de validation des paramètres sont réaffichées.
     */
    public function test_validation_des_parametres_reffichee_en_cas_derreur(): void
    {
        $optionsMock = $this->createMock(ListOptionsProvider::class);
        $optionsMock->method('getOptions')->willReturn([]);
        $this->app->instance(ListOptionsProvider::class, $optionsMock);

        // date_fin antérieure à date_debut
        $response = $this->actingAs($this->admin)->post('/admin/extractions/CPT_001/test', [
            'date_debut' => '2026-10-01',
            'date_fin'   => '2026-09-01',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['date_fin']);
    }

    /**
     * Exécution d'un test réussi, enregistrement du résultat et de dernier_test.
     */
    public function test_execution_du_test_reussi_et_enregistrement_dernier_test(): void
    {
        $optionsMock = $this->createMock(ListOptionsProvider::class);
        $optionsMock->method('getOptions')->willReturn([
            ['valeur' => '1', 'libelle' => 'Agence Principale'],
        ]);
        $this->app->instance(ListOptionsProvider::class, $optionsMock);

        $runnerMock = $this->createMock(ExtractionRunner::class);
        $runnerMock->method('run')->willReturn([
            'columns'     => ['numero_compte', 'solde'],
            'rows'        => [
                ['numero_compte' => '000123', 'solde' => '150000'],
            ],
            'count'       => 1,
            'truncated'   => false,
            'duration_ms' => 45,
            'max_rows'    => 100,
        ]);
        $this->app->instance(ExtractionRunner::class, $runnerMock);

        $response = $this->actingAs($this->admin)->post('/admin/extractions/CPT_001/test', [
            'date_debut' => '2026-01-01',
            'date_fin'   => '2026-12-31',
            'agence'     => '1',
            'clotures'   => '0',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect();

        // Vérifier la mise à jour de dernier_test
        $updated = $this->repository->find('CPT_001');
        $this->assertNotNull($updated['dernier_test']);
        $this->assertSame('reussi', $updated['dernier_test']['resultat']);
        $this->assertEquals(1, $updated['dernier_test']['nb_lignes']);
        $this->assertSame('amine.m', $updated['dernier_test']['par']);

        // Vérifier l'écran de résultat S07
        $location = $response->headers->get('Location');
        $getRes = $this->actingAs($this->admin)->get($location);
        $getRes->assertStatus(200);
        $getRes->assertSee("APERÇU DE TEST");
        $getRes->assertSee("Exécution terminée avec succès");
        $getRes->assertSee("Résultats");
        $getRes->assertSee("Publier cette extraction");
    }

    /**
     * Un échec simulé de l'exécution enregistre dernier_test.resultat = echec.
     */
    public function test_echec_simule_de_lexecution_enregistre_dernier_test_en_echec(): void
    {
        $optionsMock = $this->createMock(ListOptionsProvider::class);
        $optionsMock->method('getOptions')->willReturn([]);
        $this->app->instance(ListOptionsProvider::class, $optionsMock);

        $runnerMock = $this->createMock(ExtractionRunner::class);
        $runnerMock->method('run')->willThrowException(new \RuntimeException("Simulation ORA-01013: user requested cancel of current operation"));
        $this->app->instance(ExtractionRunner::class, $runnerMock);

        $response = $this->actingAs($this->admin)->post('/admin/extractions/CPT_001/test', [
            'date_debut' => '2026-01-01',
            'date_fin'   => '2026-12-31',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $updated = $this->repository->find('CPT_001');
        $this->assertNotNull($updated['dernier_test']);
        $this->assertSame('echec', $updated['dernier_test']['resultat']);
    }

    /**
     * Publication refusée sans test réussi de la version courante.
     */
    public function test_publication_refusee_sans_test_reussi(): void
    {
        // CRT_001 est en brouillon, sans dernier_test
        $response = $this->actingAs($this->admin)->patch('/admin/extractions/CRT_001/statut', [
            'action' => 'publier',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $ext = $this->repository->find('CRT_001');
        $this->assertSame('brouillon', $ext['statut']);
    }

    /**
     * Publication refusée si la version courante a changé depuis le dernier test.
     */
    public function test_publication_refusee_si_la_version_a_change_depuis_le_test(): void
    {
        $ext = $this->repository->find('CRT_001');
        $ext['version'] = 3;
        $ext['dernier_test'] = [
            'version'   => 2, // version antérieure !
            'date'      => now()->toIso8601String(),
            'par'       => 'amine.m',
            'resultat'  => 'reussi',
            'nb_lignes' => 10,
            'duree_ms'  => 50,
        ];
        $this->repository->save($ext, 'amine.m');

        $response = $this->actingAs($this->admin)->patch('/admin/extractions/CRT_001/statut', [
            'action' => 'publier',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $extApres = $this->repository->find('CRT_001');
        $this->assertSame('brouillon', $extApres['statut']);
    }

    /**
     * Publication acceptée lorsque le test de la version courante a réussi.
     */
    public function test_publication_reussie_si_dernier_test_conforme(): void
    {
        $ext = $this->repository->find('CRT_001');
        $ext['version'] = 2;
        $ext['dernier_test'] = [
            'version'   => 2,
            'date'      => now()->toIso8601String(),
            'par'       => 'amine.m',
            'resultat'  => 'reussi',
            'nb_lignes' => 10,
            'duree_ms'  => 50,
        ];
        $this->repository->save($ext, 'amine.m');

        $response = $this->actingAs($this->admin)->patch('/admin/extractions/CRT_001/statut', [
            'action' => 'publier',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $extApres = $this->repository->find('CRT_001');
        $this->assertSame('active', $extApres['statut']);
        $this->assertNotNull($extApres['publie_le']);
        $this->assertSame('amine.m', $extApres['publie_par']);
    }

    /**
     * Désactivation puis réactivation d'une extraction.
     */
    public function test_desactivation_et_reactivation(): void
    {
        // 1. Désactivation de CPT_001 (active)
        $resDesact = $this->actingAs($this->admin)->patch('/admin/extractions/CPT_001/statut', [
            'action' => 'desactiver',
        ]);
        $resDesact->assertStatus(302);
        $resDesact->assertSessionHas('success');

        $ext = $this->repository->find('CPT_001');
        $this->assertSame('inactive', $ext['statut']);

        // 2. Réactivation (CPT_001 a un test réussi pour sa version courante)
        $resReact = $this->actingAs($this->admin)->patch('/admin/extractions/CPT_001/statut', [
            'action' => 'reactiver',
        ]);
        $resReact->assertStatus(302);
        $resReact->assertSessionHas('success');

        $extApres = $this->repository->find('CPT_001');
        $this->assertSame('active', $extApres['statut']);
    }

    /**
     * Test avec 0 ligne retournée : affiche l'avertissement mais le test reste réussi.
     */
    public function test_execution_du_test_avec_zero_ligne_affiche_avertissement_et_reste_reussi(): void
    {
        $optionsMock = $this->createMock(ListOptionsProvider::class);
        $optionsMock->method('getOptions')->willReturn([
            ['valeur' => '1', 'libelle' => 'Agence Principale'],
        ]);
        $this->app->instance(ListOptionsProvider::class, $optionsMock);

        $runnerMock = $this->createMock(ExtractionRunner::class);
        $runnerMock->method('run')->willReturn([
            'columns'     => ['numero_compte', 'solde'],
            'rows'        => [],
            'count'       => 0,
            'truncated'   => false,
            'duration_ms' => 30,
            'max_rows'    => 100,
        ]);
        $this->app->instance(ExtractionRunner::class, $runnerMock);

        $response = $this->actingAs($this->admin)->post('/admin/extractions/CPT_001/test', [
            'date_debut' => '2026-01-01',
            'date_fin'   => '2026-12-31',
            'agence'     => '1',
            'clotures'   => '0',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect();

        // Le dernier test est bien marqué comme 'reussi' avec 0 ligne
        $updated = $this->repository->find('CPT_001');
        $this->assertNotNull($updated['dernier_test']);
        $this->assertSame('reussi', $updated['dernier_test']['resultat']);
        $this->assertEquals(0, $updated['dernier_test']['nb_lignes']);

        // Sur l'écran de résultat S07 : présence de l'avertissement
        $location = $response->headers->get('Location');
        $getRes = $this->actingAs($this->admin)->get($location);
        $getRes->assertStatus(200);
        $getRes->assertSee("Exécution terminée avec succès");
        $getRes->assertSee("Aucune ligne : vérifiez les valeurs saisies");
        $getRes->assertSee("0 lignes retournées");
    }
}
