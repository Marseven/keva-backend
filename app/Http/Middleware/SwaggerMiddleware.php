<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // En production, on peut restreindre l'accès à la documentation
        if (app()->environment('production')) {
            // Vérifier si l'utilisateur a les droits admin ou utiliser une IP whitelist
            $allowedIps = config('l5-swagger.allowed_ips', []);

            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
                abort(403, 'Accès non autorisé à la documentation API');
            }
        }

        return $next($request);
    }
}
