<?php
// app/Services/PaymentService.php

namespace App\Services;

use App\Models\Payment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private EbillingService $ebillingService;

    public function __construct(EbillingService $ebillingService)
    {
        $this->ebillingService = $ebillingService;
    }

    /**
     * Initier un paiement pour une commande
     */
    public function initiatePayment(Order $order, string $paymentMethod, array $payerInfo): array
    {
        try {
            // Valider les données
            $validation = $this->validatePaymentData($order, $paymentMethod, $payerInfo);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error']
                ];
            }

            // Vérifier que la commande peut être payée
            if (!$this->canOrderBePaid($order)) {
                return [
                    'success' => false,
                    'error' => 'Cette commande ne peut pas être payée'
                ];
            }

            DB::beginTransaction();

            try {
                // Créer le paiement selon la méthode
                $result = match ($paymentMethod) {
                    'airtel_money', 'moov_money', 'visa_mastercard' => $this->processEbillingPayment($order, $paymentMethod, $payerInfo),
                    'bank_transfer' => $this->processBankTransferPayment($order, $payerInfo),
                    'cash' => $this->processCashPayment($order, $payerInfo),
                    default => [
                        'success' => false,
                        'error' => 'Méthode de paiement non supportée'
                    ]
                };

                if ($result['success']) {
                    DB::commit();

                    // Log du succès
                    Log::info('Payment initiated successfully', [
                        'order_id' => $order->id,
                        'payment_method' => $paymentMethod,
                        'payment_id' => $result['payment']->id ?? null
                    ]);

                    return $result;
                } else {
                    DB::rollBack();
                    return $result;
                }
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur technique lors de l\'initiation du paiement'
            ];
        }
    }

    /**
     * Traiter un paiement EBILLING (Mobile Money, Cartes)
     */
    private function processEbillingPayment(Order $order, string $paymentMethod, array $payerInfo): array
    {
        $result = $this->ebillingService->createBill($order, $paymentMethod, $payerInfo);

        if (!$result['success']) {
            return $result;
        }

        $response = [
            'success' => true,
            'payment' => $result['payment'],
            'bill_id' => $result['bill_id'],
            'next_action' => $this->getNextActionForMethod($paymentMethod, $result)
        ];

        // Pour Mobile Money, initier automatiquement l'USSD Push
        if (in_array($paymentMethod, ['airtel_money', 'moov_money'])) {
            $ussdResult = $this->ebillingService->initiateUssdPush(
                $result['bill_id'],
                $payerInfo['phone']
            );

            $response['ussd_initiated'] = $ussdResult['success'];
            $response['ussd_message'] = $ussdResult['message'] ?? null;
        }

        return $response;
    }

    /**
     * Traiter un paiement par virement bancaire
     */
    private function processBankTransferPayment(Order $order, array $payerInfo): array
    {
        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'payment_method' => 'bank_transfer',
            'payment_provider' => 'manual',
            'status' => 'pending',
            'payer_name' => $payerInfo['name'],
            'payer_email' => $payerInfo['email'] ?? null,
            'payer_phone' => $payerInfo['phone'],
            'metadata' => [
                'bank_details' => $this->getBankDetails(),
                'instructions' => 'Veuillez effectuer le virement et envoyer le justificatif'
            ],
        ]);

        return [
            'success' => true,
            'payment' => $payment,
            'next_action' => [
                'type' => 'bank_transfer_instructions',
                'bank_details' => $this->getBankDetails(),
                'reference' => $payment->payment_id,
                'instructions' => 'Effectuez le virement avec la référence: ' . $payment->payment_id
            ]
        ];
    }

    /**
     * Traiter un paiement en espèces
     */
    private function processCashPayment(Order $order, array $payerInfo): array
    {
        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'payment_method' => 'cash',
            'payment_provider' => 'manual',
            'status' => 'pending',
            'payer_name' => $payerInfo['name'],
            'payer_email' => $payerInfo['email'] ?? null,
            'payer_phone' => $payerInfo['phone'],
            'metadata' => [
                'payment_instructions' => 'Paiement en espèces à la livraison'
            ],
        ]);

        return [
            'success' => true,
            'payment' => $payment,
            'next_action' => [
                'type' => 'cash_on_delivery',
                'message' => 'Paiement en espèces à la livraison',
                'amount' => $payment->formatted_amount
            ]
        ];
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus(Payment $payment): array
    {
        try {
            if ($payment->payment_provider === 'ebilling' && $payment->bill_id) {
                $result = $this->ebillingService->checkPaymentStatus($payment->bill_id);

                if ($result['success']) {
                    // Mettre à jour le paiement si nécessaire
                    $this->updatePaymentStatus($payment, $result);
                }

                return $result;
            }

            // Pour les autres méthodes, retourner le statut actuel
            return [
                'success' => true,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
            ];
        } catch (\Exception $e) {
            Log::error('Error checking payment status', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la vérification du statut'
            ];
        }
    }

    /**
     * Confirmer un paiement manuellement (admin)
     */
    public function confirmPayment(Payment $payment, array $confirmationData = []): bool
    {
        try {
            DB::beginTransaction();

            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'transaction_id' => $confirmationData['transaction_id'] ?? null,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'manual_confirmation' => true,
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now()->toISOString(),
                    'confirmation_notes' => $confirmationData['notes'] ?? null,
                ])
            ]);

            // Marquer la commande comme payée
            if ($payment->order) {
                $payment->order->markAsPaid();
            }

            DB::commit();

            Log::info('Payment confirmed manually', [
                'payment_id' => $payment->id,
                'confirmed_by' => auth()->id()
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error confirming payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Annuler un paiement
     */
    public function cancelPayment(Payment $payment, string $reason = null): bool
    {
        try {
            if (!in_array($payment->status, ['pending', 'processing'])) {
                return false;
            }

            $payment->update([
                'status' => 'cancelled',
                'failed_at' => now(),
                'failure_reason' => $reason ?? 'Annulé par l\'utilisateur',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now()->toISOString(),
                ])
            ]);

            Log::info('Payment cancelled', [
                'payment_id' => $payment->id,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error cancelling payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Obtenir les statistiques de paiements pour un utilisateur
     */
    public function getUserPaymentStats(User $user): array
    {
        $payments = $user->payments();

        return [
            'total_payments' => $payments->count(),
            'successful_payments' => $payments->where('status', 'completed')->count(),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'total_amount_paid' => $payments->where('status', 'completed')->sum('amount'),
            'average_payment_amount' => $payments->where('status', 'completed')->avg('amount') ?? 0,
            'most_used_method' => $this->getMostUsedPaymentMethod($user),
            'last_payment_date' => $payments->latest()->first()?->created_at,
        ];
    }

    /**
     * Valider les données de paiement
     */
    private function validatePaymentData(Order $order, string $paymentMethod, array $payerInfo): array
    {
        // Vérifier que la méthode de paiement est supportée
        $supportedMethods = ['airtel_money', 'moov_money', 'visa_mastercard', 'bank_transfer', 'cash'];
        if (!in_array($paymentMethod, $supportedMethods)) {
            return ['valid' => false, 'error' => 'Méthode de paiement non supportée'];
        }

        // Vérifier les informations du payeur
        if (empty($payerInfo['name']) || empty($payerInfo['phone'])) {
            return ['valid' => false, 'error' => 'Informations du payeur incomplètes'];
        }

        // Validation spécifique pour Mobile Money
        if (in_array($paymentMethod, ['airtel_money', 'moov_money'])) {
            if (!$this->ebillingService->validatePhoneForMethod($payerInfo['phone'], $paymentMethod)) {
                return ['valid' => false, 'error' => 'Numéro de téléphone invalide pour cette méthode'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Vérifier si une commande peut être payée
     */
    private function canOrderBePaid(Order $order): bool
    {
        return in_array($order->status, ['pending', 'confirmed']) &&
            $order->payment_status !== 'paid';
    }

    /**
     * Obtenir la prochaine action selon la méthode de paiement
     */
    private function getNextActionForMethod(string $method, array $billingResult): array
    {
        return match ($method) {
            'airtel_money', 'moov_money' => [
                'type' => 'ussd_push',
                'message' => 'Composez *150# pour finaliser le paiement',
                'bill_id' => $billingResult['bill_id']
            ],
            'visa_mastercard' => [
                'type' => 'redirect',
                'payment_url' => $billingResult['payment_url'],
                'message' => 'Redirection vers la page de paiement sécurisée'
            ],
            default => [
                'type' => 'wait',
                'message' => 'En attente de confirmation du paiement'
            ]
        };
    }

    /**
     * Mettre à jour le statut d'un paiement
     */
    private function updatePaymentStatus(Payment $payment, array $statusData): void
    {
        if ($statusData['status'] !== $payment->status) {
            $payment->update([
                'status' => $statusData['status'],
                'transaction_id' => $statusData['transaction_ref'] ?? $payment->transaction_id,
                'paid_at' => $statusData['status'] === 'completed' ? now() : $payment->paid_at,
            ]);

            if ($statusData['status'] === 'completed' && $payment->order) {
                $payment->order->markAsPaid();
            }
        }
    }

    /**
     * Obtenir les détails bancaires pour virement
     */
    private function getBankDetails(): array
    {
        return [
            'bank_name' => 'Banque de l\'Habitat du Gabon',
            'account_name' => 'KEVA SARL',
            'account_number' => '40001-12345-67890',
            'swift_code' => 'BHABGALX',
            'iban' => 'GA2140001000012345678901',
            'instructions' => 'Merci d\'indiquer votre numéro de commande en référence'
        ];
    }

    /**
     * Obtenir la méthode de paiement la plus utilisée par un utilisateur
     */
    private function getMostUsedPaymentMethod(User $user): ?string
    {
        return $user->payments()
            ->selectRaw('payment_method, COUNT(*) as count')
            ->groupBy('payment_method')
            ->orderByDesc('count')
            ->first()?->payment_method;
    }

    /**
     * Obtenir les méthodes de paiement disponibles
     */
    public function getAvailablePaymentMethods(): array
    {
        $ebillingMethods = $this->ebillingService->getAvailablePaymentMethods();

        $manualMethods = [
            [
                'key' => 'bank_transfer',
                'name' => 'bank_transfer',
                'display_name' => 'Virement Bancaire',
                'supported' => true,
                'test_mode' => false,
            ],
            [
                'key' => 'cash',
                'name' => 'cash',
                'display_name' => 'Espèces à la livraison',
                'supported' => true,
                'test_mode' => false,
            ]
        ];

        return array_merge($ebillingMethods, $manualMethods);
    }

    /**
     * Traiter les paiements en attente (commande cron)
     */
    public function processPendingPayments(): array
    {
        $processed = 0;
        $errors = 0;

        // Récupérer les paiements en attente depuis plus de 5 minutes
        $pendingPayments = Payment::where('status', 'pending')
            ->where('payment_provider', 'ebilling')
            ->whereNotNull('bill_id')
            ->where('created_at', '<', now()->subMinutes(5))
            ->where('created_at', '>', now()->subHours(24)) // Pas plus de 24h
            ->get();

        foreach ($pendingPayments as $payment) {
            try {
                $result = $this->checkPaymentStatus($payment);

                if ($result['success']) {
                    $processed++;
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error processing pending payment', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
            'total_checked' => $pendingPayments->count()
        ];
    }

    /**
     * Générer un rapport de paiements
     */
    public function generatePaymentReport(array $filters = []): array
    {
        $query = Payment::query();

        // Appliquer les filtres
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $payments = $query->get();

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'successful_payments' => $payments->where('status', 'completed')->count(),
            'successful_amount' => $payments->where('status', 'completed')->sum('amount'),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'by_method' => $payments->groupBy('payment_method')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                    'success_rate' => $group->where('status', 'completed')->count() / $group->count() * 100
                ];
            }),
            'by_status' => $payments->groupBy('status')->map->count(),
            'average_amount' => $payments->avg('amount'),
            'success_rate' => $payments->count() > 0 ?
                $payments->where('status', 'completed')->count() / $payments->count() * 100 : 0,
        ];
    }
}
