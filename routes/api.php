<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;

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
Route::get('/cart/count', [CartController::class, 'count']);
Route::post('/cart/validate', [CartController::class, 'validate']);


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

    // Dashboard admin (sera ajouté dans la prochaine partie)
    // Route::get('/dashboard', [DashboardController::class, 'index']);

    // Gestion des utilisateurs (sera ajouté dans la prochaine partie)
    // Route::apiResource('users', Admin\UserController::class);

    // Gestion des produits admin (sera ajouté dans la prochaine partie)
    // Route::apiResource('products', Admin\ProductController::class);

    // Gestion des plans (sera ajouté dans la prochaine partie)
    // Route::apiResource('plans', Admin\PlanController::class);

    // Statistiques et rapports (sera ajouté dans la prochaine partie)
    // Route::get('/stats', [DashboardController::class, 'stats']);

});

/*
|--------------------------------------------------------------------------
| Webhooks et Callbacks
|--------------------------------------------------------------------------
*/

// Callback EBILLING (sans authentification)
Route::post('/ebilling/callback', function (Request $request) {
    // Cette route sera implémentée dans le PaymentController
    return response()->json(['status' => 'received']);
});

/*
|--------------------------------------------------------------------------
| Routes de développement (uniquement en local)
|--------------------------------------------------------------------------
*/



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

// Ces routes seront ajoutées dans la prochaine étape

/*
|--------------------------------------------------------------------------
| Routes Protégées (Authentification requise)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Ces routes seront ajoutées dans les prochaines étapes
});

/*
|--------------------------------------------------------------------------
| Routes Administrateur
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Ces routes seront ajoutées dans les prochaines étapes
});
