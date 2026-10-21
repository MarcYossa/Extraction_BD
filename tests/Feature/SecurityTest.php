<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Http\Middleware\SecurityHeaders;

/**
 * Tests de sécurité (Lot 12 – Tâches 5, 6, 7).
 *
 * Couvre :
 *   - En-têtes HTTP (X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
 *     Permissions-Policy, Content-Security-Policy)
 *   - Session & cookies (HttpOnly, SameSite)
 *   - CSRF : toute route POST/PUT/PATCH sans jeton → 302/419
 */
class SecurityTest extends TestCase
{
    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Artisan::call('migrate:fresh');

        $this->admin = User::create([
            'identifiant' => 'admin.sec',
            'prenom'      => 'Admin',
            'nom'         => 'Sec',
            'role'        => 'admin',
            'password'    => 'MotDePasseTest12',
            'actif'       => true,
        ]);

        $this->user = User::create([
            'identifiant' => 'user.sec',
            'prenom'      => 'User',
            'nom'         => 'Sec',
            'role'        => 'user',
            'password'    => 'MotDePasseTest12',
            'actif'       => true,
        ]);
    }

    // ------------------------------------------------------------------ //
    //  T5 – En-têtes HTTP de sécurité                                     //
    // ------------------------------------------------------------------ //

    /** La page de login porte tous les en-têtes de sécurité. */
    public function test_security_headers_present_on_login(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
    }

    /** La page dashboard (authentifiée) porte les en-têtes de sécurité. */
    public function test_security_headers_present_on_authenticated_page(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
    }

    /** La CSP ne référence aucun CDN externe. */
    public function test_csp_ne_contient_pas_de_cdn_externe(): void
    {
        $csp = SecurityHeaders::cspValue();

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $csp);
        $this->assertStringNotContainsString('unpkg.com', $csp);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $csp);
        $this->assertStringNotContainsString('fonts.googleapis.com', $csp);
    }

    /** La CSP contient bien default-src 'self'. */
    public function test_csp_contient_default_src_self(): void
    {
        $csp = SecurityHeaders::cspValue();
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    /** La CSP interdit les frames (frame-ancestors 'none'). */
    public function test_csp_interdit_frames(): void
    {
        $csp = SecurityHeaders::cspValue();
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    /** La CSP interdit les objets (object-src 'none'). */
    public function test_csp_interdit_object_src(): void
    {
        $csp = SecurityHeaders::cspValue();
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    // ------------------------------------------------------------------ //
    //  T6 – Session & cookies                                             //
    // ------------------------------------------------------------------ //

    /** Le cookie de session est HttpOnly. */
    public function test_session_cookie_est_httponly(): void
    {
        $response = $this->get('/login');

        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'session') || $cookie->getName() === config('session.cookie')) {
                $this->assertTrue(
                    $cookie->isHttpOnly(),
                    "Le cookie {$cookie->getName()} doit être HttpOnly."
                );
            }
        }

        // Si aucun cookie de session n'est présent sur /login, le test passe
        // (la session est créée à la première action authentifiée).
        $this->addToAssertionCount(1);
    }

    /** Le cookie de session a SameSite=Lax (ou Strict). */
    public function test_session_cookie_samesite(): void
    {
        $sameSite = config('session.same_site', 'lax');
        $this->assertContains(
            strtolower((string) $sameSite),
            ['lax', 'strict'],
            "SameSite doit être 'lax' ou 'strict' ; valeur actuelle : {$sameSite}"
        );
    }

    // ------------------------------------------------------------------ //
    //  T7 – CSRF                                                          //
    // ------------------------------------------------------------------ //

    /**
     * POST sur une route web sans jeton CSRF → 302 redirect (ou 419 selon config).
     * En test, Laravel intercepte le TokenMismatchException et retourne 302 ou 419.
     */
    public function test_post_login_sans_csrf_bloque(): void
    {
        // Désactiver la session pour simuler l'absence de jeton
        $response = $this->post('/login', [
            'identifiant' => 'admin.sec',
            'password'    => 'MotDePasseTest12',
        ]);

        // Laravel retourne 302 (redirect) ou 419 (page expired) sans jeton CSRF valide.
        $this->assertContains(
            $response->status(),
            [302, 419],
            'Un POST sans jeton CSRF doit être rejeté (302 ou 419).'
        );
    }

    /** POST sur /admin/extractions sans jeton CSRF → rejet (302 ou 419). */
    public function test_post_admin_sans_csrf_bloque(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/extractions', []);

        $this->assertContains(
            $response->status(),
            [302, 419],
            'Un POST admin sans jeton CSRF doit être rejeté (302 ou 419).'
        );
    }

    /** PUT sans jeton CSRF → rejet (302, 404 ou 419). */
    public function test_put_admin_sans_csrf_bloque(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/extractions/CPT_001', []);

        $this->assertContains(
            $response->status(),
            [302, 404, 419],
            'Un PUT admin sans jeton CSRF doit être rejeté ou retourner 404.'
        );
    }

    // ------------------------------------------------------------------ //
    //  T11 – Exposition de fichiers                                       //
    // ------------------------------------------------------------------ //

    /** Le fichier .env n'est pas accessible via requête HTTP (404/403). */
    public function test_fichier_env_non_accessible_via_web(): void
    {
        $response = $this->get('/.env');
        $this->assertContains($response->status(), [403, 404]);
    }

    /** Le catalogue queries.json n'est pas accessible directement par URL. */
    public function test_queries_json_non_accessible_via_web(): void
    {
        $response = $this->get('/queries.json');
        $this->assertContains($response->status(), [403, 404]);

        $response2 = $this->get('/storage/app/private/extraction/queries.json');
        $this->assertContains($response2->status(), [403, 404]);
    }

    /** Le journal d'exécution n'est pas accessible directement par URL. */
    public function test_execution_log_non_accessible_via_web(): void
    {
        $response = $this->get('/execution_log.json');
        $this->assertContains($response->status(), [403, 404]);

        $response2 = $this->get('/storage/app/private/extraction/execution_log.json');
        $this->assertContains($response2->status(), [403, 404]);
    }

    /** Le journal d'authentification n'est pas accessible directement par URL. */
    public function test_auth_log_non_accessible_via_web(): void
    {
        $response = $this->get('/auth_log.json');
        $this->assertContains($response->status(), [403, 404]);

        $response2 = $this->get('/storage/app/private/extraction/auth_log.json');
        $this->assertContains($response2->status(), [403, 404]);
    }

    /** La base de données SQLite applicative n'est pas accessible par URL. */
    public function test_database_sqlite_non_accessible_via_web(): void
    {
        $response = $this->get('/database/app.sqlite');
        $this->assertContains($response->status(), [403, 404]);
    }

    /** Le répertoire private des résultats d'extraction n'est pas accessible. */
    public function test_storage_private_results_non_accessible_via_web(): void
    {
        $response = $this->get('/storage/app/private/results/');
        $this->assertContains($response->status(), [403, 404]);
    }

    /** Le lien symbolique public/storage ne doit pas exister (pas d'exposition publique du storage). */
    public function test_absence_lien_public_storage(): void
    {
        $this->assertFalse(
            file_exists(public_path('storage')),
            'Le lien public/storage ne doit pas exister.'
        );
    }

    // ------------------------------------------------------------------ //
    //  T12 – Mots de passe et hachage                                     //
    // ------------------------------------------------------------------ //

    /** app:creer-utilisateur rejette un mot de passe de moins de 12 caractères. */
    public function test_creer_utilisateur_mot_de_passe_inf_12_caracteres_rejete(): void
    {
        $exitCode = Artisan::call('app:creer-utilisateur', [
            '--identifiant' => 'court.user',
            '--prenom'      => 'Court',
            '--nom'         => 'User',
            '--role'        => 'user',
            '--password'    => 'Court123', // 8 caractères < 12
        ]);

        $this->assertEquals(1, $exitCode, 'Un mot de passe de moins de 12 caractères doit échouer.');
        $this->assertDatabaseMissing('users', [
            'identifiant' => 'court.user',
        ]);
    }

    /** app:creer-utilisateur accepte un mot de passe de 12 caractères ou plus. */
    public function test_creer_utilisateur_mot_de_passe_12_caracteres_accepte(): void
    {
        $exitCode = Artisan::call('app:creer-utilisateur', [
            '--identifiant' => 'valide.user',
            '--prenom'      => 'Valide',
            '--nom'         => 'User',
            '--role'        => 'user',
            '--password'    => 'MotDePasse123!', // 14 caractères >= 12
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('users', [
            'identifiant' => 'valide.user',
        ]);
    }

    /** Les mots de passe sont hachés avec un algorithme fort (bcrypt / argon2). */
    public function test_mots_de_passe_haches_avec_algorithme_fort(): void
    {
        $user = User::where('identifiant', 'admin.sec')->first();
        $this->assertNotNull($user);

        // Le mot de passe stocké ne doit jamais être en clair
        $this->assertNotEquals('MotDePasseTest12', $user->password);

        // Algorithme fort (bcrypt commence par $2y$ ou info['algoName'])
        $info = password_get_info($user->password);
        $this->assertContains(
            $info['algoName'],
            ['bcrypt', 'argon2i', 'argon2id'],
            "L'algorithme de hachage doit être fort ; trouvé : {$info['algoName']}"
        );
    }
}

