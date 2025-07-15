<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/products",
     *     summary="Get all products (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         required=false,
     *         @OA\Schema(type="string", example="tshirt")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "draft", "archived"})
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="stock_status",
     *         in="query",
     *         description="Filter by stock status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"in_stock", "low_stock", "out_of_stock"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *                 @OA\Property(property="pagination", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');
        $categoryId = $request->input('category_id');
        $userId = $request->input('user_id');
        $stockStatus = $request->input('stock_status');

        $query = Product::with(['user', 'category', 'orderItems']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($stockStatus) {
            $query->where(function ($q) use ($stockStatus) {
                switch ($stockStatus) {
                    case 'in_stock':
                        $q->where('track_inventory', false)
                          ->orWhere('stock_quantity', '>', 'min_stock_level');
                        break;
                    case 'low_stock':
                        $q->where('track_inventory', true)
                          ->whereColumn('stock_quantity', '<=', 'min_stock_level')
                          ->where('stock_quantity', '>', 0);
                        break;
                    case 'out_of_stock':
                        $q->where('track_inventory', true)
                          ->where('stock_quantity', '<=', 0);
                        break;
                }
            });
        }

        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/products/{id}",
     *     summary="Get specific product (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['user', 'category', 'orderItems', 'cartItems']);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/products",
     *     summary="Create new product (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="T-shirt Premium"),
     *             @OA\Property(property="description", type="string", example="Un t-shirt confortable"),
     *             @OA\Property(property="price", type="number", example=15000),
     *             @OA\Property(property="stock_quantity", type="integer", example=50),
     *             @OA\Property(property="status", type="string", enum={"active", "draft", "archived"}, example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'short_description' => 'sometimes|string|max:500',
            'sku' => 'sometimes|string|max:100|unique:products',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'track_inventory' => 'sometimes|boolean',
            'stock_quantity' => 'sometimes|integer|min:0',
            'min_stock_level' => 'sometimes|integer|min:0',
            'allow_backorder' => 'sometimes|boolean',
            'weight' => 'sometimes|numeric|min:0',
            'dimensions' => 'sometimes|array',
            'condition' => 'sometimes|string|in:new,used,refurbished',
            'featured_image' => 'sometimes|string',
            'gallery_images' => 'sometimes|array',
            'video_url' => 'sometimes|url',
            'meta_title' => 'sometimes|string|max:255',
            'meta_description' => 'sometimes|string|max:500',
            'tags' => 'sometimes|array',
            'attributes' => 'sometimes|array',
            'variants' => 'sometimes|array',
            'status' => 'sometimes|string|in:active,draft,archived',
            'is_featured' => 'sometimes|boolean',
            'is_digital' => 'sometimes|boolean',
            'published_at' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $productData = $request->all();
        $productData['currency'] = $productData['currency'] ?? 'XAF';

        $product = Product::create($productData);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load(['user', 'category'])
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/products/{id}",
     *     summary="Update product (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="T-shirt Premium Updated"),
     *             @OA\Property(property="description", type="string", example="Description mise à jour"),
     *             @OA\Property(property="price", type="number", example=16000),
     *             @OA\Property(property="stock_quantity", type="integer", example=45),
     *             @OA\Property(property="status", type="string", enum={"active", "draft", "archived"}, example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|exists:users,id',
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'short_description' => 'sometimes|string|max:500',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $product->id,
            'price' => 'sometimes|numeric|min:0',
            'compare_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'track_inventory' => 'sometimes|boolean',
            'stock_quantity' => 'sometimes|integer|min:0',
            'min_stock_level' => 'sometimes|integer|min:0',
            'allow_backorder' => 'sometimes|boolean',
            'weight' => 'sometimes|numeric|min:0',
            'dimensions' => 'sometimes|array',
            'condition' => 'sometimes|string|in:new,used,refurbished',
            'featured_image' => 'sometimes|string',
            'gallery_images' => 'sometimes|array',
            'video_url' => 'sometimes|url',
            'meta_title' => 'sometimes|string|max:255',
            'meta_description' => 'sometimes|string|max:500',
            'tags' => 'sometimes|array',
            'attributes' => 'sometimes|array',
            'variants' => 'sometimes|array',
            'status' => 'sometimes|string|in:active,draft,archived',
            'is_featured' => 'sometimes|boolean',
            'is_digital' => 'sometimes|boolean',
            'published_at' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->fresh()->load(['user', 'category'])
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/products/{id}",
     *     summary="Delete product (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product deleted successfully")
     *         )
     *     )
     * )
     */
    public function destroy(Product $product): JsonResponse
    {
        // Delete featured image if exists
        if ($product->featured_image) {
            Storage::disk('public')->delete($product->featured_image);
        }

        // Delete gallery images if exist
        if ($product->gallery_images) {
            foreach ($product->gallery_images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/products/{id}/toggle-featured",
     *     summary="Toggle product featured status (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product featured status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product featured status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function toggleFeatured(Product $product): JsonResponse
    {
        $product->update(['is_featured' => !$product->is_featured]);

        return response()->json([
            'success' => true,
            'message' => 'Product featured status updated successfully',
            'data' => $product->fresh()
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/products/{id}/publish",
     *     summary="Publish product (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product published successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product published successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function publish(Product $product): JsonResponse
    {
        $product->publish();

        return response()->json([
            'success' => true,
            'message' => 'Product published successfully',
            'data' => $product->fresh()
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/products/{id}/unpublish",
     *     summary="Unpublish product (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product unpublished successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product unpublished successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function unpublish(Product $product): JsonResponse
    {
        $product->unpublish();

        return response()->json([
            'success' => true,
            'message' => 'Product unpublished successfully',
            'data' => $product->fresh()
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/products/{id}/stock",
     *     summary="Update product stock (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="action", type="string", enum={"add", "remove", "set"}, example="add"),
     *             @OA\Property(property="quantity", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:add,remove,set',
            'quantity' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $action = $request->input('action');
        $quantity = $request->input('quantity');

        switch ($action) {
            case 'add':
                $product->incrementStock($quantity);
                break;
            case 'remove':
                if (!$product->decrementStock($quantity)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock'
                    ], 400);
                }
                break;
            case 'set':
                $product->update(['stock_quantity' => $quantity]);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully',
            'data' => $product->fresh()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/products/statistics",
     *     summary="Get product statistics (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_products", type="integer", example=2500),
     *                 @OA\Property(property="active_products", type="integer", example=2100),
     *                 @OA\Property(property="draft_products", type="integer", example=350),
     *                 @OA\Property(property="archived_products", type="integer", example=50),
     *                 @OA\Property(property="featured_products", type="integer", example=150),
     *                 @OA\Property(property="low_stock_products", type="integer", example=45),
     *                 @OA\Property(property="out_of_stock_products", type="integer", example=12),
     *                 @OA\Property(property="average_price", type="number", example=15000.75),
     *                 @OA\Property(property="total_sales", type="integer", example=8500)
     *             )
     *         )
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $draftProducts = Product::where('status', 'draft')->count();
        $archivedProducts = Product::where('status', 'archived')->count();
        $featuredProducts = Product::where('is_featured', true)->count();
        $lowStockProducts = Product::where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->where('stock_quantity', '>', 0)
            ->count();
        $outOfStockProducts = Product::where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->count();
        $averagePrice = Product::where('status', 'active')->avg('price');
        $totalSales = Product::sum('sales_count');

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'draft_products' => $draftProducts,
                'archived_products' => $archivedProducts,
                'featured_products' => $featuredProducts,
                'low_stock_products' => $lowStockProducts,
                'out_of_stock_products' => $outOfStockProducts,
                'average_price' => round($averagePrice, 2),
                'total_sales' => $totalSales
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/products/bulk-action",
     *     summary="Perform bulk actions on products (Admin)",
     *     tags={"Admin - Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="action", type="string", enum={"publish", "unpublish", "feature", "unfeature", "delete", "archive"}, example="publish"),
     *             @OA\Property(property="product_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3, 4, 5})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk action performed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bulk action performed successfully"),
     *             @OA\Property(property="affected_count", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:publish,unpublish,feature,unfeature,delete,archive',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $action = $request->input('action');
        $productIds = $request->input('product_ids');
        $products = Product::whereIn('id', $productIds);

        $affectedCount = 0;

        switch ($action) {
            case 'publish':
                $affectedCount = $products->update([
                    'status' => 'active',
                    'published_at' => now()
                ]);
                break;
            case 'unpublish':
                $affectedCount = $products->update([
                    'status' => 'draft',
                    'published_at' => null
                ]);
                break;
            case 'feature':
                $affectedCount = $products->update(['is_featured' => true]);
                break;
            case 'unfeature':
                $affectedCount = $products->update(['is_featured' => false]);
                break;
            case 'archive':
                $affectedCount = $products->update(['status' => 'archived']);
                break;
            case 'delete':
                $productsToDelete = $products->get();
                foreach ($productsToDelete as $product) {
                    // Delete images
                    if ($product->featured_image) {
                        Storage::disk('public')->delete($product->featured_image);
                    }
                    if ($product->gallery_images) {
                        foreach ($product->gallery_images as $image) {
                            Storage::disk('public')->delete($image);
                        }
                    }
                }
                $affectedCount = $products->delete();
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk action performed successfully',
            'affected_count' => $affectedCount
        ]);
    }
}
