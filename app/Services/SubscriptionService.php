<?php
// app/Services/SubscriptionService.php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionService
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Créer un nouvel abonnement
     */
    public function createSubscription(User $user, Plan $plan, array $options = []): Subscription
    {
        try {
            DB::beginTransaction();

            // Annuler l'abonnement actuel s'il existe
            $this->cancelCurrentSubscription($user);

            // Déterminer les dates
            $startsAt = $options['starts_at'] ?? now();
            $trialDays = $options['trial_days'] ?? 0;
            $trialEndsAt = $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : null;
            $endsAt = $startsAt->copy()->addDays($plan->duration_days);

            // Créer l'abonnement
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => $trialDays > 0 ? 'active' : 'pending',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialEndsAt,
                'amount' => $plan->final_price,
                'currency' => $plan->currency,
                'auto_renew' => $options['auto_renew'] ?? true,
                'features_snapshot' => $plan->features,
                'metadata' => [
                    'plan_name' => $plan->name,
                    'plan_price' => $plan->price,
                    'created_from' => $options['source'] ?? 'manual',
                    'created_at' => now()->toISOString(),
                ]
            ]);

            DB::commit();

            Log::info('Subscription created', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan' => $plan->slug,
                'trial_days' => $trialDays
            ]);

            return $subscription;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create subscription', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Activer un abonnement après paiement
     */
    public function activateSubscription(Subscription $subscription): bool
    {
        try {
            $subscription->update([
                'status' => 'active',
                'starts_at' => now(),
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'activated_at' => now()->toISOString(),
                ])
            ]);

            Log::info('Subscription activated', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to activate subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Renouveler un abonnement
     */
    public function renewSubscription(Subscription $subscription, array $options = []): Subscription
    {
        try {
            DB::beginTransaction();

            $plan = $subscription->plan;
            $newAmount = $options['amount'] ?? $plan->final_price;
            $duration = $options['duration_days'] ?? $plan->duration_days;

            // Calculer les nouvelles dates
            $newStartsAt = $subscription->ends_at;
            $newEndsAt = $newStartsAt->copy()->addDays($duration);

            // Mettre à jour l'abonnement existant
            $subscription->update([
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
                'amount' => $newAmount,
                'status' => 'active',
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'renewed_at' => now()->toISOString(),
                    'renewal_count' => ($subscription->metadata['renewal_count'] ?? 0) + 1,
                ])
            ]);

            DB::commit();

            Log::info('Subscription renewed', [
                'subscription_id' => $subscription->id,
                'new_ends_at' => $newEndsAt->toISOString()
            ]);

            return $subscription->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to renew subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Annuler un abonnement
     */
    public function cancelSubscription(Subscription $subscription, array $options = []): bool
    {
        try {
            $immediately = $options['immediately'] ?? false;
            $reason = $options['reason'] ?? 'Cancelled by user';

            $updateData = [
                'cancelled_at' => now(),
                'auto_renew' => false,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancellation_reason' => $reason,
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now()->toISOString(),
                ])
            ];

            if ($immediately) {
                $updateData['status'] = 'cancelled';
                $updateData['ends_at'] = now();
            } else {
                // Annulation à la fin de la période
                $updateData['status'] = 'active'; // Reste actif jusqu'à la fin
            }

            $subscription->update($updateData);

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'immediately' => $immediately,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Changer de plan
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $options = []): Subscription
    {
        try {
            DB::beginTransaction();

            $prorate = $options['prorate'] ?? true;
            $immediately = $options['immediately'] ?? true;

            if ($immediately) {
                // Changement immédiat
                $newEndsAt = now()->addDays($newPlan->duration_days);
                $proratedAmount = $prorate ? $this->calculateProration($subscription, $newPlan) : $newPlan->final_price;

                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'amount' => $proratedAmount,
                    'ends_at' => $newEndsAt,
                    'features_snapshot' => $newPlan->features,
                    'metadata' => array_merge($subscription->metadata ?? [], [
                        'plan_changed_at' => now()->toISOString(),
                        'previous_plan_id' => $subscription->plan_id,
                        'previous_plan_name' => $subscription->plan->name,
                        'new_plan_name' => $newPlan->name,
                        'proration_applied' => $prorate,
                    ])
                ]);
            } else {
                // Changement à la fin de la période actuelle
                $subscription->update([
                    'metadata' => array_merge($subscription->metadata ?? [], [
                        'pending_plan_change' => [
                            'new_plan_id' => $newPlan->id,
                            'new_plan_name' => $newPlan->name,
                            'effective_date' => $subscription->ends_at->toISOString(),
                        ]
                    ])
                ]);
            }

            DB::commit();

            Log::info('Plan changed', [
                'subscription_id' => $subscription->id,
                'old_plan' => $subscription->plan->slug,
                'new_plan' => $newPlan->slug,
                'immediately' => $immediately
            ]);

            return $subscription->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change plan', [
                'subscription_id' => $subscription->id,
                'new_plan_id' => $newPlan->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Suspendre un abonnement
     */
    public function suspendSubscription(Subscription $subscription, string $reason = 'Administrative suspension'): bool
    {
        try {
            $subscription->update([
                'status' => 'suspended',
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'suspended_at' => now()->toISOString(),
                    'suspension_reason' => $reason,
                    'suspended_by' => auth()->id(),
                ])
            ]);

            Log::info('Subscription suspended', [
                'subscription_id' => $subscription->id,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to suspend subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Réactiver un abonnement suspendu
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        try {
            if ($subscription->status !== 'suspended') {
                throw new \Exception('Only suspended subscriptions can be resumed');
            }

            $subscription->update([
                'status' => 'active',
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'resumed_at' => now()->toISOString(),
                    'resumed_by' => auth()->id(),
                ])
            ]);

            Log::info('Subscription resumed', [
                'subscription_id' => $subscription->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to resume subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Traiter les abonnements arrivant à expiration
     */
    public function processExpiringSubscriptions(): array
    {
        $processed = 0;
        $renewed = 0;
        $expired = 0;
        $errors = 0;

        // Abonnements expirant dans les prochaines 24h
        $expiringSubscriptions = Subscription::where('status', 'active')
            ->where('auto_renew', true)
            ->whereBetween('ends_at', [now(), now()->addDay()])
            ->with(['user', 'plan'])
            ->get();

        foreach ($expiringSubscriptions as $subscription) {
            try {
                $processed++;

                if ($this->attemptAutoRenewal($subscription)) {
                    $renewed++;
                } else {
                    $this->expireSubscription($subscription);
                    $expired++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error processing expiring subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Marquer les abonnements expirés
        Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);

        return [
            'processed' => $processed,
            'renewed' => $renewed,
            'expired' => $expired,
            'errors' => $errors
        ];
    }

    /**
     * Obtenir les statistiques d'abonnements
     */
    public function getSubscriptionStats(): array
    {
        return [
            'total_subscriptions' => Subscription::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'trial_subscriptions' => Subscription::where('status', 'active')
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
            'expiring_soon' => Subscription::where('status', 'active')
                ->whereBetween('ends_at', [now(), now()->addDays(7)])
                ->count(),
            'monthly_revenue' => Subscription::where('status', 'active')
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
            'churn_rate' => $this->calculateChurnRate(),
            'by_plan' => Subscription::where('status', 'active')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->groupBy('plans.name')
                ->selectRaw('plans.name, COUNT(*) as count, SUM(subscriptions.amount) as revenue')
                ->get()
                ->keyBy('name')
                ->toArray(),
        ];
    }

    /**
     * Annuler l'abonnement actuel d'un utilisateur
     */
    private function cancelCurrentSubscription(User $user): void
    {
        $currentSubscription = $user->activeSubscription;
        if ($currentSubscription) {
            $this->cancelSubscription($currentSubscription, [
                'immediately' => true,
                'reason' => 'Replaced by new subscription'
            ]);
        }
    }

    /**
     * Calculer la proratisation
     */
    private function calculateProration(Subscription $subscription, Plan $newPlan): float
    {
        $remainingDays = now()->diffInDays($subscription->ends_at, false);
        if ($remainingDays <= 0) {
            return $newPlan->final_price;
        }

        $totalDays = $subscription->starts_at->diffInDays($subscription->ends_at);
        $usedDays = $totalDays - $remainingDays;

        // Crédit pour la période non utilisée
        $currentDailyRate = $subscription->amount / $totalDays;
        $credit = $remainingDays * $currentDailyRate;

        // Coût du nouveau plan au prorata
        $newDailyRate = $newPlan->final_price / $newPlan->duration_days;
        $newPlanCost = $remainingDays * $newDailyRate;

        return max(0, $newPlanCost - $credit);
    }

    /**
     * Tenter le renouvellement automatique
     */
    private function attemptAutoRenewal(Subscription $subscription): bool
    {
        try {
            // Vérifier s'il y a une méthode de paiement valide
            $lastPayment = Payment::where('user_id', $subscription->user_id)
                ->where('status', 'completed')
                ->latest()
                ->first();

            if (!$lastPayment) {
                return false;
            }

            // Créer un paiement pour le renouvellement
            $result = $this->paymentService->initiatePayment(
                null, // Pas de commande pour les abonnements
                $lastPayment->payment_method,
                [
                    'name' => $subscription->user->full_name,
                    'email' => $subscription->user->email,
                    'phone' => $subscription->user->phone,
                ],
                [
                    'amount' => $subscription->plan->final_price,
                    'currency' => $subscription->currency,
                    'description' => "Renouvellement abonnement {$subscription->plan->name}",
                    'subscription_id' => $subscription->id,
                ]
            );

            if ($result['success']) {
                $this->renewSubscription($subscription);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Auto-renewal failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Expirer un abonnement
     */
    private function expireSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'expired',
            'metadata' => array_merge($subscription->metadata ?? [], [
                'expired_at' => now()->toISOString(),
                'auto_renewal_failed' => true,
            ])
        ]);
    }

    /**
     * Calculer le taux de désabonnement
     */
    private function calculateChurnRate(): float
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $activeAtStart = Subscription::where('status', 'active')
            ->where('created_at', '<', $startOfMonth)
            ->count();

        $cancelledThisMonth = Subscription::whereBetween('cancelled_at', [$startOfMonth, $endOfMonth])
            ->count();

        return $activeAtStart > 0 ? ($cancelledThisMonth / $activeAtStart) * 100 : 0;
    }
}
