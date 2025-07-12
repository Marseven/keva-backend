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

    /**
     * Envoyer un PUSH USSD pour Mobile Money
     */
    public function sendUssdPush(string $billId, string $phoneNumber, string $paymentSystem): array
    {
        try {
            $ussdData = [
                'bill_id' => $billId,
                'phone_number' => $this->formatPhoneNumber($phoneNumber),
                'payment_system' => $paymentSystem
            ];

            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->post(
                    $this->baseUrl . str_replace('{bill_id}', $billId, config('ebilling.endpoints.ussd_push')),
                    $ussdData
                );

            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('EBILLING: PUSH USSD envoyé', [
                    'bill_id' => $billId,
                    'phone_number' => $phoneNumber,
                    'payment_system' => $paymentSystem
                ]);

                return [
                    'success' => true,
                    'message' => $responseData['message'] ?? 'PUSH USSD envoyé avec succès',
                    'data' => $responseData
                ];
            }

            Log::warning('EBILLING: Échec PUSH USSD', [
                'bill_id' => $billId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Erreur lors de l\'envoi du PUSH USSD'
            ];
        } catch (\Exception $e) {
            Log::error('EBILLING: Exception PUSH USSD', [
                'bill_id' => $billId,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur technique lors de l\'envoi du PUSH USSD'
            ];
        }
    }

    /**
     * Récupérer le statut d'une facture EBILLING
     */
    public function getBillStatus(string $billId): array
    {
        try {
            $response = Http::timeout(15)
                ->retry(2, 500)
                ->get($this->baseUrl . "/api/v1/merchant/e_bills/{$billId}/status");

            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('EBILLING: Statut récupéré', [
                    'bill_id' => $billId,
                    'status' => $responseData['data']['status'] ?? 'unknown'
                ]);

                return [
                    'success' => true,
                    'e_bill' => $responseData['data'] ?? [],
                    'status' => $responseData['data']['status'] ?? 'unknown',
                    'amount' => $responseData['data']['amount'] ?? 0,
                    'paid_at' => $responseData['data']['paid_at'] ?? null,
                    'transaction_ref' => $responseData['data']['transaction_ref'] ?? null
                ];
            }

            Log::warning('EBILLING: Erreur récupération statut', [
                'bill_id' => $billId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Impossible de récupérer le statut de la facture'
            ];
        } catch (\Exception $e) {
            Log::error('EBILLING: Exception récupération statut', [
                'bill_id' => $billId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erreur technique lors de la vérification du statut'
            ];
        }
    }

    /**
     * Tester la connexion avec l'API EBILLING
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)
                ->get($this->baseUrl . '/api/v1/merchant/ping', [
                    'merchant_id' => $this->username
                ]);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                return [
                    'connection_success' => true,
                    'status_code' => $response->status(),
                    'response_time' => $responseTime,
                    'message' => 'Connexion EBILLING réussie',
                    'api_version' => $response->json()['version'] ?? 'unknown',
                    'test_mode' => $this->testMode
                ];
            }

            return [
                'connection_success' => false,
                'status_code' => $response->status(),
                'response_time' => $responseTime,
                'message' => 'Échec de connexion à EBILLING',
                'error' => $response->body()
            ];
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'connection_success' => false,
                'status_code' => 0,
                'response_time' => $responseTime,
                'message' => 'Erreur de connexion à EBILLING',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Valider les données d'un callback webhook
     */
    public function validateCallback(array $callbackData): bool
    {
        // Vérifier la présence des champs obligatoires
        $requiredFields = ['bill_id', 'status', 'amount'];

        foreach ($requiredFields as $field) {
            if (!isset($callbackData[$field]) || empty($callbackData[$field])) {
                Log::warning('EBILLING: Champ obligatoire manquant dans callback', [
                    'missing_field' => $field,
                    'callback_data' => $callbackData
                ]);
                return false;
            }
        }

        // Vérifier la signature si présente
        if (isset($callbackData['signature'])) {
            return $this->verifyWebhookSignature($callbackData);
        }

        // Valider le format du bill_id
        if (!preg_match('/^[A-Z0-9\-]{10,50}$/', $callbackData['bill_id'])) {
            Log::warning('EBILLING: Format bill_id invalide', [
                'bill_id' => $callbackData['bill_id']
            ]);
            return false;
        }

        // Valider le statut
        $validStatuses = ['pending', 'paid', 'failed', 'cancelled', 'expired'];
        if (!in_array($callbackData['status'], $validStatuses)) {
            Log::warning('EBILLING: Statut invalide dans callback', [
                'status' => $callbackData['status']
            ]);
            return false;
        }

        // Valider le montant
        if (!is_numeric($callbackData['amount']) || $callbackData['amount'] <= 0) {
            Log::warning('EBILLING: Montant invalide dans callback', [
                'amount' => $callbackData['amount']
            ]);
            return false;
        }

        return true;
    }

    /**
     * Traiter et normaliser les données d'un callback
     */
    public function processCallbackData(array $callbackData): array
    {
        // Normaliser les données
        $processedData = [
            'bill_id' => trim($callbackData['bill_id']),
            'status' => strtolower(trim($callbackData['status'])),
            'amount' => (float) $callbackData['amount'],
            'currency' => $callbackData['currency'] ?? 'XAF',
            'transaction_id' => $callbackData['transaction_id'] ?? $callbackData['transactionid'] ?? null,
            'payer_name' => $callbackData['payer_name'] ?? $callbackData['customername'] ?? null,
            'payer_email' => $callbackData['payer_email'] ?? $callbackData['payeremail'] ?? null,
            'payer_phone' => $callbackData['payer_phone'] ?? $callbackData['payermsisdn'] ?? null,
            'payment_method' => $this->detectPaymentMethod($callbackData),
            'paid_at' => $this->parseCallbackDate($callbackData),
            'raw_data' => $callbackData,
            'processed_at' => now()->toISOString()
        ];

        // Nettoyer le numéro de téléphone si présent
        if ($processedData['payer_phone']) {
            $processedData['payer_phone'] = $this->formatPhoneNumber($processedData['payer_phone']);
        }

        // Mapper le statut EBILLING vers nos statuts
        $processedData['mapped_status'] = $this->mapEbillingStatus($processedData['status']);

        Log::info('EBILLING: Données callback traitées', [
            'bill_id' => $processedData['bill_id'],
            'status' => $processedData['status'],
            'mapped_status' => $processedData['mapped_status'],
            'amount' => $processedData['amount']
        ]);

        return $processedData;
    }

    /**
     * Détecter la méthode de paiement depuis les données callback
     */
    private function detectPaymentMethod(array $callbackData): ?string
    {
        // Chercher des indices dans les données
        if (isset($callbackData['payment_method'])) {
            return $callbackData['payment_method'];
        }

        // Détecter par le numéro de téléphone
        $phone = $callbackData['payermsisdn'] ?? $callbackData['payer_phone'] ?? '';
        if (str_starts_with($phone, '07') || str_starts_with($phone, '24107')) {
            return 'airtel_money';
        }

        if (str_starts_with($phone, '06') || str_starts_with($phone, '24106')) {
            return 'moov_money';
        }

        // Détecter par le gateway
        if (isset($callbackData['gateway']) && str_contains($callbackData['gateway'], 'ORABANK')) {
            return 'visa_mastercard';
        }

        return null;
    }

    /**
     * Parser la date de paiement depuis le callback
     */
    private function parseCallbackDate(array $callbackData): ?string
    {
        $dateFields = ['paid_at', 'payment_date', 'transaction_date', 'date'];

        foreach ($dateFields as $field) {
            if (isset($callbackData[$field]) && !empty($callbackData[$field])) {
                try {
                    return \Carbon\Carbon::parse($callbackData[$field])->toISOString();
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // Si pas de date trouvée et statut = paid, utiliser maintenant
        if (($callbackData['status'] ?? '') === 'paid') {
            return now()->toISOString();
        }

        return null;
    }

    /**
     * Mapper les statuts EBILLING vers nos statuts internes
     */
    private function mapEbillingStatus(string $ebillingStatus): string
    {
        return match (strtolower($ebillingStatus)) {
            'paid', 'completed', 'success' => 'completed',
            'pending', 'processing' => 'processing',
            'failed', 'error', 'cancelled', 'expired' => 'failed',
            default => 'pending'
        };
    }

    /**
     * Vérifier la signature d'un webhook (version améliorée)
     */
    private function verifyWebhookSignature(array $callbackData): bool
    {
        if (!isset($callbackData['signature'])) {
            // Si pas de signature configurée, on accepte (mode développement)
            return !config('ebilling.webhook_secret');
        }

        $providedSignature = $callbackData['signature'];

        // Construire la chaîne à signer (selon la documentation EBILLING)
        $dataToSign = implode('|', [
            $callbackData['bill_id'],
            $callbackData['status'],
            $callbackData['amount'],
            $this->sharedKey
        ]);

        $expectedSignature = hash('sha256', $dataToSign);

        $isValid = hash_equals($expectedSignature, $providedSignature);

        if (!$isValid) {
            Log::warning('EBILLING: Signature webhook invalide', [
                'bill_id' => $callbackData['bill_id'],
                'provided_signature' => $providedSignature,
                'expected_signature' => $expectedSignature
            ]);
        }

        return $isValid;
    }
}
