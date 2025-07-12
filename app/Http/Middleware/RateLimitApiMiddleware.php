<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApiMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $key = 'api'): Response
    {
        $identifier = $this->getIdentifier($request);
        $maxAttempts = $this->getMaxAttempts($key);
        $decayMinutes = $this->getDecayMinutes($key);

        if (RateLimiter::tooManyAttempts($identifier, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($identifier);

            return response()->json([
                'success' => false,
                'message' => 'Trop de requêtes. Veuillez réessayer dans ' . $seconds . ' secondes.',
                'data' => [
                    'retry_after' => $seconds,
                ],
            ], 429);
        }

        RateLimiter::hit($identifier, $decayMinutes * 60);

        $response = $next($request);

        // Ajouter les headers de rate limiting
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($identifier, $maxAttempts),
        ]);

        return $response;
    }

    /**
     * Get the rate limiter identifier
     */
    private function getIdentifier(Request $request): string
    {
        if (auth()->check()) {
            return 'api_user_' . auth()->id();
        }

        return 'api_ip_' . $request->ip();
    }

    /**
     * Get max attempts based on key
     */
    private function getMaxAttempts(string $key): int
    {
        return match ($key) {
            'auth' => 5,      // Authentification
            'payment' => 3,   // Paiements
            'upload' => 10,   // Upload de fichiers
            default => 60,    // API générale
        };
    }

    /**
     * Get decay minutes based on key
     */
    private function getDecayMinutes(string $key): int
    {
        return match ($key) {
            'auth' => 15,     // 15 minutes
            'payment' => 30,  // 30 minutes
            'upload' => 5,    // 5 minutes
            default => 1,     // 1 minute
        };
    }
}
