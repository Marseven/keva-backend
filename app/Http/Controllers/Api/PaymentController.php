<?php
// app/Http/Controllers/Api/PaymentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\Order;
use App\Services\PaymentService;
use App\Services\EbillingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    private PaymentService $paymentService;
    private EbillingService $ebillingService;

    public function __construct(
        PaymentService $paymentService,
        EbillingService $ebillingService
    ) {
        $this->paymentService = $paymentService;
        $this->ebillingService = $ebillingService;
    }

    /**
     * @OA\Get(
     *     path="/api/payments",
     *     tags={"Paiements"},
     *     summary="Historique des paiements",
     *     description="Récupérer l'historique des paiements de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","processing","completed","failed","cancelled","refunded"}),
     *         description="Filtrer par statut"
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"airtel_money","moov_money","visa_mastercard","bank_transfer","cash"}),
     *         description="Filtrer par méthode de paiement"
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date"),
     *         description="Date de début (YYYY-MM-DD)"
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date"),
     *         description="Date de fin (YYYY-MM-DD)"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, maximum=50),
     *         description="Nombre de paiements par page"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historique des paiements récupéré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiements récupérés avec succès"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Payment")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filters = $request->only(['status', 'payment_method', 'date_from', 'date_to']);
            $filters['user_id'] = $user->id;

            $perPage = min($request->get('per_page', 10), 50);

            $query = Payment::where('user_id', $user->id)
                ->with(['order']);

            // Appliquer les filtres
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['payment_method'])) {
                $query->where('payment_method', $filters['payment_method']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            $payments = $query->latest()->paginate($perPage);

            // Transformer les données
            $paymentsData = $payments->getCollection()->map(function ($payment) {
                return $this->transformPayment($payment);
            });

            return $this->paginatedResponse(
                $payments->setCollection($paymentsData),
                'Paiements récupérés avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur récupération paiements', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des paiements', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/initiate",
     *     tags={"Paiements"},
     *     summary="Initier un paiement",
     *     description="Initier le processus de paiement pour une commande",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id","payment_method","payer_name","payer_phone"},
     *             @OA\Property(property="order_id", type="integer", example=101),
     *             @OA\Property(property="payment_method", type="string", enum={"airtel_money","moov_money","visa_mastercard","bank_transfer","cash"}, example="airtel_money"),
     *             @OA\Property(property="payer_name", type="string", example="Jean Mabiala"),
     *             @OA\Property(property="payer_email", type="string", format="email", example="jean@example.com"),
     *             @OA\Property(property="payer_phone", type="string", example="077549492"),
     *             @OA\Property(property="redirect_url", type="string", format="uri", example="https://myapp.com/payment/success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Paiement initié avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement initié avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment", ref="#/components/schemas/Payment"),
     *                 @OA\Property(property="bill_id", type="string", example="KEVA-20250712-ABC123"),
     *                 @OA\Property(property="next_action", type="object",
     *                     @OA\Property(property="type", type="string", example="ussd_push"),
     *                     @OA\Property(property="message", type="string", example="Composez *150# pour finaliser"),
     *                     @OA\Property(property="payment_url", type="string", example="https://payment.ebilling.net/pay/ABC123")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur dans les données ou commande non payable",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cette commande ne peut pas être payée"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function initiate(PaymentRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Vérifier que la commande existe et appartient à l'utilisateur
            $order = Order::where('id', $data['order_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return $this->notFoundResponse('Commande non trouvée');
            }

            // Vérifier que la commande peut être payée
            if ($order->payment_status === 'paid') {
                return $this->errorResponse('Cette commande est déjà payée', null, 400);
            }

            if (!in_array($order->status, ['pending', 'confirmed'])) {
                return $this->errorResponse('Cette commande ne peut pas être payée', null, 400);
            }

            // Préparer les informations du payeur
            $payerInfo = [
                'name' => $data['payer_name'],
                'email' => $data['payer_email'] ?? $user->email,
                'phone' => $data['payer_phone'],
            ];

            // Initier le paiement via le service
            $result = $this->paymentService->initiatePayment(
                $order,
                $data['payment_method'],
                $payerInfo
            );

            if (!$result['success']) {
                return $this->errorResponse($result['error'], null, 400);
            }

            Log::info('Paiement initié avec succès', [
                'payment_id' => $result['payment']->id,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'payment_method' => $data['payment_method'],
                'amount' => $order->total_amount
            ]);

            return $this->createdResponse([
                'payment' => $this->transformPayment($result['payment']),
                'bill_id' => $result['bill_id'] ?? null,
                'next_action' => $result['next_action'] ?? null,
                'ussd_initiated' => $result['ussd_initiated'] ?? false,
                'ussd_message' => $result['ussd_message'] ?? null,
            ], 'Paiement initié avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur initiation paiement', [
                'user_id' => $request->user()->id,
                'order_id' => $data['order_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Erreur lors de l\'initiation du paiement', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/{payment}",
     *     tags={"Paiements"},
     *     summary="Détails d'un paiement",
     *     description="Récupérer les détails d'un paiement spécifique",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du paiement"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du paiement récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement récupéré avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Paiement non trouvé",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès non autorisé à ce paiement",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        try {
            // Vérifier l'accès
            if ($payment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Vous n\'avez pas accès à ce paiement');
            }

            // Charger les relations
            $payment->load(['order', 'user']);

            return $this->successResponse(
                $this->transformPaymentDetail($payment),
                'Paiement récupéré avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur récupération paiement', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération du paiement', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/{payment}/status",
     *     tags={"Paiements"},
     *     summary="Vérifier le statut d'un paiement",
     *     description="Vérifier le statut actuel d'un paiement auprès du fournisseur",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du paiement"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut du paiement vérifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statut vérifié"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="local_status", type="string", example="completed"),
     *                 @OA\Property(property="gateway_status", type="string", example="paid"),
     *                 @OA\Property(property="amount", type="number", example=50000),
     *                 @OA\Property(property="paid_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function checkStatus(Request $request, Payment $payment): JsonResponse
    {
        try {
            // Vérifier l'accès
            if ($payment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Vous n\'avez pas accès à ce paiement');
            }

            // Vérifier le statut via le service
            $result = $this->paymentService->checkPaymentStatus($payment);

            if (!$result['success']) {
                return $this->errorResponse($result['error'], null, 400);
            }

            Log::info('Statut paiement vérifié', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'status' => $result['status']
            ]);

            return $this->successResponse($result, 'Statut vérifié avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur vérification statut paiement', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la vérification du statut', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/{payment}/cancel",
     *     tags={"Paiements"},
     *     summary="Annuler un paiement",
     *     description="Annuler un paiement en cours",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du paiement"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Changement de méthode de paiement")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement annulé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement annulé avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Paiement ne peut pas être annulé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ce paiement ne peut pas être annulé"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        try {
            // Vérifier l'accès
            if ($payment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Vous n\'avez pas accès à ce paiement');
            }

            $reason = $request->get('reason', 'Annulé par l\'utilisateur');

            // Annuler le paiement via le service
            $success = $this->paymentService->cancelPayment($payment, $reason);

            if (!$success) {
                return $this->errorResponse('Ce paiement ne peut pas être annulé', null, 400);
            }

            Log::info('Paiement annulé', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'reason' => $reason
            ]);

            return $this->successResponse(
                $this->transformPayment($payment->fresh()),
                'Paiement annulé avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur annulation paiement', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de l\'annulation du paiement', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/{payment}/confirm",
     *     tags={"Paiements"},
     *     summary="Confirmer un paiement (Admin)",
     *     description="Confirmer manuellement un paiement - réservé aux administrateurs",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du paiement"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="transaction_id", type="string", example="TXN123456789"),
     *             @OA\Property(property="notes", type="string", example="Paiement confirmé après vérification bancaire")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement confirmé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiement confirmé avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès réservé aux administrateurs",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        // Vérifier les permissions admin
        if (!$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Accès réservé aux administrateurs');
        }

        $request->validate([
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            $confirmationData = $request->only(['transaction_id', 'notes']);

            // Confirmer le paiement via le service
            $success = $this->paymentService->confirmPayment($payment, $confirmationData);

            if (!$success) {
                return $this->errorResponse('Erreur lors de la confirmation du paiement', null, 500);
            }

            Log::info('Paiement confirmé manuellement', [
                'payment_id' => $payment->id,
                'admin_id' => $request->user()->id,
                'transaction_id' => $confirmationData['transaction_id'] ?? null
            ]);

            return $this->successResponse(
                $this->transformPayment($payment->fresh()),
                'Paiement confirmé avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur confirmation paiement', [
                'payment_id' => $payment->id,
                'admin_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la confirmation du paiement', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/{payment}/retry-ussd",
     *     tags={"Paiements"},
     *     summary="Relancer le code USSD",
     *     description="Relancer l'envoi du code USSD pour un paiement Mobile Money",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du paiement"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone_number", type="string", example="077549492"),
     *             @OA\Property(property="payment_system", type="string", enum={"airtelmoney","moovmoney4"}, example="airtelmoney")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code USSD envoyé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Code USSD envoyé avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="push_sent", type="boolean", example=true),
     *                 @OA\Property(property="phone_number", type="string", example="077549492"),
     *                 @OA\Property(property="message", type="string", example="Code USSD envoyé au 077549492")
     *             )
     *         )
     *     )
     * )
     */
    public function retryUssd(Request $request, Payment $payment): JsonResponse
    {
        // Vérifier l'accès
        if ($payment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à ce paiement');
        }

        $request->validate([
            'phone_number' => 'nullable|string|size:9',
            'payment_system' => 'nullable|in:airtelmoney,moovmoney4'
        ]);

        try {
            // Vérifier que c'est un paiement Mobile Money
            if (!in_array($payment->payment_method, ['airtel_money', 'moov_money'])) {
                return $this->errorResponse('Cette fonctionnalité est réservée aux paiements Mobile Money', null, 400);
            }

            // Vérifier que le paiement n'est pas déjà terminé
            if ($payment->status === 'completed') {
                return $this->errorResponse('Ce paiement a déjà été effectué', null, 400);
            }

            $phoneNumber = $request->get('phone_number', $payment->payer_phone);
            $paymentSystem = $request->get('payment_system', $payment->payment_method === 'airtel_money' ? 'airtelmoney' : 'moovmoney4');

            // Valider le numéro selon l'opérateur
            if ($paymentSystem === 'airtelmoney' && !preg_match('/^07\d{7}$/', $phoneNumber)) {
                return $this->errorResponse('Numéro Airtel Money invalide (doit commencer par 07)', null, 400);
            }

            if ($paymentSystem === 'moovmoney4' && !preg_match('/^06\d{7}$/', $phoneNumber)) {
                return $this->errorResponse('Numéro Moov Money invalide (doit commencer par 06)', null, 400);
            }

            // Envoyer le PUSH USSD via EBILLING
            if (!$payment->bill_id) {
                return $this->errorResponse('Aucun ID de facture EBILLING trouvé', null, 400);
            }

            $pushResponse = $this->ebillingService->sendUssdPush($payment->bill_id, $phoneNumber, $paymentSystem);
            $pushSent = $pushResponse['success'] ?? false;

            Log::info('USSD Push relancé', [
                'payment_id' => $payment->id,
                'bill_id' => $payment->bill_id,
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
            Log::error('Erreur relance USSD', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de l\'envoi du code USSD', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/methods",
     *     tags={"Paiements"},
     *     summary="Méthodes de paiement disponibles",
     *     description="Récupérer la liste des méthodes de paiement disponibles",
     *     @OA\Response(
     *         response=200,
     *         description="Méthodes de paiement récupérées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Méthodes de paiement disponibles"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="key", type="string", example="airtel_money"),
     *                     @OA\Property(property="name", type="string", example="airtelmoney"),
     *                     @OA\Property(property="display_name", type="string", example="Airtel Money"),
     *                     @OA\Property(property="supported", type="boolean", example=true),
     *                     @OA\Property(property="test_mode", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getMethods(): JsonResponse
    {
        try {
            $methods = $this->paymentService->getAvailablePaymentMethods();

            return $this->successResponse($methods, 'Méthodes de paiement disponibles');
        } catch (\Exception $e) {
            Log::error('Erreur récupération méthodes paiement', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des méthodes', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/stats",
     *     tags={"Paiements"},
     *     summary="Statistiques des paiements",
     *     description="Récupérer les statistiques des paiements de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"today","week","month","year"}, default="month"),
     *         description="Période des statistiques"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques des paiements",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistiques récupérées"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_payments", type="integer", example=45),
     *                 @OA\Property(property="successful_payments", type="integer", example=40),
     *                 @OA\Property(property="failed_payments", type="integer", example=3),
     *                 @OA\Property(property="pending_payments", type="integer", example=2),
     *                 @OA\Property(property="total_amount_paid", type="number", example=890000),
     *                 @OA\Property(property="average_payment_amount", type="number", example=22250),
     *                 @OA\Property(property="most_used_method", type="string", example="airtel_money"),
     *                 @OA\Property(property="success_rate", type="number", example=88.89)
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $stats = $this->paymentService->getUserPaymentStats($user);

            return $this->successResponse($stats, 'Statistiques des paiements récupérées');
        } catch (\Exception $e) {
            Log::error('Erreur statistiques paiements', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des statistiques', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/process-pending",
     *     tags={"Paiements"},
     *     summary="Traiter les paiements en attente (Admin)",
     *     description="Traiter manuellement les paiements en attente - réservé aux administrateurs",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Paiements traités",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Paiements en attente traités"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="processed", type="integer", example=5),
     *                 @OA\Property(property="errors", type="integer", example=1),
     *                 @OA\Property(property="total_checked", type="integer", example=6)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès réservé aux administrateurs",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function processPending(Request $request): JsonResponse
    {
        // Vérifier les permissions admin
        if (!$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Accès réservé aux administrateurs');
        }

        try {
            $result = $this->paymentService->processPendingPayments();

            Log::info('Traitement paiements en attente', [
                'admin_id' => $request->user()->id,
                'processed' => $result['processed'],
                'errors' => $result['errors'],
                'total_checked' => $result['total_checked']
            ]);

            return $this->successResponse($result, 'Paiements en attente traités');
        } catch (\Exception $e) {
            Log::error('Erreur traitement paiements en attente', [
                'admin_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors du traitement des paiements', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/report",
     *     tags={"Paiements"},
     *     summary="Générer un rapport de paiements (Admin)",
     *     description="Générer un rapport détaillé des paiements - réservé aux administrateurs",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="date_from", type="string", format="date", example="2025-01-01"),
     *             @OA\Property(property="date_to", type="string", format="date", example="2025-01-31"),
     *             @OA\Property(property="status", type="string", example="completed"),
     *             @OA\Property(property="payment_method", type="string", example="airtel_money"),
     *             @OA\Property(property="user_id", type="integer", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rapport généré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rapport généré"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_payments", type="integer", example=1250),
     *                 @OA\Property(property="total_amount", type="number", example=25600000),
     *                 @OA\Property(property="successful_payments", type="integer", example=1100),
     *                 @OA\Property(property="success_rate", type="number", example=88),
     *                 @OA\Property(property="by_method", type="object"),
     *                 @OA\Property(property="by_status", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function generateReport(Request $request): JsonResponse
    {
        // Vérifier les permissions admin
        if (!$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Accès réservé aux administrateurs');
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id'
        ]);

        try {
            $filters = $request->only(['date_from', 'date_to', 'status', 'payment_method', 'user_id']);
            $report = $this->paymentService->generatePaymentReport($filters);

            Log::info('Rapport paiements généré', [
                'admin_id' => $request->user()->id,
                'filters' => $filters,
                'total_payments' => $report['total_payments']
            ]);

            return $this->successResponse($report, 'Rapport généré avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur génération rapport', [
                'admin_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la génération du rapport', null, 500);
        }
    }

    /**
     * Transformer un paiement pour l'API
     */
    private function transformPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_id' => $payment->payment_id,
            'transaction_id' => $payment->transaction_id,
            'bill_id' => $payment->bill_id,
            'amount' => $payment->amount,
            'formatted_amount' => $payment->formatted_amount,
            'currency' => $payment->currency,
            'payment_method' => $payment->payment_method,
            'method_display_name' => $payment->method_display_name,
            'payment_provider' => $payment->payment_provider,
            'status' => $payment->status,
            'status_badge' => $payment->status_badge,
            'payer_name' => $payment->payer_name,
            'payer_email' => $payment->payer_email,
            'payer_phone' => $payment->payer_phone,
            'failure_reason' => $payment->failure_reason,
            'is_successful' => $payment->is_successful,
            'can_be_refunded' => $payment->can_be_refunded,
            'paid_at' => $payment->paid_at,
            'failed_at' => $payment->failed_at,
            'refunded_at' => $payment->refunded_at,
            'order' => $payment->order ? [
                'id' => $payment->order->id,
                'order_number' => $payment->order->order_number,
                'status' => $payment->order->status,
                'total_amount' => $payment->order->total_amount,
            ] : null,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
        ];
    }

    /**
     * Transformer un paiement avec détails complets
     */
    private function transformPaymentDetail(Payment $payment): array
    {
        $data = $this->transformPayment($payment);

        // Ajouter les métadonnées et réponses gateway (sans données sensibles)
        $data['metadata'] = $payment->metadata;

        // Filtrer les données sensibles de gateway_response
        $gatewayResponse = $payment->gateway_response ?? [];
        unset($gatewayResponse['shared_key'], $gatewayResponse['private_key']);
        $data['gateway_response'] = $gatewayResponse;

        // Ajouter l'historique des tentatives (si applicable)
        $data['retry_count'] = $payment->metadata['retry_count'] ?? 0;
        $data['last_retry_at'] = $payment->metadata['last_retry_at'] ?? null;

        return $data;
    }
}
