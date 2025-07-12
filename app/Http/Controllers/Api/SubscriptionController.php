<?php
// app/Http/Controllers/Api/SubscriptionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\PaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    use ApiResponseTrait;

    private SubscriptionService $subscriptionService;
    private PaymentService $paymentService;

    public function __construct(
        SubscriptionService $subscriptionService,
        PaymentService $paymentService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/subscriptions",
     *     tags={"Abonnements"},
     *     summary="Mes abonnements",
     *     description="Récupérer l'historique des abonnements de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Abonnements récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Abonnements récupérés"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current", ref="#/components/schemas/Subscription"),
     *                 @OA\Property(property="history", type="array", @OA\Items(ref="#/components/schemas/Subscription"))
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $currentSubscription = $user->activeSubscription;
            $historySubscriptions = $user->subscriptions()
                ->with('plan')
                ->where('status', '!=', 'active')
                ->latest()
                ->get();

            $data = [
                'current' => $currentSubscription ? $this->transformSubscription($currentSubscription) : null,
                'history' => $historySubscriptions->map(function ($subscription) {
                    return $this->transformSubscription($subscription);
                }),
                'usage' => $currentSubscription ? $this->getUserUsageStats($user) : null,
            ];

            return $this->successResponse($data, 'Abonnements récupérés');
        } catch (\Exception $e) {
            Log::error('Error fetching subscriptions', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des abonnements', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/subscriptions",
     *     tags={"Abonnements"},
     *     summary="S'abonner à un plan",
     *     description="Créer un nouvel abonnement",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_slug","payment_method","payer_name","payer_phone"},
     *             @OA\Property(property="plan_slug", type="string", example="pro"),
     *             @OA\Property(property="payment_method", type="string", enum={"airtel_money","moov_money","visa_mastercard"}, example="airtel_money"),
     *             @OA\Property(property="payer_name", type="string", example="Jean Mabiala"),
     *             @OA\Property(property="payer_email", type="string", example="jean@example.com"),
     *             @OA\Property(property="payer_phone", type="string", example="077549492"),
     *             @OA\Property(property="auto_renew", type="boolean", example=true),
     *             @OA\Property(property="trial_days", type="integer", example=7)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Abonnement créé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Abonnement créé avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="subscription", ref="#/components/schemas/Subscription"),
     *                 @OA\Property(property="payment", ref="#/components/schemas/Payment"),
     *                 @OA\Property(property="next_action", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function store(SubscriptionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Trouver le plan
            $plan = Plan::where('slug', $data['plan_slug'])->active()->first();
            if (!$plan) {
                return $this->notFoundResponse('Plan non trouvé');
            }

            // Créer l'abonnement
            $trialDays = $data['trial_days'] ?? 0;
            $subscription = $this->subscriptionService->createSubscription($user, $plan, [
                'trial_days' => $trialDays,
                'auto_renew' => $data['auto_renew'] ?? true,
                'source' => 'api'
            ]);

            $response = [
                'subscription' => $this->transformSubscription($subscription),
            ];

            // Si pas de période d'essai, initier le paiement
            if ($trialDays == 0) {
                $payerInfo = [
                    'name' => $data['payer_name'],
                    'email' => $data['payer_email'] ?? $user->email,
                    'phone' => $data['payer_phone'],
                ];

                // Créer une "commande" virtuelle pour l'abonnement
                $virtualOrder = (object) [
                    'id' => null,
                    'order_number' => "SUB-{$subscription->subscription_id}",
                    'user_id' => $user->id,
                    'total_amount' => $plan->final_price,
                    'currency' => $plan->currency,
                ];

                $paymentResult = $this->paymentService->initiatePayment(
                    $virtualOrder,
                    $data['payment_method'],
                    $payerInfo
                );

                if ($paymentResult['success']) {
                    $response['payment'] = $paymentResult['payment'];
                    $response['next_action'] = $paymentResult['next_action'] ?? null;
                    $response['bill_id'] = $paymentResult['bill_id'] ?? null;
                } else {
                    // Annuler l'abonnement si le paiement échoue
                    $this->subscriptionService->cancelSubscription($subscription, [
                        'immediately' => true,
                        'reason' => 'Payment initiation failed'
                    ]);

                    return $this->errorResponse($paymentResult['error'], null, 400);
                }
            } else {
                // Activer immédiatement pour la période d'essai
                $this->subscriptionService->activateSubscription($subscription);
                $response['trial_activated'] = true;
            }

            Log::info('Subscription created', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan' => $plan->slug,
                'trial_days' => $trialDays
            ]);

            return $this->createdResponse($response, 'Abonnement créé avec succès');
        } catch (\Exception $e) {
            Log::error('Error creating subscription', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Erreur lors de la création de l\'abonnement', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/subscriptions/{subscription}",
     *     tags={"Abonnements"},
     *     summary="Détails d'un abonnement",
     *     description="Récupérer les détails d'un abonnement spécifique",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="subscription",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de l'abonnement"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'abonnement",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Abonnement récupéré"),
     *             @OA\Property(property="data", ref="#/components/schemas/Subscription")
     *         )
     *     )
     * )
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        // Vérifier que l'utilisateur a accès à cet abonnement
        if ($subscription->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Accès non autorisé à cet abonnement');
        }

        $subscription->load(['plan', 'user']);

        return $this->successResponse(
            $this->transformSubscriptionDetail($subscription),
            'Abonnement récupéré'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/subscriptions/{subscription}/cancel",
     *     tags={"Abonnements"},
     *     summary="Annuler un abonnement",
     *     description="Annuler un abonnement (prend effet à la fin de la période)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="subscription",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de l'abonnement"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Plus besoin du service"),
     *             @OA\Property(property="immediately", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Abonnement annulé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Abonnement annulé"),
     *             @OA\Property(property="data", ref="#/components/schemas/Subscription")
     *         )
     *     )
     * )
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        // Vérifier l'accès
        if ($subscription->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Accès non autorisé');
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
            'immediately' => 'boolean'
        ]);

        try {
            $success = $this->subscriptionService->cancelSubscription($subscription, [
                'reason' => $request->get('reason', 'Cancelled by user'),
                'immediately' => $request->boolean('immediately', false)
            ]);

            if (!$success) {
                return $this->errorResponse('Erreur lors de l\'annulation', null, 500);
            }

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'user_id' => $request->user()->id,
                'immediately' => $request->boolean('immediately', false)
            ]);

            return $this->successResponse(
                $this->transformSubscription($subscription->fresh()),
                'Abonnement annulé avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de l\'annulation', null, 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/subscriptions/{subscription}/change-plan",
     *     tags={"Abonnements"},
     *     summary="Changer de plan",
     *     description="Modifier le plan d'un abonnement actif",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="subscription",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de l'abonnement"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"new_plan_slug"},
     *             @OA\Property(property="new_plan_slug", type="string", example="enterprise"),
     *             @OA\Property(property="immediately", type="boolean", example=true),
     *             @OA\Property(property="prorate", type="boolean", example=true),
     *             @OA\Property(property="payment_method", type="string", example="airtel_money"),
     *             @OA\Property(property="payer_phone", type="string", example="077549492")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plan modifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Plan modifié avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="subscription", ref="#/components/schemas/Subscription"),
     *                 @OA\Property(property="payment", ref="#/components/schemas/Payment")
     *             )
     *         )
     *     )
     * )
     */
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
    {
        // Vérifier l'accès
        if ($subscription->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Accès non autorisé');
        }

        $request->validate([
            'new_plan_slug' => 'required|exists:plans,slug',
            'immediately' => 'boolean',
            'prorate' => 'boolean',
            'payment_method' => 'nullable|string',
            'payer_phone' => 'nullable|string'
        ]);

        try {
            $newPlan = Plan::where('slug', $request->new_plan_slug)->active()->first();
            if (!$newPlan) {
                return $this->notFoundResponse('Nouveau plan non trouvé');
            }

            if ($newPlan->id === $subscription->plan_id) {
                return $this->errorResponse('Vous êtes déjà sur ce plan', null, 400);
            }

            $immediately = $request->boolean('immediately', true);
            $prorate = $request->boolean('prorate', true);

            // Changer le plan
            $updatedSubscription = $this->subscriptionService->changePlan($subscription, $newPlan, [
                'immediately' => $immediately,
                'prorate' => $prorate
            ]);

            $response = [
                'subscription' => $this->transformSubscription($updatedSubscription),
                'plan_change_details' => [
                    'old_plan' => $subscription->plan->name,
                    'new_plan' => $newPlan->name,
                    'price_difference' => $newPlan->final_price - $subscription->plan->final_price,
                    'effective_immediately' => $immediately,
                    'prorated' => $prorate
                ]
            ];

            // Si changement immédiat et upgrade payant, initier le paiement
            if ($immediately && $newPlan->final_price > $subscription->plan->final_price && $request->payment_method) {
                $proratedAmount = $prorate ?
                    $this->calculateProrationAmount($subscription, $newPlan) :
                    $newPlan->final_price;

                if ($proratedAmount > 0) {
                    $payerInfo = [
                        'name' => $subscription->user->full_name,
                        'email' => $subscription->user->email,
                        'phone' => $request->payer_phone ?: $subscription->user->phone,
                    ];

                    $virtualOrder = (object) [
                        'id' => null,
                        'order_number' => "UPG-{$subscription->subscription_id}",
                        'user_id' => $subscription->user_id,
                        'total_amount' => $proratedAmount,
                        'currency' => $newPlan->currency,
                    ];

                    $paymentResult = $this->paymentService->initiatePayment(
                        $virtualOrder,
                        $request->payment_method,
                        $payerInfo
                    );

                    if ($paymentResult['success']) {
                        $response['payment'] = $paymentResult['payment'];
                        $response['next_action'] = $paymentResult['next_action'] ?? null;
                    }
                }
            }

            Log::info('Plan changed', [
                'subscription_id' => $subscription->id,
                'old_plan' => $subscription->plan->slug,
                'new_plan' => $newPlan->slug,
                'immediately' => $immediately
            ]);

            return $this->successResponse($response, 'Plan modifié avec succès');
        } catch (\Exception $e) {
            Log::error('Error changing plan', [
                'subscription_id' => $subscription->id,
                'new_plan' => $request->new_plan_slug,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors du changement de plan', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/subscriptions/{subscription}/reactivate",
     *     tags={"Abonnements"},
     *     summary="Réactiver un abonnement",
     *     description="Réactiver un abonnement annulé ou expiré",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="subscription",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de l'abonnement"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method","payer_phone"},
     *             @OA\Property(property="payment_method", type="string", example="airtel_money"),
     *             @OA\Property(property="payer_phone", type="string", example="077549492"),
     *             @OA\Property(property="duration_days", type="integer", example=30)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Abonnement réactivé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Abonnement réactivé"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="subscription", ref="#/components/schemas/Subscription"),
     *                 @OA\Property(property="payment", ref="#/components/schemas/Payment")
     *             )
     *         )
     *     )
     * )
     */
    public function reactivate(Request $request, Subscription $subscription): JsonResponse
    {
        // Vérifier l'accès
        if ($subscription->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Accès non autorisé');
        }

        $request->validate([
            'payment_method' => 'required|string',
            'payer_phone' => 'required|string',
            'duration_days' => 'nullable|integer|min:1|max:365'
        ]);

        try {
            if ($subscription->status === 'active') {
                return $this->errorResponse('L\'abonnement est déjà actif', null, 400);
            }

            $plan = $subscription->plan;
            $durationDays = $request->get('duration_days', $plan->duration_days);

            // Initier le paiement pour la réactivation
            $payerInfo = [
                'name' => $subscription->user->full_name,
                'email' => $subscription->user->email,
                'phone' => $request->payer_phone,
            ];

            $virtualOrder = (object) [
                'id' => null,
                'order_number' => "REACT-{$subscription->subscription_id}",
                'user_id' => $subscription->user_id,
                'total_amount' => $plan->final_price,
                'currency' => $plan->currency,
            ];

            $paymentResult = $this->paymentService->initiatePayment(
                $virtualOrder,
                $request->payment_method,
                $payerInfo
            );

            if (!$paymentResult['success']) {
                return $this->errorResponse($paymentResult['error'], null, 400);
            }

            // Réactiver l'abonnement
            $subscription->update([
                'status' => 'pending', // En attente du paiement
                'starts_at' => now(),
                'ends_at' => now()->addDays($durationDays),
                'cancelled_at' => null,
                'auto_renew' => true,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'reactivated_at' => now()->toISOString(),
                    'reactivated_by' => $request->user()->id,
                ])
            ]);

            $response = [
                'subscription' => $this->transformSubscription($subscription->fresh()),
                'payment' => $paymentResult['payment'],
                'next_action' => $paymentResult['next_action'] ?? null,
            ];

            Log::info('Subscription reactivation initiated', [
                'subscription_id' => $subscription->id,
                'user_id' => $request->user()->id
            ]);

            return $this->successResponse($response, 'Abonnement en cours de réactivation');
        } catch (\Exception $e) {
            Log::error('Error reactivating subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la réactivation', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/subscriptions/stats",
     *     tags={"Abonnements"},
     *     summary="Statistiques d'utilisation",
     *     description="Obtenir les statistiques d'utilisation du plan actuel",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques récupérées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistiques récupérées"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_plan", type="object"),
     *                 @OA\Property(property="usage", type="object"),
     *                 @OA\Property(property="limits", type="object"),
     *                 @OA\Property(property="recommendations", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $subscription = $user->activeSubscription;

            if (!$subscription) {
                return $this->errorResponse('Aucun abonnement actif', null, 404);
            }

            $plan = $subscription->plan;
            $usage = $this->getUserUsageStats($user);

            $stats = [
                'current_plan' => [
                    'name' => $plan->name,
                    'price' => $plan->formatted_price,
                    'ends_at' => $subscription->ends_at,
                    'days_remaining' => $subscription->days_remaining,
                    'auto_renew' => $subscription->auto_renew,
                    'status' => $subscription->status,
                ],
                'usage' => $usage,
                'limits' => [
                    'products' => [
                        'used' => $usage['products_count'],
                        'limit' => $plan->max_products,
                        'unlimited' => $plan->max_products === 0,
                        'percentage' => $plan->max_products > 0 ? ($usage['products_count'] / $plan->max_products) * 100 : 0,
                    ],
                    'orders' => [
                        'used' => $usage['orders_this_month'],
                        'limit' => $plan->max_orders,
                        'unlimited' => $plan->max_orders === 0,
                        'percentage' => $plan->max_orders > 0 ? ($usage['orders_this_month'] / $plan->max_orders) * 100 : 0,
                    ],
                    'storage' => [
                        'used_mb' => $usage['storage_used_mb'],
                        'limit_mb' => $plan->max_storage_mb,
                        'percentage' => ($usage['storage_used_mb'] / $plan->max_storage_mb) * 100,
                    ],
                ],
                'features' => [
                    'analytics' => $plan->has_analytics,
                    'priority_support' => $plan->has_priority_support,
                    'custom_domain' => $plan->has_custom_domain,
                ],
                'recommendations' => $this->getUpgradeRecommendations($user, $plan, $usage),
            ];

            return $this->successResponse($stats, 'Statistiques récupérées');
        } catch (\Exception $e) {
            Log::error('Error fetching subscription stats', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des statistiques', null, 500);
        }
    }

    /**
     * Transformer un abonnement pour l'API
     */
    private function transformSubscription(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'subscription_id' => $subscription->subscription_id,
            'status' => $subscription->status,
            'status_badge' => $subscription->status_badge,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'cancelled_at' => $subscription->cancelled_at,
            'amount' => $subscription->amount,
            'formatted_amount' => $subscription->formatted_amount,
            'currency' => $subscription->currency,
            'auto_renew' => $subscription->auto_renew,
            'days_remaining' => $subscription->days_remaining,
            'is_active' => $subscription->is_active,
            'is_expired' => $subscription->is_expired,
            'is_expiring_soon' => $subscription->is_expiring_soon,
            'is_in_trial' => $subscription->is_in_trial,
            'trial_days_remaining' => $subscription->trial_days_remaining,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'price' => $subscription->plan->formatted_price,
                'features' => $subscription->plan->features,
            ],
            'created_at' => $subscription->created_at,
            'updated_at' => $subscription->updated_at,
        ];
    }

    /**
     * Transformer un abonnement avec détails complets
     */
    private function transformSubscriptionDetail(Subscription $subscription): array
    {
        $data = $this->transformSubscription($subscription);

        $data['features_snapshot'] = $subscription->features_snapshot;
        $data['metadata'] = $subscription->metadata;

        return $data;
    }

    /**
     * Obtenir les statistiques d'utilisation d'un utilisateur
     */
    private function getUserUsageStats(User $user): array
    {
        return [
            'products_count' => $user->products()->count(),
            'active_products' => $user->products()->where('status', 'active')->count(),
            'orders_this_month' => $user->orders()->whereMonth('created_at', now()->month)->count(),
            'total_orders' => $user->orders()->count(),
            'storage_used_mb' => $this->calculateStorageUsage($user),
            'last_login' => $user->last_login_at,
        ];
    }

    /**
     * Calculer l'utilisation du stockage
     */
    private function calculateStorageUsage(User $user): float
    {
        // Calcul simple basé sur le nombre d'images
        $imageCount = $user->products()->withCount('images')->get()->sum('images_count');
        return $imageCount * 0.5; // Estimation 0.5MB par image
    }

    /**
     * Obtenir des recommandations d'upgrade
     */
    private function getUpgradeRecommendations(User $user, Plan $currentPlan, array $usage): array
    {
        $recommendations = [];

        // Vérifier les limites atteintes
        if ($currentPlan->max_products > 0 && $usage['products_count'] >= $currentPlan->max_products * 0.8) {
            $recommendations[] = "Vous approchez de la limite de produits. Considérez un upgrade.";
        }

        if ($currentPlan->max_orders > 0 && $usage['orders_this_month'] >= $currentPlan->max_orders * 0.8) {
            $recommendations[] = "Vous approchez de la limite de commandes mensuelles.";
        }

        if (($usage['storage_used_mb'] / $currentPlan->max_storage_mb) > 0.8) {
            $recommendations[] = "Votre espace de stockage est presque plein.";
        }

        // Recommandations basées sur l'usage
        if ($usage['orders_this_month'] > 50 && !$currentPlan->has_analytics) {
            $recommendations[] = "Avec votre volume de commandes, les analytics seraient utiles.";
        }

        return $recommendations;
    }

    /**
     * Calculer le montant de proratisation
     */
    private function calculateProrationAmount(Subscription $subscription, Plan $newPlan): float
    {
        $remainingDays = now()->diffInDays($subscription->ends_at, false);
        if ($remainingDays <= 0) {
            return $newPlan->final_price;
        }

        $totalDays = $subscription->starts_at->diffInDays($subscription->ends_at);

        // Crédit pour la période non utilisée
        $currentDailyRate = $subscription->amount / $totalDays;
        $credit = $remainingDays * $currentDailyRate;

        // Coût du nouveau plan au prorata
        $newDailyRate = $newPlan->final_price / $newPlan->duration_days;
        $newPlanCost = $remainingDays * $newDailyRate;

        return max(0, $newPlanCost - $credit);
    }
}
