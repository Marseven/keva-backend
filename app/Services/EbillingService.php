<?php
// app/Services/EbillingService.php

namespace App\Services;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EbillingService
{
    private string $baseUrl;
    private string $username;
    private string $sharedKey;
    private string $redirectUrl;
    private string $callbackUrl;
    private array $paymentMethods;
    private bool $testMode;

    public function __construct()
    {
        $this->baseUrl = config('ebilling.base_url');
        $this->username = config('ebilling.username');
        $this->sharedKey = config('ebilling.shared_key');
        $this->redirectUrl = config('ebilling.redirect_url');
        $this->callbackUrl = config('ebilling.callback_url');
        $this->paymentMethods = config('ebilling.payment_methods');
        $this->testMode = config('ebilling.test_mode');
    }

    /**
     * Créer une facture de paiement EBILLING
     */
    public function createBill(Order $order, string $paymentMethod, array $payerInfo): array
    {
        try {
            $billData = $this->prepareBillData($order, $paymentMethod, $payerInfo);

            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->post($this->baseUrl . config('ebilling.endpoints.create_bill'), $billData);

            if ($response->successful()) {
                $responseData = $response->json();

                // Créer l'enregistrement Payment
                $payment = $this->createPaymentRecord($order, $paymentMethod, $payerInfo, $responseData);

                Log::info('EBILLING: Facture créée avec succès', [
                    'order_id' => $order->id,
                    'bill_id' => $responseData['data']['bill_id'] ?? null
                ]);

                return [
                    'success' => true,
                    'payment' => $payment,
                    'bill_id' => $responseData['data']['bill_id'],
                    'payment_url' => $responseData['data']['payment_url'] ?? null,
                    'qr_code' => $responseData['data']['qr_code'] ?? null,
                ];
            }

            Log::error('EBILLING: Erreur création facture', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Erreur lors de la création de la facture'
            ];
        } catch (\Exception $e) {
            Log::error('EBILLING: Exception création facture', [
                'message' => $e->getMessage(),
                'order_id' => $order->id
            ]);

            return [
                'success' => false,
                'error' => 'Erreur technique lors de la création du paiement'
            ];
        }
    }

    /**
     * Initier un paiement USSD Push (Mobile Money)
     */
    public function initiateUssdPush(string $billId, string $phoneNumber): array
    {
        try {
            $ussdData = [
                'bill_id' => $billId,
                'phone_number' => $this->formatPhoneNumber($phoneNumber),
            ];

            $response = Http::timeout(30)
                ->post(
                    $this->baseUrl . str_replace('{bill_id}', $billId, config('ebilling.endpoints.ussd_push')),
                    $ussdData
                );

            if ($response->successful()) {
                Log::info('EBILLING: USSD Push initié', ['bill_id' => $billId]);

                return [
                    'success' => true,
                    'message' => 'Paiement USSD initié. Veuillez composer *150# pour finaliser.'
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Erreur lors de l\'initiation USSD'
            ];
        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur USSD Push', [
                'message' => $e->getMessage(),
                'bill_id' => $billId
            ]);

            return [
                'success' => false,
                'error' => 'Erreur technique USSD'
            ];
        }
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus(string $billId): array
    {
        try {
            $response = Http::timeout(15)
                ->get($this->baseUrl . "/api/v1/merchant/e_bills/{$billId}/status");

            if ($response->successful()) {
                $data = $response->json()['data'];

                return [
                    'success' => true,
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'paid_at' => $data['paid_at'] ?? null,
                    'transaction_ref' => $data['transaction_ref'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Impossible de vérifier le statut'
            ];
        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur vérification statut', [
                'message' => $e->getMessage(),
                'bill_id' => $billId
            ]);

            return [
                'success' => false,
                'error' => 'Erreur technique lors de la vérification'
            ];
        }
    }

    /**
     * Traiter le callback/webhook d'EBILLING
     */
    public function handleCallback(array $callbackData): bool
    {
        try {
            // Vérifier la signature du webhook
            if (!$this->verifyWebhookSignature($callbackData)) {
                Log::warning('EBILLING: Signature webhook invalide', $callbackData);
                return false;
            }

            $billId = $callbackData['bill_id'];
            $status = $callbackData['status'];
            $transactionRef = $callbackData['transaction_ref'] ?? null;

            // Trouver le paiement correspondant
            $payment = Payment::where('bill_id', $billId)->first();

            if (!$payment) {
                Log::warning('EBILLING: Paiement non trouvé pour callback', ['bill_id' => $billId]);
                return false;
            }

            // Mettre à jour le statut du paiement
            $this->updatePaymentFromCallback($payment, $status, $transactionRef, $callbackData);

            Log::info('EBILLING: Callback traité avec succès', [
                'bill_id' => $billId,
                'status' => $status,
                'payment_id' => $payment->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur traitement callback', [
                'message' => $e->getMessage(),
                'callback_data' => $callbackData
            ]);

            return false;
        }
    }

    /**
     * Préparer les données de la facture pour EBILLING
     */
    private function prepareBillData(Order $order, string $paymentMethod, array $payerInfo): array
    {
        $methodConfig = $this->paymentMethods[$paymentMethod];

        return [
            'merchant_id' => $this->username,
            'bill_id' => $this->generateBillId(),
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'description' => "Commande #{$order->order_number}",
            'payment_method' => $methodConfig['name'],
            'payer_name' => $payerInfo['name'],
            'payer_email' => $payerInfo['email'] ?? '',
            'payer_phone' => $this->formatPhoneNumber($payerInfo['phone']),
            'redirect_url' => $this->redirectUrl,
            'callback_url' => $this->callbackUrl,
            'expiry_period' => config('ebilling.default_expiry_period'),
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->user_id,
            ],
            'signature' => $this->generateSignature($order, $payerInfo),
        ];
    }

    /**
     * Créer l'enregistrement Payment
     */
    private function createPaymentRecord(Order $order, string $paymentMethod, array $payerInfo, array $ebillingResponse): Payment
    {
        return Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'bill_id' => $ebillingResponse['data']['bill_id'],
            'external_reference' => $ebillingResponse['data']['reference'] ?? null,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'payment_method' => $paymentMethod,
            'payment_provider' => 'ebilling',
            'status' => 'pending',
            'payer_name' => $payerInfo['name'],
            'payer_email' => $payerInfo['email'] ?? null,
            'payer_phone' => $payerInfo['phone'],
            'gateway_response' => $ebillingResponse,
            'metadata' => [
                'bill_created_at' => now()->toISOString(),
                'payment_url' => $ebillingResponse['data']['payment_url'] ?? null,
            ],
        ]);
    }

    /**
     * Mettre à jour le paiement depuis le callback
     */
    private function updatePaymentFromCallback(Payment $payment, string $status, ?string $transactionRef, array $callbackData): void
    {
        $updateData = [
            'transaction_id' => $transactionRef,
            'gateway_response' => array_merge($payment->gateway_response ?? [], $callbackData),
        ];

        switch ($status) {
            case 'paid':
            case 'completed':
                $updateData['status'] = 'completed';
                $updateData['paid_at'] = now();
                $payment->order?->markAsPaid();
                break;

            case 'failed':
            case 'cancelled':
                $updateData['status'] = 'failed';
                $updateData['failed_at'] = now();
                $updateData['failure_reason'] = $callbackData['failure_reason'] ?? 'Paiement échoué';
                break;

            case 'expired':
                $updateData['status'] = 'cancelled';
                $updateData['failed_at'] = now();
                $updateData['failure_reason'] = 'Facture expirée';
                break;

            default:
                $updateData['status'] = 'processing';
        }

        $payment->update($updateData);
    }

    /**
     * Générer un ID de facture unique
     */
    private function generateBillId(): string
    {
        return 'KEVA-' . date('Ymd') . '-' . strtoupper(Str::random(8));
    }

    /**
     * Formater le numéro de téléphone pour le Gabon
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Nettoyer le numéro
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Format international pour le Gabon (+241)
        if (strlen($phone) === 8) {
            return '241' . $phone;
        }

        if (strlen($phone) === 11 && str_starts_with($phone, '241')) {
            return $phone;
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '+241')) {
            return substr($phone, 1);
        }

        return $phone; // Retourner tel quel si format non reconnu
    }

    /**
     * Générer la signature pour EBILLING
     */
    private function generateSignature(Order $order, array $payerInfo): string
    {
        $data = [
            $this->username,
            $order->total_amount,
            $order->currency,
            $payerInfo['phone'],
            $this->sharedKey
        ];

        return hash('sha256', implode('|', $data));
    }

    /**
     * Vérifier la signature du webhook
     */
    private function verifyWebhookSignature(array $callbackData): bool
    {
        if (!isset($callbackData['signature'])) {
            return false;
        }

        $expectedSignature = hash(
            'sha256',
            $callbackData['bill_id'] .
                $callbackData['status'] .
                $callbackData['amount'] .
                $this->sharedKey
        );

        return hash_equals($expectedSignature, $callbackData['signature']);
    }

    /**
     * Obtenir les méthodes de paiement disponibles
     */
    public function getAvailablePaymentMethods(): array
    {
        return array_map(function ($method, $key) {
            return [
                'key' => $key,
                'name' => $method['name'],
                'display_name' => $this->getMethodDisplayName($key),
                'supported' => true,
                'test_mode' => $this->testMode,
            ];
        }, $this->paymentMethods, array_keys($this->paymentMethods));
    }

    /**
     * Obtenir le nom d'affichage d'une méthode de paiement
     */
    private function getMethodDisplayName(string $method): string
    {
        return match ($method) {
            'airtel_money' => 'Airtel Money',
            'moov_money' => 'Moov Money',
            'visa_mastercard' => 'Visa/Mastercard',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    /**
     * Valider un numéro de téléphone pour une méthode de paiement
     */
    public function validatePhoneForMethod(string $phone, string $method): bool
    {
        if (!isset($this->paymentMethods[$method])) {
            return false;
        }

        $methodConfig = $this->paymentMethods[$method];

        if (!isset($methodConfig['prefix'])) {
            return true; // Pas de validation spécifique
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        return str_starts_with($cleanPhone, $methodConfig['prefix']) &&
            strlen($cleanPhone) === $methodConfig['length'];
    }
}
