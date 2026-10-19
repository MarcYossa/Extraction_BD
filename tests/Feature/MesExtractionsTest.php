<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MesExtractionsTest extends TestCase
{
    protected User $admin;
    protected User $metier1;
    protected User $metier2;

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

        $this->metier1 = User::create([
            'identifiant' => 'sonia.d',
            'prenom'      => 'Sonia',
            'nom'         => 'D.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $this->metier2 = User::create([
            'identifiant' => 'fatou.s',
            'prenom'      => 'Fatou',
            'nom'         => 'S.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        // Catalogue d'exemple avec CPT_001 actif et RIS_003 inactif
        $example = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $example);

        // Journal d'exécutions
        $execLogs = [
            [
                'id'          => 'ACT-1001',
                'date'        => '2026-09-27T10:42:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'CPT_001',
                'titre'       => 'Soldes des comptes',
                'mode'        => 'execution',
                'statut'      => 'succes',
                'nb_lignes'   => 45,
                'duree_ms'    => 1200,
                'parametres'  => ['date_debut' => '2026-01-15', 'date_fin' => '2026-01-30'],
            ],
            [
                'id'          => 'ACT-1002',
                'date'        => '2026-09-27T11:00:00Z',
                'utilisateur' => 'fatou.s',
                'code'        => 'CPT_001',
                'titre'       => 'Soldes des comptes',
                'mode'        => 'execution',
                'statut'      => 'succes',
                'nb_lignes'   => 80,
                'duree_ms'    => 1500,
                'parametres'  => ['devise' => 'USD'],
            ],
            [
                'id'          => 'ACT-1003',
                'date'        => '2026-09-27T09:00:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'RIS_003',
                'titre'       => 'Encours par agence',
                'mode'        => 'execution',
                'statut'      => 'succes',
                'nb_lignes'   => 12,
                'duree_ms'    => 600,
                'parametres'  => ['code_agence' => '001'],
            ],
        ];
        Storage::disk('local')->put('extraction/execution_log.json', json_encode($execLogs));
    }

    public function test_visiteur_non_authentifie_redirige_vers_login(): void
    {
        $response = $this->get(route('mes-extractions'));
        $response->assertRedirect(route('login'));
    }

    public function test_administrateur_redirige_vers_espace_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('mes-extractions'));
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_utilisateur_metier_voit_son_historique(): void
    {
        $response = $this->actingAs($this->metier1)->get(route('mes-extractions'));

        $response->assertOk();
        $response->assertSee('Retrouver mes extractions');
        $response->assertSee('ACT-1001');
        $response->assertSee('CPT_001');
        $response->assertSee('1,2 s');
    }

    public function test_isolation_stricte_entre_utilisateurs_meme_avec_parametre_url(): void
    {
        // sonia.d tente de voir les données de fatou.s via query string
        $response = $this->actingAs($this->metier1)->get(route('mes-extractions', ['utilisateur' => 'fatou.s']));

        $response->assertOk();
        // Doit voir ses propres logs
        $response->assertSee('ACT-1001');
        // Ne doit ABSOLUMENT PAS voir l'activité de fatou.s
        $response->assertDontSee('ACT-1002');
    }

    public function test_relance_extraction_active_avec_parametres_pre_remplis(): void
    {
        $response = $this->actingAs($this->metier1)->get(route('extractions.show', [
            'code'   => 'CPT_001',
            'params' => ['date_debut' => '2026-01-15'],
        ]));

        $response->assertOk();
        $response->assertSee('2026-01-15');
    }

    public function test_extraction_inactive_affiche_bouton_desactive(): void
    {
        $response = $this->actingAs($this->metier1)->get(route('mes-extractions'));

        $response->assertOk();
        // Ligne de l'extraction RIS_003 (inactive dans queries.example.json)
        $response->assertSee('btn-relancer-disabled-ACT-1003', false);
        $response->assertSee('Extraction inactive');
    }

    public function test_export_csv_historique_personnel(): void
    {
        $response = $this->actingAs($this->metier1)->get(route('mes-extractions.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('ACT-1001', $content);
        $this->assertStringNotContainsString('ACT-1002', $content); // Ne contient pas l'activité de fatou.s
    }

    public function test_recherche_textuelle_ne_permet_pas_de_fuiter_les_donnees_d_un_autre_utilisateur(): void
    {
        // sonia.d recherche le nom d'un autre utilisateur
        $response = $this->actingAs($this->metier1)->get(route('mes-extractions', ['q' => 'fatou.s']));

        $response->assertOk();
        $response->assertDontSee('ACT-1002');
        $response->assertSee('Aucune extraction trouvée');
    }

    public function test_visibilite_menus_selon_le_role(): void
    {
        // Admin voit Journal d'activité mais pas Mon historique
        $adminResp = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $adminResp->assertOk();
        $adminResp->assertSee("Journal d'activité", false);
        $adminResp->assertDontSee('Mon historique');

        // Métier voit Mon historique mais pas Journal d'activité
        $metierResp = $this->actingAs($this->metier1)->get(route('dashboard'));
        $metierResp->assertOk();
        $metierResp->assertSee('Mon historique');
        $metierResp->assertDontSee("Journal d'activité", false);
    }

    public function test_export_csv_neutralise_formules_pour_utilisateur_metier(): void
    {
        // Log contenant une formule malicieuse pour sonia.d
        $execLogs = [
            [
                'id'          => '=1+1',
                'date'        => '2026-09-27T10:42:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'EXT_MALICIOUS',
                'titre'       => '+HYPERLINK',
                'mode'        => 'execution',
                'statut'      => 'succes',
                'nb_lignes'   => 10,
                'duree_ms'    => 500,
                'parametres'  => ['formule' => '=SUM(A1:A10)'],
            ],
        ];
        Storage::disk('local')->put('extraction/execution_log.json', json_encode($execLogs));

        $response = $this->actingAs($this->metier1)->get(route('mes-extractions.export'));
        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString("'=1+1;", $content);
        $this->assertStringContainsString(";'+HYPERLINK;", $content);
    }
}
