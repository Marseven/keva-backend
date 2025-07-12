<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EbillingService;
use App\Services\OrderService;
use App\Models\Payment;
use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EbillingCallbackController extends Controller
{
    use ApiResponseTrait;

    private EbillingService $ebillingService;
    private OrderService $orderService;

    public function __construct(EbillingService $ebillingService, OrderService $orderService)
    {
        $this->ebillingService = $ebillingService;
        $this->orderService = $orderService;
    }

    /**
     * @OA\Post(
     *     path="/api/ebilling/callback",
     *     tags={"Paiements"},
     *     summary="Callback EBILLING",
     *     description="Endpoint pour recevoir les notifications de paiement d'EBILLING",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="reference", type="string", example="KEV-202507-0001"),
     *             @OA\Property(property="amount", type="string", example="50000"),
     *             @OA\Property(property="transactionid", type="string", example="TXN123456789"),
     *             @OA\Property(property="billingid", type="string", example="5550051928"),
     *             @OA\Property(property="customername", type="string", example="Jean Dupont"),
     *             @OA\Property(property="payeremail", type="string", example="jean@example.com"),
     *             @OA\Property(property="payermsisdn", type="string", example="077549492")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Callback traité avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement confirmé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données de callback invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Données invalides")
     *         )
     *     )
     * )
     */
    public function handleCallback(Request $request): JsonResponse
    {
        Log::info('EBILLING: Callback reçu', [
            'data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $callbackData = $request->all();

            // Valider les données du callback
            if (!$this->ebillingService->validateCallback($callbackData)) {
                Log::warning('EBILLING: Callback invalide', ['data' => $callbackData]);
                return $this->errorResponse('Données de callback invalides', null, 400);
            }

            // Traiter les données
            $processedData = $this->ebillingService->processCallbackData($callbackData);
            $billId = $processedData['bill_id'];

            // Trouver le paiement correspondant
            $payment = Payment::where('bill_id', $billId)->first();

            if (!$payment) {
                Log::error('EBILLING: Paiement non trouvé', ['bill_id' => $billId]);
                return $this->errorResponse('Paiement non trouvé', null, 404);
            }

            // Éviter le double traitement
            if ($payment->status === 'paid') {
                Log::info('EBILLING: Paiement déjà traité', ['bill_id' => $billId]);
                return $this->successResponse(null, 'Paiement déjà confirmé');
            }

            // Traiter le paiement réussi
            DB::beginTransaction();

            try {
                $this->orderService->markPaymentAsSuccessful($billId, $processedData);

                Log::info('EBILLING: Paiement confirmé avec succès', [
                    'bill_id' => $billId,
                    'order_id' => $payment->order_id,
                    'transaction_id' => $processedData['transaction_id'],
                    'amount' => $processedData['amount']
                ]);

                // Envoyer une notification à l'utilisateur (email, SMS, etc.)
                $this->sendPaymentConfirmationNotification($payment->order);

                DB::commit();

                return $this->successResponse(null, 'Paiement confirmé avec succès');
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur traitement callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);

            return $this->errorResponse('Erreur lors du traitement du callback', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/ebilling/payment-status/{billId}",
     *     tags={"Paiements"},
     *     summary="Vérifier le statut d'un paiement",
     *     description="Vérifier manuellement le statut d'un paiement via l'API EBILLING",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="billId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="ID de la facture EBILLING"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut du paiement récupéré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statut récupéré"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="bill_id", type="string", example="5550051928"),
     *                 @OA\Property(property="status", type="string", example="paid"),
     *                 @OA\Property(property="amount", type="number", example=50000),
     *                 @OA\Property(property="order_status", type="string", example="confirmed")
     *             )
     *         )
     *     )
     * )
     */
    public function checkPaymentStatus(Request $request, string $billId): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur a accès à ce paiement
            $payment = Payment::where('bill_id', $billId)->first();

            if (!$payment) {
                return $this->notFoundResponse('Paiement non trouvé');
            }

            // Vérifier les permissions
            if ($payment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Accès non autorisé');
            }

            // Récupérer le statut depuis EBILLING
            $ebillingStatus = $this->ebillingService->getBillStatus($billId);

            // Mettre à jour le statut local si nécessaire
            $this->syncPaymentStatus($payment, $ebillingStatus);

            return $this->successResponse([
                'bill_id' => $billId,
                'local_status' => $payment->fresh()->status,
                'ebilling_status' => $ebillingStatus['e_bill']['status'] ?? 'unknown',
                'amount' => $payment->amount,
                'order_id' => $payment->order_id,
                'order_status' => $payment->order->status,
                'created_at' => $payment->created_at,
                'paid_at' => $payment->paid_at,
            ], 'Statut du paiement récupéré');
        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur vérification statut', [
                'bill_id' => $billId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la vérification du statut', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/ebilling/retry-push",
     *     tags={"Paiements"},
     *     summary="Relancer un PUSH USSD",
     *     description="Relancer l'envoi d'un code USSD pour paiement mobile money",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bill_id","phone_number"},
     *             @OA\Property(property="bill_id", type="string", example="5550051928"),
     *             @OA\Property(property="phone_number", type="string", example="077549492"),
     *             @OA\Property(property="payment_system", type="string", enum={"airtelmoney","moovmoney4"}, example="airtelmoney")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PUSH USSD relancé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Code USSD envoyé"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="push_sent", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function retryUssdPush(Request $request): JsonResponse
    {
        $request->validate([
            'bill_id' => 'required|string',
            'phone_number' => 'required|string|size:9',
            'payment_system' => 'nullable|in:airtelmoney,moovmoney4'
        ]);

        try {
            $billId = $request->bill_id;
            $phoneNumber = $request->phone_number;

            // Vérifier que l'utilisateur a accès à ce paiement
            $payment = Payment::where('bill_id', $billId)->first();

            if (!$payment) {
                return $this->notFoundResponse('Paiement non trouvé');
            }

            if ($payment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Accès non autorisé');
            }

            if ($payment->status === 'paid') {
                return $this->errorResponse('Ce paiement a déjà été effectué', null, 400);
            }

            // Déterminer le système de paiement
            $paymentSystem = $request->payment_system;
            if (!$paymentSystem) {
                $paymentSystem = str_starts_with($phoneNumber, '07') ? 'airtelmoney' : 'moovmoney4';
            }

            // Valider le numéro selon l'opérateur
            if ($paymentSystem === 'airtelmoney' && !preg_match('/^07\d{7}$/', $phoneNumber)) {
                return $this->errorResponse('Numéro Airtel Money invalide (doit commencer par 07)', null, 400);
            }

            if ($paymentSystem === 'moovmoney4' && !preg_match('/^06\d{7}$/', $phoneNumber)) {
                return $this->errorResponse('Numéro Moov Money invalide (doit commencer par 06)', null, 400);
            }

            // Envoyer le PUSH USSD
            $pushResponse = $this->ebillingService->sendUssdPush($billId, $phoneNumber, $paymentSystem);
            $pushSent = $pushResponse['message'] === 'Accepted';

            Log::info('EBILLING: PUSH USSD relancé', [
                'bill_id' => $billId,
                'phone_number' => $phoneNumber,
                'payment_system' => $paymentSystem,
                'push_sent' => $pushSent,
                'user_id' => $request->user()->id
            ]);

            return $this->successResponse([
                'push_sent' => $pushSent,
                'phone_number' => $phoneNumber,
                'payment_system' => $paymentSystem,
                'message' => $pushSent
                    ? "Code USSD envoyé au {$phoneNumber}. Suivez les instructions pour confirmer le paiement."
                    : "Erreur lors de l'envoi du code USSD. Veuillez réessayer."
            ], $pushSent ? 'Code USSD envoyé avec succès' : 'Erreur envoi code USSD');
        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur relance PUSH USSD', [
                'bill_id' => $request->bill_id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);

            return $this->errorResponse('Erreur lors de l\'envoi du code USSD', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/ebilling/test-connection",
     *     tags={"Paiements"},
     *     summary="Tester la connexion EBILLING",
     *     description="Tester la connectivité avec l'API EBILLING (admin uniquement)",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Test de connexion effectué",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Test de connexion effectué"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="connection_success", type="boolean", example=true),
     *                 @OA\Property(property="status_code", type="integer", example=200),
     *                 @OA\Property(property="response_time", type="number", example=0.5),
     *                 @OA\Property(property="message", type="string", example="Connexion réussie")
     *             )
     *         )
     *     )
     * )
     */
    public function testConnection(Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est admin
        if (!$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Accès réservé aux administrateurs');
        }

        try {
            $testResult = $this->ebillingService->testConnection();

            Log::info('EBILLING: Test de connexion effectué', [
                'user_id' => $request->user()->id,
                'result' => $testResult
            ]);

            return $this->successResponse($testResult, 'Test de connexion effectué');
        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur test connexion', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);

            return $this->errorResponse('Erreur lors du test de connexion', null, 500);
        }
    }

    /**
     * Synchroniser le statut du paiement avec EBILLING
     */
    private function syncPaymentStatus(Payment $payment, array $ebillingData): void
    {
        $ebillingStatus = $ebillingData['e_bill']['status'] ?? 'unknown';

        // Mapper les statuts EBILLING vers nos statuts
        $statusMap = [
            'paid' => 'paid',
            'pending' => 'pending',
            'failed' => 'failed',
            'expired' => 'failed',
            'cancelled' => 'failed'
        ];

        $newStatus = $statusMap[$ebillingStatus] ?? 'pending';

        if ($payment->status !== $newStatus) {
            $payment->update(['status' => $newStatus]);

            if ($newStatus === 'paid' && !$payment->paid_at) {
                $payment->update(['paid_at' => now()]);

                // Mettre à jour la commande
                $payment->order->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed'
                ]);
            }

            Log::info('EBILLING: Statut paiement synchronisé', [
                'payment_id' => $payment->id,
                'old_status' => $payment->getOriginal('status'),
                'new_status' => $newStatus,
                'ebilling_status' => $ebillingStatus
            ]);
        }
    }

    /**
     * Envoyer une notification de confirmation de paiement
     */
    private function sendPaymentConfirmationNotification(Order $order): void
    {
        try {
            // Ici vous pouvez implémenter l'envoi d'email, SMS, notification push, etc.
            // Exemple : envoyer un email de confirmation

            Log::info('EBILLING: Notification paiement confirmé envoyée', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_email' => $order->customer_email
            ]);

            // TODO: Implémenter l'envoi des notifications réelles
            // Mail::to($order->customer_email)->send(new PaymentConfirmedMail($order));

        } catch (\Exception $e) {
            Log::error('EBILLING: Erreur envoi notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
