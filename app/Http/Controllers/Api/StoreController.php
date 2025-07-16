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
     *     path="/api/stores/{store}/manage/products",
     *     tags={"Store Management"},
     *     summary="Get store products (management)",
     *     description="Get all products for a specific store (requires access to store)",
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

        $products = $store->products()->with(['user', 'category', 'store'])->get();
        
        // Transform products using the same method as ProductController
        $transformedProducts = $products->map(function ($product) {
            return $this->transformProduct($product);
        });

        return $this->successResponse($transformedProducts, 'Store products retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{slug}/products",
     *     tags={"Store Management"},
     *     summary="Get store products by slug (public)",
     *     description="Get all active products for a specific store using store slug - public access",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Store slug"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=12, maximum=50),
     *         description="Number of products per page"
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         description="Filter by category ID"
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Search products by name or description"
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "created_at", "popularity"}, default="created_at"),
     *         description="Sort products by field"
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc"),
     *         description="Sort order"
     *     ),
     *     @OA\Parameter(
     *         name="available_only",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false),
     *         description="Show only available products"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store products retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination"),
     *             @OA\Property(property="store", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Ma Boutique"),
     *                 @OA\Property(property="slug", type="string", example="ma-boutique"),
     *                 @OA\Property(property="whatsapp_number", type="string", example="+24177123456"),
     *                 @OA\Property(property="description", type="string", example="Description de ma boutique")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function publicProducts(Request $request, Store $store): JsonResponse
    {
        // Only show active stores
        if (!$store->is_active) {
            return $this->errorResponse('Store not found', null, 404);
        }

        $perPage = min($request->get('per_page', 12), 50);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $availableOnly = $request->boolean('available_only', false);

        // Build query for store products
        $query = $store->products()
            ->published() // Only published products
            ->with(['category', 'user', 'store']);

        // Apply filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if ($availableOnly) {
            $query->available();
        }

        // Apply sorting
        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'price':
                $query->orderBy('price', $sortOrder);
                break;
            case 'popularity':
                $query->orderBy('views_count', 'desc')
                      ->orderBy('sales_count', 'desc');
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        $products = $query->paginate($perPage);

        // Transform products using the same method as other endpoints
        $transformedProducts = $products->getCollection()->map(function ($product) {
            return $this->transformProduct($product);
        });

        // Store information for response
        $storeInfo = [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'whatsapp_number' => $store->whatsapp_number,
            'description' => $store->description,
        ];

        return $this->paginatedResponse(
            $products->setCollection($transformedProducts),
            'Store products retrieved successfully',
            ['store' => $storeInfo]
        );
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

    /**
     * Transform a product for API response
     */
    private function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'price' => $product->price,
            'formatted_price' => $product->formatted_price,
            'compare_price' => $product->compare_price,
            'formatted_compare_price' => $product->formatted_compare_price,
            'discount_percentage' => $product->discount_percentage,
            'is_on_sale' => $product->is_on_sale,
            'sku' => $product->sku,
            'featured_image_url' => $product->featured_image_url,
            'condition' => $product->condition,
            'status' => $product->status,
            'is_featured' => $product->is_featured,
            'is_digital' => $product->is_digital,
            'is_in_stock' => $product->is_in_stock,
            'is_available' => $product->is_available,
            'availability_status' => $product->availability_status,
            'stock_status' => $product->stock_status,
            'track_inventory' => $product->track_inventory,
            'stock_quantity' => $product->stock_quantity,
            'allow_backorder' => $product->allow_backorder,
            'average_rating' => $product->average_rating,
            'reviews_count' => $product->reviews_count,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'store' => $product->store ? [
                'id' => $product->store->id,
                'name' => $product->store->name,
                'slug' => $product->store->slug,
                'whatsapp_number' => $product->store->whatsapp_number,
            ] : null,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }
}