<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests fonctionnels pour l'espace métier : accueil (M01) et catalogue (M02).
 *
 * Règles vérifiées :
 *  - Seules les extractions « active » sont visibles (pas les brouillons)
 *  - L'administrateur est redirigé vers /admin (D14)
 *  - Un invité est renvoyé vers /login
 *  - L'URL directe d'un brouillon ou d'une extraction inactive → 404
 *  - Tri par date de publication décroissante
 *  - Présence de la barre de recherche
 */
class MetierCatalogueTest extends TestCase
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

        // Charge le catalogue d'exemple dans le stockage simulé
        $example = file_get_contents(base_path('docs/design/catalogue/queries.example.json'));
        Storage::disk('local')->put('extraction/queries.json', $example);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Contrôle d'accès
    // ──────────────────────────────────────────────────────────────────────────

    /** Un invité accédant à /dashboard est redirigé vers /login. */
    public function test_invité_redirigé_vers_login_depuis_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    /** Un invité accédant à /catalogue est redirigé vers /login. */
    public function test_invité_redirigé_vers_login_depuis_catalogue(): void
    {
        $this->get('/catalogue')->assertRedirect('/login');
    }

    /** L'administrateur ouvrant /dashboard est redirigé vers /admin (D14). */
    public function test_admin_redirigé_vers_admin_depuis_dashboard(): void
    {
        $this->actingAs($this->admin)
             ->get('/dashboard')
             ->assertRedirect('/admin');
    }

    /** L'administrateur ouvrant /catalogue est redirigé vers /admin (D14). */
    public function test_admin_redirigé_vers_admin_depuis_catalogue(): void
    {
        $this->actingAs($this->admin)
             ->get('/catalogue')
             ->assertRedirect('/admin');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // M01 — Accueil
    // ──────────────────────────────────────────────────────────────────────────

    /** L'utilisateur métier accède à l'accueil (HTTP 200). */
    public function test_utilisateur_accède_au_dashboard(): void
    {
        $this->actingAs($this->user)
             ->get('/dashboard')
             ->assertStatus(200);
    }

    /** La page d'accueil affiche la salutation personnalisée. */
    public function test_dashboard_affiche_salutation_prénom(): void
    {
        $this->actingAs($this->user)
             ->get('/dashboard')
             ->assertSee('Bonjour, Sonia');
    }

    /** La page d'accueil affiche les 3 extractions actives les plus récentes. */
    public function test_dashboard_affiche_les_3_plus_récentes(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);

        // Les 3 actives par ordre décroissant : CPT_001, CLI_001, OPE_001
        $response->assertSee('CPT_001');
        $response->assertSee('CLI_001');
        $response->assertSee('OPE_001');
    }

    /** CRT_001 (brouillon) est absent de l'accueil. */
    public function test_dashboard_ne_montre_pas_les_brouillons(): void
    {
        $this->actingAs($this->user)
             ->get('/dashboard')
             ->assertDontSee('CRT_001');
    }

    /** La page d'accueil contient le bouton « Parcourir le catalogue ». */
    public function test_dashboard_contient_bouton_catalogue(): void
    {
        $this->actingAs($this->user)
             ->get('/dashboard')
             ->assertSee('Parcourir le catalogue');
    }

    /** La page d'accueil contient le lien « Voir le catalogue ». */
    public function test_dashboard_contient_lien_voir_catalogue(): void
    {
        $this->actingAs($this->user)
             ->get('/dashboard')
             ->assertSee('Voir le catalogue');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // M02 — Catalogue
    // ──────────────────────────────────────────────────────────────────────────

    /** L'utilisateur métier accède au catalogue (HTTP 200). */
    public function test_utilisateur_accède_au_catalogue(): void
    {
        $this->actingAs($this->user)
             ->get('/catalogue')
             ->assertStatus(200);
    }

    /** Le catalogue affiche toutes les extractions actives. */
    public function test_catalogue_affiche_les_actives(): void
    {
        $response = $this->actingAs($this->user)->get('/catalogue');
        $response->assertSee('CPT_001');
        $response->assertSee('CLI_001');
        $response->assertSee('OPE_001');
    }

    /** Le catalogue n'affiche pas les brouillons. */
    public function test_catalogue_ne_montre_pas_les_brouillons(): void
    {
        $this->actingAs($this->user)
             ->get('/catalogue')
             ->assertDontSee('CRT_001');
    }

    /** La barre de recherche est présente sur le catalogue. */
    public function test_catalogue_contient_barre_de_recherche(): void
    {
        $this->actingAs($this->user)
             ->get('/catalogue')
             ->assertSee('catalogue-recherche', false);
    }

    /** Le catalogue affiche le sous-titre attendu. */
    public function test_catalogue_affiche_sous_titre(): void
    {
        $this->actingAs($this->user)
             ->get('/catalogue')
             ->assertSee('Choisir une extraction');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Accès direct à une extraction
    // ──────────────────────────────────────────────────────────────────────────

    /** L'URL d'une extraction active renvoie 200 (page M03 de saisie des paramètres). */
    public function test_extraction_active_retourne_200(): void
    {
        $this->actingAs($this->user)
             ->get('/extractions/CPT_001')
             ->assertStatus(200)
             ->assertSee("Paramètres d'exécution", false);
    }



    /** L'URL directe d'un brouillon renvoie 404. */
    public function test_brouillon_retourne_404(): void
    {
        $this->actingAs($this->user)
             ->get('/extractions/CRT_001')
             ->assertStatus(404);
    }

    /** Une extraction inexistante retourne 404. */
    public function test_extraction_inexistante_retourne_404(): void
    {
        $this->actingAs($this->user)
             ->get('/extractions/ZZZ_999')
             ->assertStatus(404);
    }

    /** L'administrateur accédant à une extraction active est redirigé vers /admin. */
    public function test_admin_redirigé_vers_admin_depuis_extraction(): void
    {
        $this->actingAs($this->admin)
             ->get('/extractions/CPT_001')
             ->assertRedirect('/admin');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tri et ordre
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Les extractions de l'accueil sont dans l'ordre décroissant de publication.
     * CPT_001 (2026-09-24) > CLI_001 (2026-09-22) > OPE_001 (2026-09-18).
     */
    public function test_ordre_décroissant_par_date_publication(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $content  = $response->getContent();

        $posCpt = strpos($content, 'CPT_001');
        $posCli = strpos($content, 'CLI_001');
        $posOpe = strpos($content, 'OPE_001');

        $this->assertLessThan($posCli, $posCpt, 'CPT_001 devrait apparaître avant CLI_001');
        $this->assertLessThan($posOpe, $posCli, 'CLI_001 devrait apparaître avant OPE_001');
    }
}
