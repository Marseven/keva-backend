<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentification requise',
                'data' => null,
            ], 401);
        }

        $user = auth()->user();

        // Vérifier si l'utilisateur a un abonnement actif
        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Abonnement requis pour accéder à cette fonctionnalité',
                'data' => [
                    'subscription_required' => true,
                    'available_plans' => '/api/plans',
                ],
            ], 402); // 402 Payment Required
        }

        // Vérifier si l'abonnement permet cette action
        $subscription = $user->activeSubscription;
        $plan = $subscription->plan;

        // Exemple : vérifier les limites du plan
        $action = $request->route()->getActionMethod();

        if ($action === 'store' && $request->is('api/products')) {
            // Vérifier la limite de produits
            if (!$user->canCreateProducts()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de produits atteinte pour votre plan',
                    'data' => [
                        'current_count' => $user->products()->count(),
                        'max_allowed' => $plan->max_products,
                        'upgrade_required' => true,
                    ],
                ], 402);
            }
        }

        return $next($request);
    }
}
