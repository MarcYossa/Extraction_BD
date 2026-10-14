<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve les routes métier au rôle « user ».
 * Un administrateur est renvoyé vers /admin (D14).
 * Un invité est renvoyé vers /login (middleware auth en amont).
 */
class EnsureIsUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // L'administrateur ne doit pas accéder à l'espace métier (D14)
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
