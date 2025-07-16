<?php
// app/Http/Controllers/Api/ProductController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    use ApiResponseTrait;

    private ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * @OA\Get(
     *     path="/api/products",
     *     tags={"Produits"},
     *     summary="Lister les produits",
     *     description="Récupérer la liste des produits avec filtres et pagination",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Recherche textuelle dans le nom, description et SKU"
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         description="Filtrer par catégorie"
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="number"),
     *         description="Prix minimum"
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="number"),
     *         description="Prix maximum"
     *     ),
     *     @OA\Parameter(
     *         name="in_stock_only",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         description="Afficher uniquement les produits en stock"
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         description="Afficher uniquement les produits en vedette"
     *     ),
     *     @OA\Parameter(
     *         name="condition",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"new","used","refurbished"}),
     *         description="Filtrer par condition"
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name","price","popularity","rating","created_at"}),
     *         description="Trier par"
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc"),
     *         description="Ordre de tri"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=12, maximum=50),
     *         description="Nombre de produits par page"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des produits récupérée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produits récupérés avec succès"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'category_id',
            'min_price',
            'max_price',
            'in_stock_only',
            'featured',
            'condition',
            'sort_by',
            'sort_order'
        ]);

        $perPage = min($request->get('per_page', 12), 50);
        $products = $this->productService->searchProducts($filters, $perPage);

        // Transformer les données
        $productsData = $products->getCollection()->map(function ($product) {
            return $this->transformProduct($product);
        });

        return $this->paginatedResponse(
            $products->setCollection($productsData),
            'Produits récupérés avec succès'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     tags={"Produits"},
     *     summary="Créer un produit",
     *     description="Créer un nouveau produit avec images",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name","category_id","description","price"},
     *                 @OA\Property(property="name", type="string", example="T-shirt Premium"),
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="description", type="string", example="T-shirt de qualité premium en coton bio"),
     *                 @OA\Property(property="short_description", type="string", example="T-shirt premium coton bio"),
     *                 @OA\Property(property="price", type="number", example=25000),
     *                 @OA\Property(property="compare_price", type="number", example=35000),
     *                 @OA\Property(property="cost_price", type="number", example=15000),
     *                 @OA\Property(property="sku", type="string", example="TSH-001"),
     *                 @OA\Property(property="track_inventory", type="boolean", example=true),
     *                 @OA\Property(property="stock_quantity", type="integer", example=100),
     *                 @OA\Property(property="min_stock_level", type="integer", example=10),
     *                 @OA\Property(property="allow_backorder", type="boolean", example=false),
     *                 @OA\Property(property="weight", type="number", example=0.2),
     *                 @OA\Property(property="condition", type="string", enum={"new","used","refurbished"}, example="new"),
     *                 @OA\Property(property="featured_image", type="string", format="binary"),
     *                 @OA\Property(property="gallery_images[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="video_url", type="string", example="https://youtube.com/watch?v=..."),
     *                 @OA\Property(property="meta_title", type="string", example="T-shirt Premium - Boutique"),
     *                 @OA\Property(property="meta_description", type="string", example="Découvrez notre t-shirt premium"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"t-shirt","premium","coton"}),
     *                 @OA\Property(property="status", type="string", enum={"draft","active","inactive","archived"}, example="active"),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="is_digital", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Produit créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produit créé avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=402,
     *         description="Limite de produits atteinte",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Limite de produits atteinte"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function store(ProductRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            $product = $this->productService->createProduct($user, $data);

            return $this->createdResponse(
                $this->transformProduct($product),
                'Produit créé avec succès'
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Limite de produits')) {
                return $this->errorResponse($e->getMessage(), null, 402);
            }

            return $this->errorResponse('Erreur lors de la création du produit', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     tags={"Produits"},
     *     summary="Détails d'un produit",
     *     description="Récupérer les détails complets d'un produit",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du produit récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produit récupéré avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Produit non trouvé",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function show(Product $product): JsonResponse
    {
        // Incrémenter le compteur de vues
        $product->incrementViews();

        // Charger les relations
        $product->load(['category', 'user', 'images', 'store']);

        return $this->successResponse(
            $this->transformProductDetail($product),
            'Produit récupéré avec succès'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     tags={"Produits"},
     *     summary="Mettre à jour un produit",
     *     description="Modifier un produit existant",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="T-shirt Premium Modifié"),
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="description", type="string", example="Description modifiée"),
     *                 @OA\Property(property="price", type="number", example=27000),
     *                 @OA\Property(property="stock_quantity", type="integer", example=150),
     *                 @OA\Property(property="featured_image", type="string", format="binary"),
     *                 @OA\Property(property="status", type="string", enum={"draft","active","inactive","archived"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Produit mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produit mis à jour avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé à modifier ce produit",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        // Vérifier que l'utilisateur est propriétaire du produit ou admin
        if ($product->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'êtes pas autorisé à modifier ce produit');
        }

        try {
            $data = $request->validated();
            $updatedProduct = $this->productService->updateProduct($product, $data);

            return $this->updatedResponse(
                $this->transformProduct($updatedProduct),
                'Produit mis à jour avec succès'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du produit', null, 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     tags={"Produits"},
     *     summary="Supprimer un produit",
     *     description="Supprimer un produit et toutes ses images",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Produit supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produit supprimé avec succès"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Non autorisé à supprimer ce produit",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        // Vérifier que l'utilisateur est propriétaire du produit ou admin
        if ($product->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'êtes pas autorisé à supprimer ce produit');
        }

        try {
            $this->productService->deleteProduct($product);

            return $this->deletedResponse('Produit supprimé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du produit', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/products/{id}/duplicate",
     *     tags={"Produits"},
     *     summary="Dupliquer un produit",
     *     description="Créer une copie d'un produit existant",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit à dupliquer"
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Produit dupliqué avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produit dupliqué avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function duplicate(Request $request, Product $product): JsonResponse
    {
        try {
            $user = $request->user();
            $duplicatedProduct = $this->productService->duplicateProduct($product, $user);

            return $this->createdResponse(
                $this->transformProduct($duplicatedProduct),
                'Produit dupliqué avec succès'
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Limite de produits')) {
                return $this->errorResponse($e->getMessage(), null, 402);
            }

            return $this->errorResponse('Erreur lors de la duplication du produit', null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/products/{id}/toggle-featured",
     *     tags={"Produits"},
     *     summary="Basculer le statut vedette",
     *     description="Mettre en vedette ou retirer de la vedette un produit",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut vedette modifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statut vedette modifié"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="is_featured", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function toggleFeatured(Request $request, Product $product): JsonResponse
    {
        // Vérifier que l'utilisateur est propriétaire du produit ou admin
        if ($product->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'êtes pas autorisé à modifier ce produit');
        }

        $product->update(['is_featured' => !$product->is_featured]);

        $message = $product->is_featured ? 'Produit mis en vedette' : 'Produit retiré de la vedette';

        return $this->successResponse([
            'is_featured' => $product->is_featured
        ], $message);
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}/stock",
     *     tags={"Produits"},
     *     summary="Mettre à jour le stock",
     *     description="Modifier la quantité en stock d'un produit",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID du produit"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity","operation"},
     *             @OA\Property(property="quantity", type="integer", example=50),
     *             @OA\Property(property="operation", type="string", enum={"set","add","subtract"}, example="set")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock mis à jour"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="stock_quantity", type="integer", example=50),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="is_available", type="boolean", example=true),
     *                 @OA\Property(property="availability_status", type="string", example="in_stock"),
     *                 @OA\Property(property="is_low_stock", type="boolean", example=false)
     *             )
     *         )
     *     )
     * )
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        // Vérifier que l'utilisateur est propriétaire du produit ou admin
        if ($product->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbiddenResponse('Vous n\'êtes pas autorisé à modifier ce produit');
        }

        $request->validate([
            'quantity' => 'required|integer|min:0',
            'operation' => 'required|in:set,add,subtract'
        ]);

        if (!$product->track_inventory) {
            return $this->errorResponse('Ce produit ne suit pas les stocks', null, 400);
        }

        try {
            $updatedProduct = $this->productService->updateStock(
                $product,
                $request->quantity,
                $request->operation
            );

            return $this->successResponse([
                'stock_quantity' => $updatedProduct->stock_quantity,
                'stock_status' => $updatedProduct->stock_status,
                'is_available' => $updatedProduct->is_available,
                'availability_status' => $updatedProduct->availability_status,
                'is_low_stock' => $updatedProduct->is_low_stock,
            ], 'Stock mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du stock', null, 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-products",
     *     tags={"Produits"},
     *     summary="Mes produits",
     *     description="Récupérer les produits de l'utilisateur connecté",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft","active","inactive","archived"}),
     *         description="Filtrer par statut"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=12),
     *         description="Nombre de produits par page"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mes produits récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Vos produits récupérés"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function myProducts(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'category_id']);
        $filters['user_id'] = $request->user()->id;

        $perPage = min($request->get('per_page', 12), 50);
        $products = $this->productService->searchProducts($filters, $perPage);

        // Transformer les données avec des informations supplémentaires pour le propriétaire
        $productsData = $products->getCollection()->map(function ($product) {
            $data = $this->transformProduct($product);

            // Ajouter des données spécifiques au propriétaire
            $data['analytics'] = [
                'views_count' => $product->views_count,
                'sales_count' => $product->sales_count,
                'average_rating' => $product->average_rating,
                'reviews_count' => $product->reviews_count,
            ];

            return $data;
        });

        return $this->paginatedResponse(
            $products->setCollection($productsData),
            'Vos produits récupérés avec succès'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/products/search",
     *     tags={"Produits"},
     *     summary="Recherche avancée",
     *     description="Recherche de produits avec suggestions",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Terme de recherche"
     *     ),
     *     @OA\Parameter(
     *         name="suggestions",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         description="Inclure les suggestions"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats de recherche",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Résultats de recherche"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="results", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *                 @OA\Property(property="suggestions", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="total_results", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'suggestions' => 'boolean'
        ]);

        $searchTerm = $request->get('q');
        $includeSuggestions = $request->boolean('suggestions', false);

        $filters = ['search' => $searchTerm];
        $products = $this->productService->searchProducts($filters, 10);

        $data = [
            'results' => $products->getCollection()->map(function ($product) {
                return $this->transformProduct($product);
            }),
            'total_results' => $products->total(),
        ];

        // Ajouter des suggestions si demandé
        if ($includeSuggestions) {
            $data['suggestions'] = $this->generateSearchSuggestions($searchTerm);
        }

        return $this->successResponse($data, 'Résultats de recherche');
    }

    /**
     * Transformer un produit pour l'API
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

    /**
     * Transformer un produit avec détails complets
     */
    private function transformProductDetail(Product $product): array
    {
        $data = $this->transformProduct($product);

        // Ajouter les détails complets
        $data['description'] = $product->description;
        $data['weight'] = $product->weight;
        $data['dimensions'] = $product->dimensions;
        $data['video_url'] = $product->video_url;
        $data['tags'] = $product->tags;
        $data['attributes'] = $product->attributes;
        $data['variants'] = $product->variants;
        $data['meta_title'] = $product->meta_title;
        $data['meta_description'] = $product->meta_description;
        $data['track_inventory'] = $product->track_inventory;
        $data['stock_quantity'] = $product->stock_quantity;
        $data['allow_backorder'] = $product->allow_backorder;
        $data['views_count'] = $product->views_count;
        $data['sales_count'] = $product->sales_count;

        // Ajouter les images
        $data['images'] = $product->images->map(function ($image) {
            return [
                'id' => $image->id,
                'image_url' => asset('storage/' . $image->image_path),
                'alt_text' => $image->alt_text,
                'is_primary' => $image->is_primary,
            ];
        });

        // Ajouter les infos du vendeur
        $data['seller'] = [
            'id' => $product->user->id,
            'business_name' => $product->user->business_name,
            'city' => $product->user->city,
        ];

        return $data;
    }

    /**
     * Générer des suggestions de recherche
     */
    private function generateSearchSuggestions(string $searchTerm): array
    {
        // Rechercher des termes similaires dans les noms de produits
        $suggestions = Product::published()
            ->where('name', 'like', "%{$searchTerm}%")
            ->distinct()
            ->pluck('name')
            ->take(5)
            ->toArray();

        return $suggestions;
    }
}
