<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuthLogger $authLogger
    ) {}

    /**
     * Affiche le formulaire de connexion.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return Auth::user()->isAdmin()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Traite la tentative de connexion.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifiant' => ['required', 'string'],
            'password'    => ['required', 'string'],
        ], [
            'identifiant.required' => 'Veuillez saisir votre identifiant.',
            'password.required'    => 'Veuillez saisir votre mot de passe.',
        ]);

        $identifiant = strtolower(trim($credentials['identifiant']));
        $throttleKey = $identifiant . '|' . $request->ip();

        // Vérification du rate limiting (5 échecs max par minute)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->authLogger->logLockout(
                $identifiant,
                $request->ip(),
                $request->userAgent() ?? ''
            );

            return back()
                ->withInput($request->only('identifiant'))
                ->with('error_type', 'lockout')
                ->with('lockout_seconds', $seconds)
                ->withErrors([
                    'identifiant' => "Trop de tentatives de connexion. Veuillez réesayer dans {$seconds} secondes.",
                ]);
        }

        // Tentative d'authentification (compte actif uniquement)
        if (Auth::attempt([
            'identifiant' => $identifiant,
            'password'    => $credentials['password'],
            'actif'       => true,
        ], $request->boolean('remember'))) {

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            /** @var \App\Models\User $user */
            $user = Auth::user();

            $this->authLogger->logSuccess(
                $user->identifiant,
                $request->ip(),
                $request->userAgent() ?? ''
            );

            return $user->isAdmin()
                ? redirect()->intended(route('admin.dashboard'))
                : redirect()->intended(route('dashboard'));
        }

        // Échec d'authentification
        RateLimiter::hit($throttleKey, 60);

        $this->authLogger->logFailed(
            $identifiant,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return back()
            ->withInput($request->only('identifiant'))
            ->withErrors([
                'identifiant' => 'Identifiant ou mot de passe incorrect.',
            ]);
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $this->authLogger->logLogout(
                $user->identifiant,
                $request->ip(),
                $request->userAgent() ?? ''
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
