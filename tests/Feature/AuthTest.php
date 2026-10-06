<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Artisan::call('migrate:fresh');
    }

    /**
     * L'écran de connexion s'affiche correctement.
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Connexion');
    }

    /**
     * Un administrateur est redirigé vers /admin après connexion.
     */
    public function test_admin_user_can_authenticate_and_redirects_to_admin_dashboard(): void
    {
        $admin = User::create([
            'identifiant' => 'amine.m',
            'prenom'      => 'Amine',
            'nom'         => 'M.',
            'role'        => 'admin',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $response = $this->post('/login', [
            'identifiant' => 'amine.m',
            'password'    => 'secret123',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard'));
    }

    /**
     * Un utilisateur métier est redirigé vers /dashboard après connexion.
     */
    public function test_standard_user_can_authenticate_and_redirects_to_user_dashboard(): void
    {
        $user = User::create([
            'identifiant' => 'sonia.d',
            'prenom'      => 'Sonia',
            'nom'         => 'D.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $response = $this->post('/login', [
            'identifiant' => 'sonia.d',
            'password'    => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Authentification refusée avec un mauvais mot de passe.
     */
    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        User::create([
            'identifiant' => 'amine.m',
            'prenom'      => 'Amine',
            'nom'         => 'M.',
            'role'        => 'admin',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $response = $this->post('/login', [
            'identifiant' => 'amine.m',
            'password'    => 'mauvais-mdp',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('identifiant');
    }

    /**
     * Verrouillage après 5 échecs consécutifs.
     */
    public function test_rate_limiting_locks_out_after_five_failed_attempts(): void
    {
        User::create([
            'identifiant' => 'test.user',
            'prenom'      => 'Test',
            'nom'         => 'User',
            'role'        => 'user',
            'password'    => 'password123',
            'actif'       => true,
        ]);

        // 5 tentatives infructueuses
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'identifiant' => 'test.user',
                'password'    => 'bad-password',
            ]);
        }

        // 6ème tentative : doit être bloquée par le limiteur
        $response = $this->post('/login', [
            'identifiant' => 'test.user',
            'password'    => 'bad-password',
        ]);

        $response->assertSessionHas('error_type', 'lockout');
        $this->assertGuest();
    }

    /**
     * Un invité est redirigé vers /login lorsqu'il accède à une route protégée.
     */
    public function test_guest_redirected_to_login_when_accessing_protected_route(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * Un utilisateur métier reçoit une erreur HTTP 403 s'il tente d'accéder à /admin.
     */
    public function test_non_admin_user_gets_403_when_accessing_admin_routes(): void
    {
        $user = User::create([
            'identifiant' => 'sonia.d',
            'prenom'      => 'Sonia',
            'nom'         => 'D.',
            'role'        => 'user',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    /**
     * Déconnexion d'un utilisateur.
     */
    public function test_user_can_logout(): void
    {
        $user = User::create([
            'identifiant' => 'amine.m',
            'prenom'      => 'Amine',
            'nom'         => 'M.',
            'role'        => 'admin',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    /**
     * Vérification du hachage du mot de passe.
     */
    public function test_passwords_are_stored_hashed(): void
    {
        $user = User::create([
            'identifiant' => 'user.test',
            'prenom'      => 'User',
            'nom'         => 'Test',
            'role'        => 'user',
            'password'    => 'monMotDePasse',
            'actif'       => true,
        ]);

        $this->assertNotEquals('monMotDePasse', $user->password);
        $this->assertTrue(Hash::check('monMotDePasse', $user->password));
    }

    /**
     * Journalisation des événements d'authentification dans auth_log.json.
     */
    public function test_auth_events_are_logged_in_auth_log_json(): void
    {
        $user = User::create([
            'identifiant' => 'amine.m',
            'prenom'      => 'Amine',
            'nom'         => 'M.',
            'role'        => 'admin',
            'password'    => 'secret123',
            'actif'       => true,
        ]);

        // Connexion réussie
        $this->post('/login', [
            'identifiant' => 'amine.m',
            'password'    => 'secret123',
        ]);

        Storage::disk('local')->assertExists('extraction/auth_log.json');
        $content = Storage::disk('local')->get('extraction/auth_log.json');
        $log = json_decode($content, true);

        $this->assertNotEmpty($log);
        $this->assertEquals('login_success', $log[0]['event']);
        $this->assertEquals('amine.m', $log[0]['identifiant']);
    }

    /**
     * Test de la commande Artisan app:creer-utilisateur.
     */
    public function test_creer_utilisateur_artisan_command(): void
    {
        $exitCode = Artisan::call('app:creer-utilisateur', [
            '--identifiant' => 'pierre.d',
            '--prenom'      => 'Pierre',
            '--nom'         => 'Durand',
            '--role'        => 'admin',
            '--password'    => 'motdepasse123',
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('users', [
            'identifiant' => 'pierre.d',
            'role'        => 'admin',
        ]);
    }
}
