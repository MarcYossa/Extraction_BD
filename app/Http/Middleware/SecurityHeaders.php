<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware d'en-têtes de sécurité HTTP (Lot 12 – Tâche 5).
 *
 * En-têtes appliqués à chaque réponse :
 *   - X-Content-Type-Options : nosniff
 *   - X-Frame-Options : DENY
 *   - Referrer-Policy : same-origin
 *   - Permissions-Policy : restrictive (caméra, micro, géolocalisation désactivés)
 *   - Content-Security-Policy :
 *       • default-src 'self'
 *       • script-src 'self' 'unsafe-inline' 'unsafe-eval'
 *         ('unsafe-eval' requis par Alpine.js CDN standard ; voir note ci-dessous)
 *       • style-src 'self' 'unsafe-inline'
 *       • img-src 'self' data:
 *       • font-src 'self'
 *       • connect-src 'self'
 *       • frame-ancestors 'none'
 *       • object-src 'none'
 *       • base-uri 'self'
 *       • form-action 'self'
 *
 * NOTE – Alpine.js et CSP :
 *   Alpine.js (version CDN standard) utilise new Function() → 'unsafe-eval' obligatoire.
 *   Le build @alpinejs/csp supprime cette contrainte mais ajoute un coût de maintenance
 *   (build asset pipeline, CDN supprimé). Coût évalué trop élevé pour cette phase :
 *   'unsafe-eval' est donc conservé et documenté dans docs/securite/checklist-securite.md.
 *   Si le projet passe en production exposée, remplacer le CDN Alpine par @alpinejs/csp.
 */
class SecurityHeaders
{
    /**
     * Construit la valeur de la Content-Security-Policy.
     * Factorisée ici pour être testable unitairement.
     */
    public static function cspValue(): string
    {
        return implode('; ', [
            "default-src 'self'",
            // 'unsafe-eval' nécessaire pour Alpine.js CDN (new Function) – voir note.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        );
        $response->headers->set('Content-Security-Policy', self::cspValue());

        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
