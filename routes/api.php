<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\EbillingCallbackController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toutes les routes de l'API KEVA
| Préfixe automatique : /api
|
*/

/**
 * @OA\Get(
 *     path="/api/health",
 *     tags={"Système"},
 *     summary="Vérification de l'état de l'API",
 *     description="Endpoint pour vérifier que l'API fonctionne correctement",
 *     @OA\Response(
 *         response=200,
 *         description="API fonctionnelle",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="API KEVA opérationnelle"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="version", type="string", example="1.0.0"),
 *                 @OA\Property(property="timestamp", type="string", example="2025-01-15T10:30:00Z"),
 *                 @OA\Property(property="environment", type="string", example="local")
 *             )
 *         )
 *     )
 * )
 */
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API KEVA opérationnelle',
        'data' => [
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'database' => 'Connected',
        ]
    ]);
});

/**
 * @OA\Get(
 *     path="/api/business-types",
 *     tags={"Utilitaires"},
 *     summary="Liste des types d'entreprise",
 *     description="Récupère la liste des types d'entreprise disponibles",
 *     @OA\Response(
 *         response=200,
 *         description="Liste des types d'entreprise",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Types d'entreprise récupérés"),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(type="string")
 *             )
 *         )
 *     )
 * )
 */
Route::get('/business-types', function () {
    $businessTypes = [
        'Vêtements et mode',
        'Alimentation et boissons',
        'Technologie et électronique',
        'Beauté et cosmétiques',
        'Maison et décoration',
        'Sport et loisirs',
        'Automobile',
        'Santé et bien-être',
        'Éducation et formation',
        'Services professionnels',
        'Arts et artisanat',
        'Tourisme et voyage',
        'Immobilier',
        'Finance et assurance',
        'Agriculture',
        'Autre'
    ];

    return response()->json([
        'success' => true,
        'message' => 'Types d\'entreprise récupérés',
        'data' => $businessTypes
    ]);
});

/**
 * @OA\Get(
 *     path="/api/cities",
 *     tags={"Utilitaires"},
 *     summary="Liste des villes du Gabon",
 *     description="Récupère la liste des villes disponibles",
 *     @OA\Response(
 *         response=200,
 *         description="Liste des villes",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Villes récupérées"),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(type="string")
 *             )
 *         )
 *     )
 * )
 */
Route::get('/cities', function () {
    $cities = [
        'Libreville',
        'Port-Gentil',
        'Franceville',
        'Oyem',
        'Moanda',
        'Mouila',
        'Lambaréné',
        'Tchibanga',
        'Koulamoutou',
        'Makokou',
        'Bitam',
        'Gamba',
        'Ndjolé',
        'Mitzic',
        'Médouneu'
    ];

    return response()->json([
        'success' => true,
        'message' => 'Villes récupérées',
        'data' => $cities
    ]);
});

/*
|--------------------------------------------------------------------------
| Routes d'Authentification (Publiques)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Routes publiques
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Routes protégées par authentification
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

/*
|--------------------------------------------------------------------------
| Routes Publiques (Sans authentification)
|--------------------------------------------------------------------------
*/

Route::get('/plans', [PlanController::class, 'index']);
Route::get('/plans/compare', [PlanController::class, 'compare']);
Route::get('/plans/{slug}', [PlanController::class, 'show']);

// Catégories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/tree', [CategoryController::class, 'tree']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/products', [CategoryController::class, 'products']);

// Produits publics
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Panier public (pour les utilisateurs non connectés)
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::put('/cart/{cart}', [CartController::class, 'update']);
Route::delete('/cart/{cart}', [CartController::class, 'destroy']);
Route::get('/cart/count', [CartController::class, 'count']);
Route::post('/cart/validate-cart', [CartController::class, 'validateCart']);
Route::delete('/cart/clear', [CartController::class, 'clear']);


/*
|--------------------------------------------------------------------------
| Routes Protégées (Authentification requise)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'active_user'])->group(function () {

    // Profil utilisateur
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::get('/user/tokens', [AuthController::class, 'getUserTokens']);
    Route::delete('/user/tokens/{tokenId}', [AuthController::class, 'revokeToken']);

    // Routes utilisateur
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/avatar', [UserController::class, 'uploadAvatar']);
    Route::put('/user/password', [UserController::class, 'changePassword']);
    Route::get('/user/dashboard', [UserController::class, 'dashboard']);
    Route::delete('/user/account', [UserController::class, 'deleteAccount']);

    // Routes des abonnements
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::put('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
    Route::put('/subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume']);

    // Routes des paiements
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::put('/payments/{payment}/cancel', [PaymentController::class, 'cancel']);

    // Routes des factures
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download']);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);

    // Routes avec vérification d'abonnement
    Route::middleware('subscription')->group(function () {

        // Gestion des produits
        Route::get('/my-products', [ProductController::class, 'myProducts']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate']);
        Route::post('/products/{product}/toggle-featured', [ProductController::class, 'toggleFeatured']);
        Route::put('/products/{product}/stock', [ProductController::class, 'updateStock']);


        // Gestion des commandes
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/stats', [OrderController::class, 'stats']);
        Route::get('/orders/sales', [OrderController::class, 'sales']);
        Route::get('/orders/sales/stats', [OrderController::class, 'salesStats']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::put('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::put('/orders/{order}/notes', [OrderController::class, 'addNotes']);
        Route::get('/orders/{order}/track', [OrderController::class, 'track']);
        Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder']);


        // Gestion des produits (sera ajouté dans la prochaine partie)
        // Route::apiResource('products', ProductController::class);

        // Gestion du panier (sera ajouté dans la prochaine partie)
        // Route::apiResource('cart', CartController::class, ['only' => ['index', 'store', 'update', 'destroy']]);

        // Gestion des commandes (sera ajouté dans la prochaine partie)
        // Route::apiResource('orders', OrderController::class, ['only' => ['index', 'store', 'show']]);

        // Gestion des paiements (sera ajouté dans la prochaine partie)
        // Route::post('/payments', [PaymentController::class, 'create']);
        // Route::get('/payments', [PaymentController::class, 'index']);

        // Gestion des factures (sera ajouté dans la prochaine partie)
        // Route::get('/invoices', [InvoiceController::class, 'index']);
        // Route::get('/invoices/{id}', [InvoiceController::class, 'show']);

    });

    // Gestion des abonnements (sera ajouté dans la prochaine partie)
    // Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    // Route::post('/subscriptions', [SubscriptionController::class, 'subscribe']);

});

/*
|--------------------------------------------------------------------------
| Routes Administrateur
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // Dashboard admin
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);
    Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);

    // Gestion des utilisateurs
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);
    Route::put('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::get('/users/statistics', [AdminUserController::class, 'statistics']);

    // Gestion des produits admin
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{product}', [AdminProductController::class, 'show']);
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::patch('/products/{product}/toggle-featured', [AdminProductController::class, 'toggleFeatured']);
    Route::patch('/products/{product}/publish', [AdminProductController::class, 'publish']);
    Route::patch('/products/{product}/unpublish', [AdminProductController::class, 'unpublish']);
    Route::put('/products/{product}/stock', [AdminProductController::class, 'updateStock']);
    Route::get('/products/statistics', [AdminProductController::class, 'statistics']);
    Route::post('/products/bulk-action', [AdminProductController::class, 'bulkAction']);

    // Gestion des commandes admin
    Route::get('/orders', [OrderController::class, 'adminIndex']);
    Route::get('/orders/{order}', [OrderController::class, 'adminShow']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::put('/orders/{order}/cancel', [OrderController::class, 'adminCancel']);
    Route::get('/orders/statistics', [OrderController::class, 'statistics']);

    // Gestion des paiements admin
    Route::get('/payments', [PaymentController::class, 'adminIndex']);
    Route::get('/payments/{payment}', [PaymentController::class, 'adminShow']);
    Route::put('/payments/{payment}/refund', [PaymentController::class, 'refund']);
    Route::get('/payments/statistics', [PaymentController::class, 'statistics']);

    // Gestion des abonnements admin
    Route::get('/subscriptions', [SubscriptionController::class, 'adminIndex']);
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'adminShow']);
    Route::put('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'adminCancel']);
    Route::get('/subscriptions/statistics', [SubscriptionController::class, 'statistics']);

    // Gestion des factures admin
    Route::get('/invoices', [InvoiceController::class, 'adminIndex']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'adminShow']);
    Route::post('/invoices/{invoice}/regenerate', [InvoiceController::class, 'regenerate']);
    Route::get('/invoices/statistics', [InvoiceController::class, 'statistics']);

    // Gestion des plans admin
    Route::get('/plans', [PlanController::class, 'adminIndex']);
    Route::post('/plans', [PlanController::class, 'store']);
    Route::get('/plans/{plan}', [PlanController::class, 'adminShow']);
    Route::put('/plans/{plan}', [PlanController::class, 'update']);
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy']);
    Route::patch('/plans/{plan}/toggle-active', [PlanController::class, 'toggleActive']);

    // Gestion des catégories admin
    Route::get('/categories', [CategoryController::class, 'adminIndex']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{category}', [CategoryController::class, 'adminShow']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    Route::patch('/categories/{category}/toggle-active', [CategoryController::class, 'toggleActive']);

});

/*
|--------------------------------------------------------------------------
| Webhooks et Callbacks
|--------------------------------------------------------------------------
*/

// Callback EBILLING (sans authentification)
Route::post('/ebilling/callback', [EbillingCallbackController::class, 'handle']);
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
Route::post('/subscriptions/webhook', [SubscriptionController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| Routes de développement (uniquement en local)
|--------------------------------------------------------------------------
*/



