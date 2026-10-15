<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Extraction\ExecutionLogger;
use App\Services\Extraction\ResultStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    protected User $user;
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

        $this->otherUser = User::create([
            'identifiant' => 'autre.user',
            'prenom'      => 'Autre',
            'nom'         => 'User',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        // Catalogue d'exemple
        $example = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $example);

        $this->resultStore = new ResultStore();
    }

    /**
     * Vérifie l'export CSV complet depuis le résultat conservé.
     */
    public function test_export_csv_complet(): void
    {
        $jeton = $this->resultStore->store(
            utilisateur: 'sonia.d',
            code: 'CPT_001',
            version: 1,
            parametres: ['agence' => '1', 'inclure_clotures' => '0'],
            colonnes: [
                ['cle' => 'num_compte', 'libelle' => 'N° Compte', 'format' => 'code'],
                ['cle' => 'client', 'libelle' => 'Client Titulaire', 'format' => 'texte'],
                ['cle' => 'date_ouverture', 'libelle' => 'Date Ouverture', 'format' => 'date'],
                ['cle' => 'formule_test', 'libelle' => 'Formule Test', 'format' => 'texte'],
            ],
            lignes: [
                ['num_compte' => '000123', 'client' => 'Hervé Tchoupo', 'date_ouverture' => '15/01/2021', 'formule_test' => "'=SUM(1)"],
                ['num_compte' => '000124', 'client' => 'Société Ébène', 'date_ouverture' => '20/02/2022', 'formule_test' => 'Standard'],
            ],
            dureeMs: 42,
            tronque: false,
            totalLignes: 2,
            parametresAffiches: ['Agence' => 'Agence Centre'],
            rawLignes: [
                ['num_compte' => '000123', 'client' => 'Hervé Tchoupo', 'date_ouverture' => '2021-01-15', 'formule_test' => '=SUM(1)'],
                ['num_compte' => '000124', 'client' => 'Société Ébène', 'date_ouverture' => '2022-02-20', 'formule_test' => 'Standard'],
            ]
        );

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/csv/{$jeton}");

        $response->assertStatus(200);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('CPT_001_', (string) $response->headers->get('Content-Disposition'));

        // Récupérer le contenu du streaming
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // 1. Présence du BOM UTF-8
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // 2. Séparateur et en-têtes
        $lines = explode("\n", trim($content));
        $this->assertCount(3, $lines); // En-tête + 2 lignes de données

        $headerLine = substr($lines[0], 3); // retire le BOM
        $this->assertSame('"N° Compte";"Client Titulaire";"Date Ouverture";"Formule Test"', $headerLine);

        // 3. Neutralisation de formule : =SUM(1) devient '=SUM(1)
        $this->assertStringContainsString("'=SUM(1)", $lines[1]);
        $this->assertStringContainsString('15/01/2021', $lines[1]);
        $this->assertStringContainsString('Hervé Tchoupo', $lines[1]);

        // 4. Préservation des accents
        $this->assertStringContainsString('Société Ébène', $lines[2]);

        // 5. Journalisation en mode export_csv
        $logContent = Storage::disk('local')->get('extraction/execution_log.json');
        $this->assertNotNull($logContent);
        $logs = json_decode((string) $logContent, true);
        $this->assertNotEmpty($logs);

        $lastLog = end($logs);
        $this->assertSame('sonia.d', $lastLog['utilisateur']);
        $this->assertSame('CPT_001', $lastLog['code']);
        $this->assertSame('export_csv', $lastLog['mode']);
        $this->assertSame('succes', $lastLog['statut']);
        $this->assertSame(2, $lastLog['nb_lignes']);
    }

    /**
     * Un jeton appartenant à un autre utilisateur ne peut pas être téléchargé.
     */
    public function test_export_csv_refuse_pour_autre_utilisateur(): void
    {
        $jeton = $this->resultStore->store(
            utilisateur: 'autre.user',
            code: 'CPT_001',
            version: 1,
            parametres: ['agence' => '1'],
            colonnes: [['cle' => 'c1', 'libelle' => 'C1']],
            lignes: [['c1' => 'v1']],
            dureeMs: 10
        );

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/csv/{$jeton}");

        // Le contrôleur affiche la vue expire si le jeton n'appartient pas à l'utilisateur
        $response->assertSee('Ce résultat a expiré', false);
    }

    /**
     * Un jeton inconnu affiche la page de résultat expiré.
     */
    public function test_export_csv_jeton_inconnu_affiche_page_expire(): void
    {
        $inconnu = str_repeat('a', 40);

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/csv/{$inconnu}");

        $response->assertSee('Ce résultat a expiré', false);
        $response->assertSee("Relancer l'extraction", false);
    }
}
