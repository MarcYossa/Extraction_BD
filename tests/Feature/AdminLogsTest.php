<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminLogsTest extends TestCase
{
    protected User $admin;
    protected User $metier;

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

        $this->metier = User::create([
            'identifiant' => 'sonia.d',
            'prenom'      => 'Sonia',
            'nom'         => 'D.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        // Données d'exemple pour execution_log.json
        $execLogs = [
            [
                'id'          => 'ACT-2841',
                'date'        => '2026-09-27T10:42:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'CLI_001',
                'titre'       => 'Liste des clients actifs',
                'mode'        => 'execution',
                'statut'      => 'succes',
                'nb_lignes'   => 150,
                'tronque'     => false,
                'duree_ms'    => 2400,
                'parametres'  => ['segment' => 'PART'],
            ],
            [
                'id'          => 'ACT-2840',
                'date'        => '2026-09-27T10:39:00Z',
                'utilisateur' => 'sonia.d',
                'code'        => 'CLI_001',
                'titre'       => 'Liste des clients actifs',
                'mode'        => 'export_csv',
                'statut'      => 'succes',
                'nb_lignes'   => 150,
                'tronque'     => false,
                'duree_ms'    => 800,
                'parametres'  => ['segment' => 'PART'],
            ],
        ];
        Storage::disk('local')->put('extraction/execution_log.json', json_encode($execLogs));

        // Données d'exemple pour auth_log.json
        $authLogs = [
            [
                'timestamp'   => '2026-09-27T08:31:00Z',
                'event'       => 'login_success',
                'identifiant' => 'amine.m',
                'ip'          => '127.0.0.1',
                'user_agent'  => 'Mozilla/5.0 PHPUnit Test',
            ],
        ];
        Storage::disk('local')->put('extraction/auth_log.json', json_encode($authLogs));
    }

    public function test_acces_non_authentifie_redirige_vers_login(): void
    {
        $response = $this->get(route('admin.logs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_utilisateur_metier_recoit_403(): void
    {
        $response = $this->actingAs($this->metier)->get(route('admin.logs.index'));
        $response->assertForbidden();
    }

    public function test_administrateur_peut_consulter_les_logs_executions(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.logs.index'));

        $response->assertOk();
        $response->assertSee("Suivre l'activité de la plateforme");
        $response->assertSee('Exécutions');
        $response->assertSee('ACT-2841');
        $response->assertSee('CLI_001');
        $response->assertSee('sonia.d');
        $response->assertSee('2,4 s');
    }

    public function test_administrateur_peut_consulter_les_logs_connexions(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.logs.index', ['onglet' => 'connexions']));

        $response->assertOk();
        $response->assertSee('Connexions');
        $response->assertSee('amine.m');
        $response->assertSee('Connexion réussie');
        $response->assertSee('127.0.0.1');
    }

    public function test_filtrage_des_logs_administrateur(): void
    {
        // Filtre sur mode export_csv
        $response = $this->actingAs($this->admin)->get(route('admin.logs.index', ['mode' => 'export_csv']));

        $response->assertOk();
        $response->assertSee('ACT-2840');
        $response->assertDontSee('ACT-2841');
    }

    public function test_export_csv_du_journal_neutralise_les_formules(): void
    {
        // Ajout d'une entrée avec formule malveillante
        $malicious = [
            [
                'id'          => '=CMD|calc',
                'date'        => '2026-09-27T12:00:00Z',
                'utilisateur' => '+hacker',
                'code'        => 'CLI_001',
                'mode'        => 'execution',
                'statut'      => 'succes',
            ],
        ];
        Storage::disk('local')->put('extraction/execution_log.json', json_encode($malicious));

        $response = $this->actingAs($this->admin)->get(route('admin.logs.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        // Vérifie la neutralisation avec apostrophe
        $this->assertStringContainsString("'=CMD|calc;", $content);
        $this->assertStringContainsString(";'+hacker;", $content);
    }
}
