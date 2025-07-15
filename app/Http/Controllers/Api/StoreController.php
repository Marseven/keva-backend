<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @OA\Tag(
 *     name="Store Management",
 *     description="API endpoints for store management with role-based access control"
 * )
 */
class StoreController extends Controller
{
    use ApiResponseTrait, AuthorizesRequests;

    /**
     * @OA\Get(
     *     path="/api/stores",
     *     tags={"Store Management"},
     *     summary="List user's stores",
     *     description="Get all stores where the user has access",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Stores retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stores retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Store"))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Store::class);

        $user = $request->user();
        
        if ($user->is_admin) {
            $stores = Store::with(['user', 'products', 'orders'])->get();
        } else {
            $stores = $user->managedStores()->with(['products', 'orders'])->get();
        }

        return $this->successResponse($stores, 'Stores retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{store}",
     *     tags={"Store Management"},
     *     summary="Get store details",
     *     description="Get detailed information about a specific store",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store details retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Store")
     *         )
     *     )
     * )
     */
    public function show(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $store->load(['user', 'products', 'orders', 'users']);

        return $this->successResponse($store, 'Store details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/stores",
     *     tags={"Store Management"},
     *     summary="Create a new store",
     *     description="Create a new store and assign the user as owner",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "whatsapp_number"},
     *             @OA\Property(property="name", type="string", example="My Store"),
     *             @OA\Property(property="whatsapp_number", type="string", example="+241123456789"),
     *             @OA\Property(property="description", type="string", example="Store description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Store created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Store")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Store::class);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'whatsapp_number' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
        ]);

        $store = Store::create([
            'name' => $validatedData['name'],
            'whatsapp_number' => $validatedData['whatsapp_number'],
            'description' => $validatedData['description'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        // Add the user as owner of the store
        $store->addUser($request->user(), 'owner');

        return $this->createdResponse($store, 'Store created successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/stores/{store}",
     *     tags={"Store Management"},
     *     summary="Update store",
     *     description="Update store information (requires owner or admin role)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Store Name"),
     *             @OA\Property(property="whatsapp_number", type="string", example="+241123456789"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Store")
     *         )
     *     )
     * )
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'whatsapp_number' => 'sometimes|required|string|max:20',
            'description' => 'nullable|string|max:1000',
        ]);

        $store->update($validatedData);

        return $this->updatedResponse($store, 'Store updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/stores/{store}",
     *     tags={"Store Management"},
     *     summary="Delete store",
     *     description="Delete a store (requires owner role)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function destroy(Store $store): JsonResponse
    {
        $this->authorize('delete', $store);

        $store->delete();

        return $this->deletedResponse('Store deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{store}/products",
     *     tags={"Store Management"},
     *     summary="Get store products",
     *     description="Get all products for a specific store",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store products retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product"))
     *         )
     *     )
     * )
     */
    public function products(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $products = $store->products()->with(['user', 'category'])->get();

        return $this->successResponse($products, 'Store products retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{store}/orders",
     *     tags={"Store Management"},
     *     summary="Get store orders",
     *     description="Get all orders for a specific store (requires manage permission)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store orders retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Order"))
     *         )
     *     )
     * )
     */
    public function orders(Request $request, Store $store): JsonResponse
    {
        $this->authorize('manage-store-orders', $store);

        $orders = $store->orders()
            ->with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($orders, 'Store orders retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/stores/{store}/users",
     *     tags={"Store Management"},
     *     summary="Add user to store",
     *     description="Add a user to the store with a specific role (requires admin permission)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "role"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="role", type="string", enum={"admin", "manager", "staff"}, example="manager"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"manage_products", "view_orders"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User added to store successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User added to store successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function addUser(Request $request, Store $store): JsonResponse
    {
        $this->authorize('addUser', $store);

        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,manager,staff',
            'permissions' => 'nullable|array',
        ]);

        $user = \App\Models\User::find($validatedData['user_id']);
        $store->addUser($user, $validatedData['role'], $validatedData['permissions'] ?? []);

        return $this->createdResponse(null, 'User added to store successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{store}/analytics",
     *     tags={"Store Management"},
     *     summary="Get store analytics",
     *     description="Get analytics data for a specific store (requires manage permission)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store ID or slug"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store analytics retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_products", type="integer", example=25),
     *                 @OA\Property(property="total_orders", type="integer", example=150),
     *                 @OA\Property(property="total_revenue", type="number", example=750000),
     *                 @OA\Property(property="pending_orders", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function analytics(Store $store): JsonResponse
    {
        $this->authorize('view-store-analytics', $store);

        $analytics = [
            'total_products' => $store->products()->count(),
            'active_products' => $store->activeProducts()->count(),
            'total_orders' => $store->orders()->count(),
            'pending_orders' => $store->orders()->where('status', 'pending')->count(),
            'total_revenue' => $store->orders()
                ->where('payment_status', 'paid')
                ->sum('total_amount'),
            'monthly_revenue' => $store->orders()
                ->where('payment_status', 'paid')
                ->whereMonth('created_at', now()->month)
                ->sum('total_amount'),
            'store_staff_count' => $store->users()->count(),
        ];

        return $this->successResponse($analytics, 'Store analytics retrieved successfully');
    }
}