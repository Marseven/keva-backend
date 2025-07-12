<?php
// app/Http/Controllers/Api/OrderController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\CartService;
use App\Services\PaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    use ApiResponseTrait;

    private OrderService $orderService;
    private CartService $cartService;
    private PaymentService $paymentService;

    public function __construct(
        OrderService $orderService,
        CartService $cartService,
        PaymentService $paymentService
    ) {
        $this->orderService = $orderService;
        $this->cartService = $cartService;
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     tags={"Commandes"},
     *     summary="Lister les commandes de l'utilisateur",
     *     description="Récupérer l'historique des commandes de l'utilisateur connecté",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","confirmed","processing","shipped","delivered","cancelled","refunded"}),
     *         description="Filtrer par statut"
     *     ),
     *     @OA\Parameter(
     *         name="payment_status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","paid","failed","refunded","partial"}),
     *         description="Filtrer par statut de paiement"
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
     *         description="Nombre de commandes par page"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des commandes récupérée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commandes récupérées avec succès"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Order")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filters = $request->only(['status', 'payment_status', 'date_from', 'date_to', 'order_number', 'per_page']);

            $orders = $this->orderService->searchOrders($user, $filters);

            // Transformer les données
            $ordersData = $orders->getCollection()->map(function ($order) {
                return $this->transformOrder($order);
            });

            return $this->paginatedResponse(
                $orders->setCollection($ordersData),
                'Commandes récupérées avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur récupération commandes', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des commandes', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/orders",
     *     tags={"Commandes"},
     *     summary="Créer une nouvelle commande",
     *     description="Créer une commande à partir du panier de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"shipping_address","payment_method","items"},
     *             @OA\Property(
     *                 property="shipping_address",
     *                 type="object",
     *                 required={"name","phone","address","city"},
     *                 @OA\Property(property="name", type="string", example="Jean Mabiala"),
     *                 @OA\Property(property="phone", type="string", example="+241123456789"),
     *                 @OA\Property(property="address", type="string", example="123 Rue de la Paix"),
     *                 @OA\Property(property="city", type="string", example="Libreville"),
     *                 @OA\Property(property="postal_code", type="string", example="")
     *             ),
     *             @OA\Property(
     *                 property="billing_address",
     *                 type="object",
     *                 description="Adresse de facturation (optionnelle, utilise shipping_address si non fournie)"
     *             ),
     *             @OA\Property(property="shipping_method", type="string", example="standard"),
     *             @OA\Property(property="payment_method", type="string", enum={"airtel_money","moov_money","visa_mastercard","bank_transfer","cash"}, example="airtel_money"),
     *             @OA\Property(property="notes", type="string", example="Livrer après 17h"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id","quantity"},
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(property="product_options", type="array", @OA\Items(type="string"))
     *                 )
     *             ),
     *             @OA\Property(property="coupon_code", type="string", example="WELCOME10"),
     *             @OA\Property(property="session_id", type="string", description="Pour panier d'invité")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Commande créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commande créée avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur dans les données ou panier vide",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le panier est vide"),
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
    public function store(OrderRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            DB::beginTransaction();

            // Récupérer les articles du panier
            $cartItems = $this->cartService->getCartItems($user, $data['session_id'] ?? null);

            if ($cartItems->isEmpty()) {
                return $this->errorResponse('Le panier est vide', null, 400);
            }

            // Valider le panier avant création de commande
            $cartErrors = $this->cartService->validateCart($cartItems);
            if (!empty($cartErrors)) {
                return $this->errorResponse('Problèmes détectés dans le panier', $cartErrors, 400);
            }

            // Créer la commande
            $order = $this->orderService->createOrderFromCart($user, $data, $cartItems);

            // Appliquer un coupon si fourni
            if (!empty($data['coupon_code'])) {
                $this->applyCouponToOrder($order, $data['coupon_code']);
            }

            DB::commit();

            // Charger les relations pour la réponse
            $order->load(['items.product', 'user']);

            Log::info('Commande créée avec succès', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $user->id,
                'total_amount' => $order->total_amount
            ]);

            return $this->createdResponse(
                $this->transformOrderDetail($order),
                'Commande créée avec succès'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur création commande', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Erreur lors de la création de la commande: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{order}",
     *     tags={"Commandes"},
     *     summary="Détails d'une commande",
     *     description="Récupérer les détails complets d'une commande",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la commande"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la commande récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commande récupérée avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Commande non trouvée",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès non autorisé à cette commande",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur a accès à cette commande
            if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Vous n\'avez pas accès à cette commande');
            }

            // Charger les relations
            $order->load(['items.product.category', 'payments', 'invoice', 'user']);

            return $this->successResponse(
                $this->transformOrderDetail($order),
                'Commande récupérée avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur récupération commande', [
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération de la commande', null, 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{order}/cancel",
     *     tags={"Commandes"},
     *     summary="Annuler une commande",
     *     description="Annuler une commande si elle est dans un état annulable",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la commande"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Changement d'avis")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commande annulée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commande annulée avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Commande ne peut pas être annulée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cette commande ne peut pas être annulée"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur a accès à cette commande
            if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return $this->forbiddenResponse('Vous n\'avez pas accès à cette commande');
            }

            // Vérifier que la commande peut être annulée
            if (!$order->can_be_cancelled) {
                return $this->errorResponse('Cette commande ne peut pas être annulée', null, 400);
            }

            $reason = $request->get('reason', 'Annulée par le client');

            // Annuler la commande via le service
            $this->orderService->updateOrderStatus($order, 'cancelled', [
                'admin_notes' => "Annulée: {$reason}"
            ]);

            Log::info('Commande annulée', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $request->user()->id,
                'reason' => $reason
            ]);

            return $this->successResponse(
                $this->transformOrder($order->fresh()),
                'Commande annulée avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur annulation commande', [
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de l\'annulation: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{order}/status",
     *     tags={"Commandes"},
     *     summary="Mettre à jour le statut d'une commande (Admin)",
     *     description="Changer le statut d'une commande - réservé aux administrateurs",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la commande"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending","confirmed","processing","shipped","delivered","cancelled","refunded"}, example="shipped"),
     *             @OA\Property(property="tracking_number", type="string", example="TRACK123456789"),
     *             @OA\Property(property="admin_notes", type="string", example="Expédié via DHL")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statut mis à jour avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès réservé aux administrateurs",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        // Vérifier les permissions (admin ou propriétaire de la boutique)
        if (!$request->user()->isAdmin() && $order->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Accès non autorisé');
        }

        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'tracking_number' => 'nullable|string|max:255',
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        try {
            $additionalData = $request->only(['tracking_number', 'admin_notes']);

            // Mettre à jour le statut via le service
            $updatedOrder = $this->orderService->updateOrderStatus(
                $order,
                $request->status,
                $additionalData
            );

            Log::info('Statut commande mis à jour', [
                'order_id' => $order->id,
                'old_status' => $order->status,
                'new_status' => $request->status,
                'admin_id' => $request->user()->id
            ]);

            return $this->successResponse(
                $this->transformOrder($updatedOrder),
                'Statut mis à jour avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour statut', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la mise à jour: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{order}/notes",
     *     tags={"Commandes"},
     *     summary="Ajouter des notes à une commande",
     *     description="Ajouter des notes client ou admin à une commande",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la commande"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Merci de livrer avant 18h"),
     *             @OA\Property(property="admin_notes", type="string", example="Client prioritaire")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notes ajoutées avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notes ajoutées avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     )
     * )
     */
    public function addNotes(Request $request, Order $order): JsonResponse
    {
        // Vérifier l'accès
        if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette commande');
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        try {
            $updateData = [];

            // Notes client (propriétaire seulement)
            if ($request->has('notes') && $order->user_id === $request->user()->id) {
                $updateData['notes'] = $request->notes;
            }

            // Notes admin (admin seulement)
            if ($request->has('admin_notes') && $request->user()->isAdmin()) {
                $updateData['admin_notes'] = $request->admin_notes;
            }

            if (!empty($updateData)) {
                $order->update($updateData);
            }

            return $this->successResponse(
                $this->transformOrder($order->fresh()),
                'Notes ajoutées avec succès'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'ajout des notes', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{order}/track",
     *     tags={"Commandes"},
     *     summary="Suivre une commande",
     *     description="Obtenir les informations de suivi d'une commande",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la commande"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Informations de suivi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Informations de suivi récupérées"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="order_number", type="string", example="KEV-202507-0001"),
     *                 @OA\Property(property="status", type="string", example="shipped"),
     *                 @OA\Property(property="tracking_number", type="string", example="TRACK123456789"),
     *                 @OA\Property(property="shipped_at", type="string", format="date-time"),
     *                 @OA\Property(property="estimated_delivery", type="string", format="date-time"),
     *                 @OA\Property(property="timeline", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function track(Request $request, Order $order): JsonResponse
    {
        // Vérifier l'accès
        if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette commande');
        }

        try {
            $trackingInfo = [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_display' => $order->status_badge['text'],
                'tracking_number' => $order->tracking_number,
                'shipped_at' => $order->shipped_at,
                'delivered_at' => $order->delivered_at,
                'estimated_delivery' => $order->shipped_at?->addDays(3), // Estimation simple
                'timeline' => $this->generateTrackingTimeline($order)
            ];

            return $this->successResponse($trackingInfo, 'Informations de suivi récupérées');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du suivi', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/orders/{order}/reorder",
     *     tags={"Commandes"},
     *     summary="Renouveler une commande",
     *     description="Ajouter les articles d'une commande précédente au panier",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID de la commande à renouveler"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Articles ajoutés au panier",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Articles ajoutés au panier"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="added_items", type="integer", example=3),
     *                 @OA\Property(property="unavailable_items", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function reorder(Request $request, Order $order): JsonResponse
    {
        // Vérifier l'accès
        if ($order->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Vous n\'avez pas accès à cette commande');
        }

        try {
            $user = $request->user();
            $addedItems = 0;
            $unavailableItems = [];

            foreach ($order->items as $item) {
                try {
                    $product = $item->product;

                    if (!$product || $product->status !== 'active') {
                        $unavailableItems[] = $item->product_name . ' (produit indisponible)';
                        continue;
                    }

                    if (!$product->is_in_stock) {
                        $unavailableItems[] = $item->product_name . ' (en rupture de stock)';
                        continue;
                    }

                    // Ajouter au panier
                    $this->cartService->addToCart(
                        $product,
                        $item->quantity,
                        $item->product_options ?? [],
                        $user
                    );

                    $addedItems++;
                } catch (\Exception $e) {
                    $unavailableItems[] = $item->product_name . ' (erreur)';
                }
            }

            $message = $addedItems > 0
                ? "Articles ajoutés au panier avec succès"
                : "Aucun article n'a pu être ajouté au panier";

            return $this->successResponse([
                'added_items' => $addedItems,
                'unavailable_items' => $unavailableItems,
                'total_items' => $order->items->count()
            ], $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du renouvellement', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/stats",
     *     tags={"Commandes"},
     *     summary="Statistiques des commandes",
     *     description="Récupérer les statistiques des commandes de l'utilisateur",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques récupérées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistiques récupérées"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_orders", type="integer", example=25),
     *                 @OA\Property(property="pending_orders", type="integer", example=2),
     *                 @OA\Property(property="completed_orders", type="integer", example=20),
     *                 @OA\Property(property="total_spent", type="number", example=485000),
     *                 @OA\Property(property="average_order_value", type="number", example=19400)
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $stats = $this->orderService->getUserOrderStats($user);

            return $this->successResponse($stats, 'Statistiques récupérées avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des statistiques', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/sales",
     *     tags={"Commandes"},
     *     summary="Commandes reçues (Vendeur)",
     *     description="Lister les commandes reçues pour les produits du vendeur",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Filtrer par statut"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=10),
     *         description="Nombre par page"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commandes reçues récupérées",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Commandes reçues récupérées"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Order"))
     *         )
     *     )
     * )
     */
    public function sales(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = min($request->get('per_page', 10), 50);

            // Récupérer les commandes contenant des produits du vendeur
            $query = Order::whereHas('items.product', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->with(['items.product', 'user']);

            // Filtrer par statut si spécifié
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filtrer par période si spécifiée
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $orders = $query->latest()->paginate($perPage);

            // Transformer les données en ne gardant que les items du vendeur
            $ordersData = $orders->getCollection()->map(function ($order) use ($user) {
                $orderData = $this->transformOrder($order);

                // Filtrer les items pour ne garder que ceux du vendeur
                $orderData['items'] = collect($order->items)
                    ->filter(function ($item) use ($user) {
                        return $item->product && $item->product->user_id === $user->id;
                    })
                    ->map(function ($item) {
                        return $this->transformOrderItem($item);
                    })
                    ->values()
                    ->toArray();

                // Recalculer le sous-total pour ce vendeur
                $vendorSubtotal = collect($orderData['items'])->sum('total_price');
                $orderData['vendor_subtotal'] = $vendorSubtotal;
                $orderData['vendor_subtotal_formatted'] = number_format($vendorSubtotal, 0, ',', ' ') . ' XAF';

                return $orderData;
            });

            return $this->paginatedResponse(
                $orders->setCollection($ordersData),
                'Commandes reçues récupérées avec succès'
            );
        } catch (\Exception $e) {
            Log::error('Erreur récupération ventes', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des ventes', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/sales/stats",
     *     tags={"Commandes"},
     *     summary="Statistiques des ventes",
     *     description="Statistiques des ventes pour le vendeur",
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
     *         description="Statistiques des ventes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistiques des ventes"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_sales", type="number", example=250000),
     *                 @OA\Property(property="orders_count", type="integer", example=15),
     *                 @OA\Property(property="average_order_value", type="number", example=16667),
     *                 @OA\Property(property="products_sold", type="integer", example=45),
     *                 @OA\Property(property="top_products", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function salesStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $period = $request->get('period', 'month');

            // Définir la période
            $dateFrom = match ($period) {
                'today' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            // Récupérer les statistiques
            $orderItems = \App\Models\OrderItem::whereHas('product', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
                ->whereHas('order', function ($q) use ($dateFrom) {
                    $q->where('created_at', '>=', $dateFrom)
                        ->where('payment_status', 'paid');
                })
                ->with(['product', 'order'])
                ->get();

            $stats = [
                'period' => $period,
                'period_start' => $dateFrom->toDateString(),
                'total_sales' => $orderItems->sum('total_price'),
                'orders_count' => $orderItems->pluck('order_id')->unique()->count(),
                'products_sold' => $orderItems->sum('quantity'),
                'average_order_value' => $orderItems->isNotEmpty()
                    ? $orderItems->sum('total_price') / $orderItems->pluck('order_id')->unique()->count()
                    : 0,
                'top_products' => $orderItems
                    ->groupBy('product_id')
                    ->map(function ($items) {
                        return [
                            'product_id' => $items->first()->product_id,
                            'product_name' => $items->first()->product_name,
                            'quantity_sold' => $items->sum('quantity'),
                            'total_revenue' => $items->sum('total_price'),
                        ];
                    })
                    ->sortByDesc('total_revenue')
                    ->take(5)
                    ->values()
                    ->toArray(),
                'daily_sales' => $this->getDailySales($user, $dateFrom),
            ];

            // Ajouter les formats
            $stats['total_sales_formatted'] = number_format($stats['total_sales'], 0, ',', ' ') . ' XAF';
            $stats['average_order_value_formatted'] = number_format($stats['average_order_value'], 0, ',', ' ') . ' XAF';

            return $this->successResponse($stats, 'Statistiques des ventes récupérées');
        } catch (\Exception $e) {
            Log::error('Erreur statistiques ventes', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erreur lors de la récupération des statistiques', null, 500);
        }
    }

    /**
     * Appliquer un coupon à une commande
     */
    private function applyCouponToOrder(Order $order, string $couponCode): void
    {
        // Implémentation simple - à étendre avec un système de coupons complet
        $validCoupons = [
            'WELCOME10' => ['type' => 'percentage', 'value' => 10, 'min_amount' => 20000],
            'SAVE5000' => ['type' => 'fixed', 'value' => 5000, 'min_amount' => 30000],
            'FIRSTORDER' => ['type' => 'percentage', 'value' => 15, 'min_amount' => 0],
        ];

        $couponCode = strtoupper($couponCode);

        if (isset($validCoupons[$couponCode])) {
            $coupon = $validCoupons[$couponCode];

            if ($order->subtotal >= $coupon['min_amount']) {
                $discountAmount = $coupon['type'] === 'percentage'
                    ? ($order->subtotal * $coupon['value']) / 100
                    : $coupon['value'];

                $order->update([
                    'discount_amount' => $discountAmount,
                    'total_amount' => $order->subtotal + $order->tax_amount + $order->shipping_amount - $discountAmount,
                    'metadata' => array_merge($order->metadata ?? [], [
                        'coupon_code' => $couponCode,
                        'coupon_discount' => $discountAmount,
                    ])
                ]);
            }
        }
    }

    /**
     * Générer la timeline de suivi
     */
    private function generateTrackingTimeline(Order $order): array
    {
        $timeline = [
            [
                'status' => 'pending',
                'title' => 'Commande reçue',
                'description' => 'Votre commande a été reçue et est en cours de traitement',
                'date' => $order->created_at,
                'completed' => true,
            ]
        ];

        if ($order->status !== 'pending') {
            $timeline[] = [
                'status' => 'confirmed',
                'title' => 'Commande confirmée',
                'description' => 'Votre commande a été confirmée et va être préparée',
                'date' => $order->updated_at,
                'completed' => in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered']),
            ];
        }

        if (in_array($order->status, ['processing', 'shipped', 'delivered'])) {
            $timeline[] = [
                'status' => 'processing',
                'title' => 'Préparation en cours',
                'description' => 'Votre commande est en cours de préparation',
                'date' => $order->updated_at,
                'completed' => in_array($order->status, ['processing', 'shipped', 'delivered']),
            ];
        }

        if ($order->shipped_at) {
            $timeline[] = [
                'status' => 'shipped',
                'title' => 'Commande expédiée',
                'description' => $order->tracking_number
                    ? "Expédiée avec le numéro de suivi: {$order->tracking_number}"
                    : 'Votre commande a été expédiée',
                'date' => $order->shipped_at,
                'completed' => in_array($order->status, ['shipped', 'delivered']),
            ];
        }

        if ($order->delivered_at) {
            $timeline[] = [
                'status' => 'delivered',
                'title' => 'Commande livrée',
                'description' => 'Votre commande a été livrée avec succès',
                'date' => $order->delivered_at,
                'completed' => true,
            ];
        }

        if ($order->status === 'cancelled') {
            $timeline[] = [
                'status' => 'cancelled',
                'title' => 'Commande annulée',
                'description' => 'Votre commande a été annulée',
                'date' => $order->updated_at,
                'completed' => true,
            ];
        }

        return $timeline;
    }

    /**
     * Obtenir les ventes quotidiennes
     */
    private function getDailySales($user, $dateFrom): array
    {
        $orderItems = \App\Models\OrderItem::whereHas('product', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->whereHas('order', function ($q) use ($dateFrom) {
                $q->where('created_at', '>=', $dateFrom)
                    ->where('payment_status', 'paid');
            })
            ->with('order')
            ->get();

        return $orderItems
            ->groupBy(function ($item) {
                return $item->order->created_at->format('Y-m-d');
            })
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'sales' => $items->sum('total_price'),
                    'orders' => $items->pluck('order_id')->unique()->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Transformer une commande pour l'API
     */
    private function transformOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_badge' => $order->status_badge,
            'payment_status' => $order->payment_status,
            'payment_status_badge' => $order->payment_status_badge,
            'subtotal' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'shipping_amount' => $order->shipping_amount,
            'discount_amount' => $order->discount_amount,
            'total_amount' => $order->total_amount,
            'formatted_total' => $order->formatted_total,
            'currency' => $order->currency,
            'items_count' => $order->items_count,
            'shipping_address' => $order->shipping_address,
            'billing_address' => $order->billing_address,
            'shipping_method' => $order->shipping_method,
            'tracking_number' => $order->tracking_number,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'notes' => $order->notes,
            'admin_notes' => $order->admin_notes,
            'can_be_cancelled' => $order->can_be_cancelled,
            'can_be_shipped' => $order->can_be_shipped,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }

    /**
     * Transformer une commande avec détails complets
     */
    private function transformOrderDetail(Order $order): array
    {
        $data = $this->transformOrder($order);

        // Ajouter les articles
        $data['items'] = $order->items->map(function ($item) {
            return $this->transformOrderItem($item);
        });

        // Ajouter les paiements
        $data['payments'] = $order->payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_id' => $payment->payment_id,
                'amount' => $payment->amount,
                'formatted_amount' => $payment->formatted_amount,
                'payment_method' => $payment->payment_method,
                'method_display_name' => $payment->method_display_name,
                'status' => $payment->status,
                'status_badge' => $payment->status_badge,
                'paid_at' => $payment->paid_at,
                'created_at' => $payment->created_at,
            ];
        });

        // Ajouter la facture si elle existe
        if ($order->invoice) {
            $data['invoice'] = [
                'id' => $order->invoice->id,
                'invoice_number' => $order->invoice->invoice_number,
                'status' => $order->invoice->status,
                'pdf_url' => $order->invoice->pdf_url,
                'created_at' => $order->invoice->created_at,
            ];
        }

        return $data;
    }

    /**
     * Transformer un article de commande
     */
    private function transformOrderItem(\App\Models\OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'product_sku' => $item->product_sku,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
            'formatted_unit_price' => $item->formatted_unit_price,
            'formatted_total_price' => $item->formatted_total_price,
            'product_options' => $item->product_options,
            'product_snapshot' => $item->product_snapshot,
            'product' => $item->product ? [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'slug' => $item->product->slug,
                'featured_image_url' => $item->product->featured_image_url,
                'status' => $item->product->status,
            ] : null,
        ];
    }
}
