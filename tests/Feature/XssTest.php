<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Extraction\ResultStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests de protection contre les failles XSS (Lot 12 – Tâche 9).
 *
 * Vérifie :
 *   1. L'absence d'utilisation non justifiée de {!! dans les vues Blade.
 *   2. L'échappement systématique d'une charge XSS (<script>alert(1)</script>)
 *      dans un paramètre texte affiché.
 *   3. L'échappement systématique dans le titre d'une extraction.
 *   4. L'échappement systématique dans une donnée de résultat (nom de client).
 *   5. L'échappement systématique dans le champ de recherche du catalogue.
 */
class XssTest extends TestCase
{
    protected User $user;
    protected User $admin;
    protected ResultStore $resultStore;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Artisan::call('migrate:fresh');

        $this->user = User::create([
            'identifiant' => 'xss.user',
            'prenom'      => 'User',
            'nom'         => 'Xss',
            'role'        => 'user',
            'password'    => 'MotDePasseTest12',
            'actif'       => true,
        ]);

        $this->admin = User::create([
            'identifiant' => 'xss.admin',
            'prenom'      => 'Admin',
            'nom'         => 'Xss',
            'role'        => 'admin',
            'password'    => 'MotDePasseTest12',
            'actif'       => true,
        ]);

        $this->resultStore = new ResultStore();

        // Charger le catalogue d'exemple dans le stockage simulé
        $example = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $example);
    }

    /**
     * 1. Vérifie que toutes les vues Blade n'utilisent pas {!! sauf justification explicite.
     * La seule utilisation autorisée est dans components/icon.blade.php pour injecter les SVG locaux.
     */
    public function test_aucune_utilisation_injustifiee_de_raw_blade(): void
    {
        $viewsPath = resource_path('views');
        $bladeFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath)
        );

        $violatingFiles = [];

        foreach ($bladeFiles as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $content = file_get_contents($file->getRealPath());

                // Chercher les balises {!! ... !!}
                if (preg_match_all('/\{\!\!(.*?)\!\}/s', $content, $matches)) {
                    $relative = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                    $relative = str_replace('\\', '/', $relative);

                    // Seul components/icon.blade.php est autorisé pour le SVG local
                    if ($relative !== 'components/icon.blade.php') {
                        $violatingFiles[$relative] = $matches[0];
                    }
                }
            }
        }

        $this->assertEmpty(
            $violatingFiles,
            'Des balises {!! non autorisées ont été trouvées dans les vues : ' . json_encode($violatingFiles)
        );
    }

    /**
     * 2. Une charge XSS dans un paramètre texte est toujours affichée échappée.
     */
    public function test_charge_xss_dans_parametre_affiche_echappee(): void
    {
        $xssPayload = '<script>alert(1)</script>';

        $jeton = $this->resultStore->store(
            utilisateur: 'xss.user',
            code: 'CPT_001',
            version: 1,
            parametres: ['agence' => $xssPayload],
            colonnes: [
                ['cle' => 'num_compte', 'libelle' => 'N° Compte', 'format' => 'code'],
            ],
            lignes: [
                ['num_compte' => '0001'],
            ],
            dureeMs: 15,
            tronque: false,
            totalLignes: 1,
            parametresAffiches: ['Agence' => $xssPayload]
        );

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/resultats/{$jeton}");

        $response->assertStatus(200);
        // Ne doit JAMAIS contenir la balise script brute
        $response->assertDontSee($xssPayload, false);
        // Doit contenir la version échappée HTML
        $response->assertSee(htmlspecialchars($xssPayload, ENT_QUOTES, 'UTF-8'), false);
    }

    /**
     * 3. Une charge XSS dans le titre d'une extraction est toujours affichée échappée.
     */
    public function test_charge_xss_dans_titre_extraction_echappee(): void
    {
        $xssTitle = '<script>alert(1)</script>';

        $catalogue = [
            'schema_version' => 1,
            'modifie_le' => '2026-10-21T18:00:00+01:00',
            'extractions' => [
                [
                    'code' => 'XSS_001',
                    'version' => 1,
                    'titre' => $xssTitle,
                    'description' => 'Test sécurité XSS dans le titre.',
                    'statut' => 'active',
                    'categorie' => 'Comptes',
                    'requete_sql' => ['SELECT 1 FROM DUAL'],
                    'parametres' => [],
                    'colonnes' => [
                        ['cle' => 'col1', 'libelle' => 'Col 1', 'type' => 'CHAINE', 'format' => 'texte'],
                    ],
                ],
            ],
        ];

        Storage::disk('local')->put('extraction/queries.json', json_encode($catalogue));

        // Consultation du catalogue côté utilisateur métier (données injectées en JSON sécurisé pour Alpine)
        $response = $this->actingAs($this->user)->get('/catalogue');
        $response->assertStatus(200);
        $response->assertDontSee($xssTitle, false);

        // Consultation de la fiche extraction côté utilisateur métier
        $responseShow = $this->actingAs($this->user)->get('/extractions/XSS_001');
        $responseShow->assertStatus(200);
        $responseShow->assertDontSee($xssTitle, false);
        $responseShow->assertSee(htmlspecialchars($xssTitle, ENT_QUOTES, 'UTF-8'), false);

        // Consultation de la liste côté administrateur
        $responseAdmin = $this->actingAs($this->admin)->get('/admin/extractions');
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertDontSee($xssTitle, false);
        $responseAdmin->assertSee(htmlspecialchars($xssTitle, ENT_QUOTES, 'UTF-8'), false);
    }

    /**
     * 4. Une charge XSS dans les données de résultat (nom de client) est toujours affichée échappée.
     */
    public function test_charge_xss_dans_donnees_resultats_echappee(): void
    {
        $xssClientName = '<script>alert("XSS-Client")</script>';

        $jeton = $this->resultStore->store(
            utilisateur: 'xss.user',
            code: 'CPT_001',
            version: 1,
            parametres: ['agence' => '1'],
            colonnes: [
                ['cle' => 'num_compte', 'libelle' => 'N° Compte', 'format' => 'code'],
                ['cle' => 'nom_client', 'libelle' => 'Nom du client', 'format' => 'texte'],
            ],
            lignes: [
                ['num_compte' => 'CPT-999', 'nom_client' => $xssClientName],
            ],
            dureeMs: 20,
            tronque: false,
            totalLignes: 1,
            parametresAffiches: ['Agence' => 'Agence Principale']
        );

        $response = $this->actingAs($this->user)
            ->get("/extractions/CPT_001/resultats/{$jeton}");

        $response->assertStatus(200);
        // Le script brut ne doit pas apparaître dans la réponse HTML
        $response->assertDontSee($xssClientName, false);
        $response->assertDontSee('<script>', false);
        // Les balises sont neutralisées sous forme unicode JSON hex (\u003Cscript\u003E)
        $response->assertSee('\u003Cscript\u003E', false);
    }

    /**
     * 5. Une charge XSS dans la recherche du catalogue est affichée échappée dans l'input ou l'en-tête.
     */
    public function test_charge_xss_dans_recherche_catalogue_echappee(): void
    {
        $xssSearch = '"><script>alert(1)</script>';

        $response = $this->actingAs($this->user)
            ->get('/catalogue?recherche=' . urlencode($xssSearch));

        $response->assertStatus(200);
        $response->assertDontSee($xssSearch, false);
    }
}
