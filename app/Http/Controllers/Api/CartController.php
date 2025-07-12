<?php
// app/Http/Controllers/Api/CartController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Services\CartService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    use ApiResponseTrait;

    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @OA\Get(
     *     path="/api/cart",
     *     tags={"Panier"},
     *     summary="Contenu du panier",
     *     description="Récupérer le contenu du panier de l'utilisateur",
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="ID de session pour les utilisateurs non connectés"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contenu du panier récupéré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Panier récupéré avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CartItem")),
     *                 @OA\Property(property="totals", ref="#/components/schemas/CartTotals")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->get('session_id');

        // Si l'utilisateur est connecté et qu'il y a un session_id, fusionner les paniers
        if ($user && $sessionId) {
            $this->cartService->mergeCart($user, $sessionId);
        }

        $cartItems = $this->cartService->getCartItems($user, $sessionId);
        $totals = $this->cartService->calculateCartTotals($cartItems);

        $transformedItems = $cartItems->map(function ($item) {
            return $this->transformCartItem($item);
        });

        return $this->successResponse([
            'items' => $transformedItems,
            'totals' => $totals,
        ], 'Panier récupéré avec succès');
    }

    /**
     * @OA\Post(
     *     path="/api/cart",
     *     tags={"Panier"},
     *     summary="Ajouter au panier",
     *     description="Ajouter un produit au panier",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id","quantity"},
     *             @OA\Property(property="product_id", type="integer", example=1),
     *             @OA\Property(property="quantity", type="integer", example=2),
     *             @OA\Property(property="session_id", type="string", example="cart_session_123"),
     *             @OA\Property(property="options", type="object",
     *                 @OA\Property(property="color", type="string", example="rouge"),
     *                 @OA\Property(property="size", type="string", example="M")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Produit ajouté au panier",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produit ajouté au panier"),
     *             @OA\Property(property="data", ref="#/components/schemas/CartItem")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Stock insuffisant",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Stock insuffisant"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Produit non trouvé",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999',
            'session_id' => 'nullable|string|max:255',
            'options' => 'nullable|array',
        ]);

        try {
            $product = Product::findOrFail($request->product_id);

            // Vérifier que le produit est disponible
            if ($product->status !== 'active') {
                return $this->errorResponse('Ce produit n\'est pas disponible', null, 400);
            }

            $user = $request->user();
            $sessionId = $request->get('session_id');

            $cartItem = $this->cartService->addToCart(
                $product,
                $request->quantity,
                $request->get('options', []),
                $user,
                $sessionId
            );

            return $this->createdResponse(
                $this->transformCartItem($cartItem),
                'Produit ajouté au panier'
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Stock insuffisant')) {
                return $this->errorResponse($e->getMessage(), null, 400);
            }

            return $this->errorResponse('Erreur lors de l\'ajout au panier', null, 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/cart/clear",
     *     tags={"Panier"},
     *     summary="Vider le panier",
     *     description="Supprimer tous les articles du panier",
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="ID de session pour les utilisateurs non connectés"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Panier vidé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Panier vidé avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="deleted_items", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $deletedCount = $this->cartService->clearCart(
                $request->user(),
                $request->get('session_id')
            );

            return $this->successResponse([
                'deleted_items' => $deletedCount
            ], 'Panier vidé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du vidage du panier', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/cart/validate-cart",
     *     tags={"Panier"},
     *     summary="Valider le panier",
     *     description="Vérifier la disponibilité et les prix des articles avant commande",
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="ID de session pour les utilisateurs non connectés"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Validation réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Panier valide"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="is_valid", type="boolean", example=true),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="totals", ref="#/components/schemas/CartTotals")
     *             )
     *         )
     *     )
     * )
     */
    public function validateCart(Request $request): JsonResponse  // ← Renommé de validate() à validateCart()
    {
        try {
            $user = $request->user();
            $sessionId = $request->get('session_id');

            $cartItems = $this->cartService->getCartItems($user, $sessionId);

            if ($cartItems->isEmpty()) {
                return $this->errorResponse('Le panier est vide', null, 400);
            }

            $errors = $this->cartService->validateCart($cartItems);
            $totals = $this->cartService->calculateCartTotals($cartItems);

            $isValid = empty($errors);

            return $this->successResponse([
                'is_valid' => $isValid,
                'errors' => $errors,
                'totals' => $totals,
                'items_count' => $cartItems->count(),
            ], $isValid ? 'Panier valide' : 'Problèmes détectés dans le panier');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la validation', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/cart/count",
     *     tags={"Panier"},
     *     summary="Nombre d'articles dans le panier",
     *     description="Récupérer le nombre total d'articles dans le panier",
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="ID de session pour les utilisateurs non connectés"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Nombre d'articles récupéré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Nombre d'articles récupéré"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="items_count", type="integer", example=5),
     *                 @OA\Property(property="unique_items", type="integer", example=3),
     *                 @OA\Property(property="total_amount", type="number", example=75000)
     *             )
     *         )
     *     )
     * )
     */
    public function count(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $sessionId = $request->get('session_id');

            $cartItems = $this->cartService->getCartItems($user, $sessionId);
            $totals = $this->cartService->calculateCartTotals($cartItems);

            return $this->successResponse([
                'items_count' => $totals['items_count'],
                'unique_items' => $cartItems->count(),
                'total_amount' => $totals['total'],
            ], 'Nombre d\'articles récupéré');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du calcul', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/cart/apply-coupon",
     *     tags={"Panier"},
     *     summary="Appliquer un code promo",
     *     description="Appliquer un code de réduction au panier",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"coupon_code"},
     *             @OA\Property(property="coupon_code", type="string", example="WELCOME10"),
     *             @OA\Property(property="session_id", type="string", example="cart_session_123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code promo appliqué",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Code promo appliqué"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="discount_amount", type="number", example=7500),
     *                 @OA\Property(property="discount_percentage", type="number", example=10),
     *                 @OA\Property(property="new_total", type="number", example=67500)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Code promo invalide",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Code promo invalide ou expiré"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code' => 'required|string|max:50',
            'session_id' => 'nullable|string',
        ]);

        // Pour le moment, on simule un système de coupons simple
        // Dans une implémentation complète, il faudrait une table coupons
        $couponCode = strtoupper($request->coupon_code);

        $validCoupons = [
            'WELCOME10' => ['type' => 'percentage', 'value' => 10, 'min_amount' => 20000],
            'SAVE5000' => ['type' => 'fixed', 'value' => 5000, 'min_amount' => 30000],
            'FIRSTORDER' => ['type' => 'percentage', 'value' => 15, 'min_amount' => 0],
        ];

        if (!isset($validCoupons[$couponCode])) {
            return $this->errorResponse('Code promo invalide', null, 400);
        }

        try {
            $user = $request->user();
            $sessionId = $request->get('session_id');

            $cartItems = $this->cartService->getCartItems($user, $sessionId);
            $totals = $this->cartService->calculateCartTotals($cartItems);

            $coupon = $validCoupons[$couponCode];

            // Vérifier le montant minimum
            if ($totals['subtotal'] < $coupon['min_amount']) {
                return $this->errorResponse(
                    'Montant minimum requis : ' . number_format($coupon['min_amount'], 0, ',', ' ') . ' XAF',
                    null,
                    400
                );
            }

            // Calculer la remise
            if ($coupon['type'] === 'percentage') {
                $discountAmount = ($totals['subtotal'] * $coupon['value']) / 100;
            } else {
                $discountAmount = $coupon['value'];
            }

            $newTotal = max(0, $totals['total'] - $discountAmount);

            return $this->successResponse([
                'coupon_code' => $couponCode,
                'discount_amount' => $discountAmount,
                'discount_percentage' => $coupon['type'] === 'percentage' ? $coupon['value'] : null,
                'original_total' => $totals['total'],
                'new_total' => $newTotal,
                'savings' => $discountAmount,
            ], 'Code promo appliqué avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'application du code promo', null, 500);
        }
    }

    /**
     * Vérifier si l'utilisateur peut accéder à cet article du panier
     */
    private function canAccessCartItem(Cart $cart, $user, ?string $sessionId): bool
    {
        if ($user && $cart->user_id === $user->id) {
            return true;
        }

        if (!$user && $sessionId && $cart->session_id === $sessionId) {
            return true;
        }

        return false;
    }

    /**
     * Transformer un article du panier pour l'API
     */
    private function transformCartItem(Cart $cartItem): array
    {
        $product = $cartItem->product;

        return [
            'id' => $cartItem->id,
            'quantity' => $cartItem->quantity,
            'unit_price' => $cartItem->unit_price,
            'total_price' => $cartItem->total_price,
            'formatted_total_price' => $cartItem->formatted_total_price,
            'product_options' => $cartItem->product_options,
            'added_at' => $cartItem->created_at,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => $product->price,
                'formatted_price' => $product->formatted_price,
                'featured_image_url' => $product->featured_image_url,
                'is_in_stock' => $product->is_in_stock,
                'stock_status' => $product->stock_status,
                'stock_quantity' => $product->track_inventory ? $product->stock_quantity : null,
                'status' => $product->status,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
            ],
        ];
    }
}
