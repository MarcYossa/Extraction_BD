<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Matrice rôles × routes automatisée (Lot 12 – Tâche 2).
 *
 * Pour chaque route déclarée dans $matrix :
 *   - invité     → code attendu (302 vers /login, ou 404 pour les routes système)
 *   - user       → code attendu
 *   - admin      → code attendu
 *
 * Toute route NON présente dans $matrix déclenche un échec explicite : cela force
 * le développeur à documenter les droits de chaque nouvelle route.
 *
 * Comportements notables :
 *   - EnsureIsUser (middleware 'user') : un admin est redirigé (302) vers /admin
 *     au lieu d'obtenir 403. Décision D14 : les deux espaces sont strictement séparés.
 *   - EnsureIsAdmin (middleware 'admin') : un user reçoit 403.
 *   - Les routes POST/PUT/PATCH sont testées SANS jeton CSRF.
 *     Pour l'invité le redirect (302) se produit avant le contrôle CSRF.
 *     Pour les rôles authentifiés : 419 (TokenMismatchException).
 *   - /dev/styleguide n'est enregistrée qu'en env 'local' → 404 en test.
 *   - CPT_001 n'existe pas dans le catalogue de test → 404 pour les rôles autorisés.
 *   - Les exports (StreamedResponse) retournent le status() via getStatusCode().
 */
class RoutesMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Artisan::call('migrate:fresh');
    }

    // ------------------------------------------------------------------ //
    //  Matrice de référence                                                //
    //                                                                      //
    //  Format : 'METHOD uri' => [guest, user, admin]                      //
    // ------------------------------------------------------------------ //
    private array $matrix = [
        // ── Routes publiques / auth ──────────────────────────────────────
        'GET /'                                           => [302, 302, 302],
        'GET /login'                                      => [200, 302, 302],
        'POST /login'                                     => [302, 302, 302],
        'POST /logout'                                    => [302, 302, 302],

        // ── Route de santé (Laravel built-in) ───────────────────────────
        'GET /up'                                         => [200, 200, 200],

        // ── Espace métier (middleware 'user') ────────────────────────────
        // Un admin est redirigé (302) vers /admin par EnsureIsUser (D14).
        // CPT_001 n'existe pas dans le catalogue de test.
        // POST sur une ressource inexistante → 404 (le contrôleur répond avant CSRF).
        'GET /dashboard'                                  => [302, 200, 302],
        'GET /catalogue'                                  => [302, 200, 302],
        'GET /mes-extractions'                            => [302, 200, 302],
        'GET /mes-extractions/export'                     => [302, 200, 302],
        'GET /extractions/CPT_001'                        => [302, 404, 302],
        'POST /extractions/CPT_001/execute'               => [302, 404, 302],
        'GET /extractions/CPT_001/resultats/__TOKEN__'    => [302, 404, 302],
        'GET /extractions/CPT_001/csv/__TOKEN__'          => [302, 404, 302],

        // ── Espace administration (middleware 'admin') ───────────────────
        // Un user reçoit 403. CPT_001 inexistant → 404 pour l'admin.
        // /admin/extractions (store) sans CSRF → 302 (redirect TokenMismatch).
        // /admin/extractions/verifier → 200 (endpoint AJAX, retourne JSON).
        // PUT/PATCH sur CPT_001 inexistant → 404 avant validation CSRF.
        'GET /admin'                                      => [302, 403, 200],
        'GET /admin/extractions'                          => [302, 403, 200],
        'GET /admin/extractions/create'                   => [302, 403, 200],
        'POST /admin/extractions'                         => [302, 403, 302],
        'POST /admin/extractions/verifier'                => [302, 403, 200],
        'GET /admin/extractions/CPT_001/edit'             => [302, 403, 404],
        'PUT /admin/extractions/CPT_001'                  => [302, 403, 404],
        'PATCH /admin/extractions/CPT_001/statut'         => [302, 403, 404],
        'PATCH /admin/extractions/CPT_001/toggle-statut'  => [302, 403, 404],
        'GET /admin/extractions/CPT_001/test'             => [302, 403, 404],
        'POST /admin/extractions/CPT_001/test'            => [302, 403, 404],
        'GET /admin/extractions/CPT_001/test/resultat/__TOKEN__' => [302, 403, 404],
        'GET /admin/logs'                                 => [302, 403, 200],
        'GET /admin/logs/export'                          => [302, 403, 200],

        // ── Route de développement ───────────────────────────────────────
        // Uniquement en env 'local' → 404 en environnement de test.
        'GET /dev/styleguide'                             => [404, 404, 404],
    ];

    // ------------------------------------------------------------------ //
    //  Helpers                                                             //
    // ------------------------------------------------------------------ //

    private function makeUser(string $role): User
    {
        return User::create([
            'identifiant' => 'test.' . $role,
            'prenom'      => 'Test',
            'nom'         => ucfirst($role),
            'role'        => $role,
            'password'    => 'MotDePasseTest12',
            'actif'       => true,
        ]);
    }

    /**
     * Appelle la route avec l'acteur demandé et retourne le code HTTP.
     * Gère les StreamedResponse (exports CSV) dont la méthode status() est absente.
     */
    private function callRoute(string $method, string $uri, ?User $actor): int
    {
        $test = $actor ? $this->actingAs($actor) : $this;

        $response = match (strtoupper($method)) {
            'POST'  => $test->post($uri, []),
            'PUT'   => $test->put($uri, []),
            'PATCH' => $test->patch($uri, []),
            default => $test->get($uri),
        };

        // Certaines réponses (StreamedResponse) n'ont pas la méthode status() de TestResponse.
        // On accède au status via le baseResponse Symfony dans ce cas.
        try {
            return $response->status();
        } catch (\Error $e) {
            // @phpstan-ignore-next-line
            return $response->baseResponse?->getStatusCode() ?? $response->getStatusCode();
        }
    }

    // ------------------------------------------------------------------ //
    //  Test principal                                                      //
    // ------------------------------------------------------------------ //

    /**
     * Vérifie que chaque route réagit au bon code HTTP selon le rôle.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('matrixProvider')]
    public function test_route_acces_par_role(
        string $method,
        string $uri,
        int $expectedGuest,
        int $expectedUser,
        int $expectedAdmin
    ): void {
        $user  = $this->makeUser('user');
        $admin = $this->makeUser('admin');

        $this->assertSame(
            $expectedGuest,
            $this->callRoute($method, $uri, null),
            "Invité : {$method} {$uri}"
        );

        $this->assertSame(
            $expectedUser,
            $this->callRoute($method, $uri, $user),
            "User : {$method} {$uri}"
        );

        $this->assertSame(
            $expectedAdmin,
            $this->callRoute($method, $uri, $admin),
            "Admin : {$method} {$uri}"
        );
    }

    /** Fournit les lignes de la matrice au test dataProvider. */
    public static function matrixProvider(): array
    {
        $self = new self('matrixProvider');
        $rows = [];

        foreach ($self->matrix as $key => [$guest, $user, $admin]) {
            [$method, $uri] = explode(' ', $key, 2);
            $rows[$key] = [$method, $uri, $guest, $user, $admin];
        }

        return $rows;
    }

    // ------------------------------------------------------------------ //
    //  Garde-fou : toutes les routes de l'application sont dans la        //
    //  matrice (aucune route "fantôme").                                  //
    // ------------------------------------------------------------------ //

    public function test_toutes_les_routes_sont_declarees_dans_la_matrice(): void
    {
        /** @var \Illuminate\Routing\RouteCollection $routes */
        $routes = app('router')->getRoutes();

        // URIs de routes système à ignorer
        $excludedPatterns = [
            'storage/{path}',
        ];

        $declaredUris = array_map(
            fn(string $k) => explode(' ', $k, 2)[1],
            array_keys($this->matrix)
        );

        $undeclared = [];

        foreach ($routes as $route) {
            $routeUri = '/' . ltrim($route->uri(), '/');

            // Ignorer les routes système exclues
            $skip = false;
            foreach ($excludedPatterns as $pattern) {
                if (str_contains($route->uri(), ltrim($pattern, '/'))) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // Normaliser l'URI en remplaçant les segments variables
            $routeUriNorm = preg_replace('/\{[^}]+\}/', '__VAR__', $routeUri);

            $covered = false;
            foreach ($declaredUris as $matrixUri) {
                // Normaliser l'URI de la matrice (valeurs fictives → __VAR__)
                $matrixUriNorm = preg_replace('/\{[^}]+\}/', '__VAR__', $matrixUri);
                $matrixUriNorm = preg_replace('~/(CPT_001|__TOKEN__)~', '/__VAR__', $matrixUriNorm);

                if ($matrixUriNorm === $routeUriNorm) {
                    $covered = true;
                    break;
                }
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                if (! $covered) {
                    $undeclared[] = "{$method} {$routeUri}";
                }
            }
        }

        $undeclared = array_unique($undeclared);

        $this->assertEmpty(
            $undeclared,
            "Des routes ne sont pas déclarées dans la matrice RoutesMatrixTest :\n"
            . implode("\n", $undeclared)
        );
    }
}
