<?php
// app/Http/Controllers/Api/PlanController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    use ApiResponseTrait;

    /**
     * @OA\Get(
     *     path="/api/plans",
     *     tags={"Abonnements"},
     *     summary="Lister tous les plans",
     *     description="Récupérer la liste de tous les plans d'abonnement disponibles",
     *     @OA\Response(
     *         response=200,
     *         description="Plans récupérés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Plans récupérés avec succès"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Plan")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $plans = Plan::active()
            ->ordered()
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'final_price' => $plan->final_price,
                    'discounted_price' => $plan->discounted_price,
                    'is_on_sale' => $plan->is_on_sale,
                    'duration_days' => $plan->duration_days,
                    'currency' => $plan->currency,
                    'features' => $plan->features,
                    'max_products' => $plan->max_products,
                    'max_orders' => $plan->max_orders,
                    'max_storage_mb' => $plan->max_storage_mb,
                    'has_analytics' => $plan->has_analytics,
                    'has_priority_support' => $plan->has_priority_support,
                    'has_custom_domain' => $plan->has_custom_domain,
                    'is_popular' => $plan->is_popular,
                    'discount_percentage' => $plan->discount_percentage,
                    'discount_expires_at' => $plan->discount_expires_at,
                ];
            });

        return $this->successResponse($plans, 'Plans récupérés avec succès');
    }

    /**
     * @OA\Get(
     *     path="/api/plans/{slug}",
     *     tags={"Abonnements"},
     *     summary="Détails d'un plan",
     *     description="Récupérer les détails d'un plan spécifique",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Slug du plan (basic, pro, enterprise)"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du plan récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Plan récupéré avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Plan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Plan non trouvé",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $plan = Plan::where('slug', $slug)->active()->first();

        if (!$plan) {
            return $this->notFoundResponse('Plan non trouvé');
        }

        $planData = [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price' => $plan->price,
            'formatted_price' => $plan->formatted_price,
            'final_price' => $plan->final_price,
            'discounted_price' => $plan->discounted_price,
            'is_on_sale' => $plan->is_on_sale,
            'duration_days' => $plan->duration_days,
            'currency' => $plan->currency,
            'features' => $plan->features,
            'max_products' => $plan->max_products,
            'max_orders' => $plan->max_orders,
            'max_storage_mb' => $plan->max_storage_mb,
            'has_analytics' => $plan->has_analytics,
            'has_priority_support' => $plan->has_priority_support,
            'has_custom_domain' => $plan->has_custom_domain,
            'is_popular' => $plan->is_popular,
            'discount_percentage' => $plan->discount_percentage,
            'discount_expires_at' => $plan->discount_expires_at,
            'is_unlimited_products' => $plan->isUnlimitedProducts(),
            'is_unlimited_orders' => $plan->isUnlimitedOrders(),
        ];

        return $this->successResponse($planData, 'Plan récupéré avec succès');
    }

    /**
     * @OA\Get(
     *     path="/api/plans/compare",
     *     tags={"Abonnements"},
     *     summary="Comparer les plans",
     *     description="Obtenir un tableau comparatif des plans pour faciliter le choix",
     *     @OA\Response(
     *         response=200,
     *         description="Comparaison des plans",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Comparaison des plans récupérée"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="plans", type="array", @OA\Items(ref="#/components/schemas/Plan")),
     *                 @OA\Property(property="features_comparison", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function compare(): JsonResponse
    {
        $plans = Plan::active()->ordered()->get();

        // Créer une liste de toutes les fonctionnalités
        $allFeatures = [
            'Nombre de produits',
            'Commandes par mois',
            'Stockage',
            'Support',
            'Analytiques',
            'Thèmes',
            'Intégrations paiement',
            'Domaine personnalisé',
            'Formation',
        ];

        $comparison = [];
        foreach ($plans as $plan) {
            $comparison[$plan->slug] = [
                'plan' => $plan->only(['name', 'price', 'formatted_price', 'is_popular']),
                'features' => [
                    'Nombre de produits' => $plan->max_products == 0 ? 'Illimité' : $plan->max_products,
                    'Commandes par mois' => $plan->max_orders == 0 ? 'Illimité' : $plan->max_orders,
                    'Stockage' => round($plan->max_storage_mb / 1024, 1) . ' GB',
                    'Support' => $plan->has_priority_support ? 'Prioritaire' : 'Email',
                    'Analytiques' => $plan->has_analytics ? 'Oui' : 'Non',
                    'Thèmes' => $plan->slug == 'basic' ? 'De base' : 'Premium',
                    'Intégrations paiement' => $plan->slug == 'basic' ? 'Mobile Money' : 'Toutes',
                    'Domaine personnalisé' => $plan->has_custom_domain ? 'Oui' : 'Non',
                    'Formation' => $plan->slug == 'enterprise' ? 'Dédiée' : 'Documentation',
                ],
            ];
        }

        return $this->successResponse([
            'plans' => $plans,
            'features_comparison' => $comparison,
            'all_features' => $allFeatures,
        ], 'Comparaison des plans récupérée');
    }
}
