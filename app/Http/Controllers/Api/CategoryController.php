<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    /**
     * @OA\Get(
     *     path="/api/categories",
     *     tags={"Produits"},
     *     summary="Lister toutes les catégories",
     *     description="Récupérer la liste de toutes les catégories de produits",
     *     @OA\Parameter(
     *         name="parent_only",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         description="Récupérer uniquement les catégories parentes"
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         description="Récupérer uniquement les catégories en vedette"
     *     ),
     *     @OA\Parameter(
     *         name="with_children",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         description="Inclure les sous-catégories"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Catégories récupérées avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Catégories récupérées avec succès"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Category")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::active()->ordered();

        // Filtrer par catégories parentes seulement
        if ($request->boolean('parent_only')) {
            $query->rootCategories();
        }

        // Filtrer par catégories en vedette
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Inclure les sous-catégories
        if ($request->boolean('with_children')) {
            $query->with('children');
        }

        $categories = $query->get()->map(function ($category) use ($request) {
            $data = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => $category->image_url,
                'icon' => $category->icon,
                'color' => $category->color,
                'parent_id' => $category->parent_id,
                'is_featured' => $category->is_featured,
                'products_count' => $category->getActiveProductsCount(),
                'breadcrumb' => $category->breadcrumb,
            ];

            // Ajouter les enfants si demandé
            if ($request->boolean('with_children') && $category->children) {
                $data['children'] = $category->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'icon' => $child->icon,
                        'color' => $child->color,
                        'products_count' => $child->getActiveProductsCount(),
                    ];
                });
            }

            return $data;
        });

        return $this->successResponse($categories, 'Catégories récupérées avec succès');
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{slug}",
     *     tags={"Produits"},
     *     summary="Détails d'une catégorie",
     *     description="Récupérer les détails d'une catégorie spécifique",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Slug de la catégorie"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la catégorie récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Catégorie récupérée avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Category")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Catégorie non trouvée",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->active()
            ->with(['children', 'parent'])
            ->first();

        if (!$category) {
            return $this->notFoundResponse('Catégorie non trouvée');
        }

        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'icon' => $category->icon,
            'color' => $category->color,
            'parent_id' => $category->parent_id,
            'is_featured' => $category->is_featured,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'products_count' => $category->getActiveProductsCount(),
            'breadcrumb' => $category->breadcrumb,
            'is_parent' => $category->isParent(),
            'is_child' => $category->isChild(),
        ];

        // Ajouter le parent si existe
        if ($category->parent) {
            $categoryData['parent'] = [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->slug,
                'color' => $category->parent->color,
            ];
        }

        // Ajouter les enfants si existent
        if ($category->children->isNotEmpty()) {
            $categoryData['children'] = $category->children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'icon' => $child->icon,
                    'color' => $child->color,
                    'products_count' => $child->getActiveProductsCount(),
                ];
            });
        }

        return $this->successResponse($categoryData, 'Catégorie récupérée avec succès');
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{slug}/products",
     *     tags={"Produits"},
     *     summary="Produits d'une catégorie",
     *     description="Récupérer les produits d'une catégorie spécifique",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Slug de la catégorie"
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=1),
     *         description="Numéro de page"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=12),
     *         description="Nombre de produits par page"
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "created_at", "popularity"}),
     *         description="Tri des produits"
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="asc"),
     *         description="Ordre de tri"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Produits de la catégorie récupérés",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produits récupérés avec succès"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     )
     * )
     */
    public function products(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->active()->first();

        if (!$category) {
            return $this->notFoundResponse('Catégorie non trouvée');
        }

        // Obtenir tous les IDs des catégories descendantes
        $categoryIds = $category->getAllDescendantIds();

        $query = \App\Models\Product::published()
            ->inStock()
            ->whereIn('category_id', $categoryIds)
            ->with(['category', 'images']);

        // Tri des produits
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');

        switch ($sort) {
            case 'name':
                $query->orderBy('name', $order);
                break;
            case 'price':
                $query->orderBy('price', $order);
                break;
            case 'popularity':
                $query->orderBy('sales_count', $order);
                break;
            default:
                $query->orderBy('created_at', $order);
        }

        $perPage = min($request->get('per_page', 12), 50); // Max 50 par page
        $products = $query->paginate($perPage);

        // Transformer les données des produits
        $productsData = $products->getCollection()->map(function ($product) {
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
                'featured_image_url' => $product->featured_image_url,
                'is_in_stock' => $product->is_in_stock,
                'stock_status' => $product->stock_status,
                'average_rating' => $product->average_rating,
                'reviews_count' => $product->reviews_count,
                'category' => [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ],
            ];
        });

        return $this->paginatedResponse($products->setCollection($productsData), 'Produits récupérés avec succès');
    }

    /**
     * @OA\Get(
     *     path="/api/categories/tree",
     *     tags={"Produits"},
     *     summary="Arbre des catégories",
     *     description="Récupérer l'arbre hiérarchique complet des catégories",
     *     @OA\Response(
     *         response=200,
     *         description="Arbre des catégories récupéré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Arbre des catégories récupéré"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="slug", type="string"),
     *                     @OA\Property(property="icon", type="string"),
     *                     @OA\Property(property="color", type="string"),
     *                     @OA\Property(property="products_count", type="integer"),
     *                     @OA\Property(property="children", type="array", @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function tree(): JsonResponse
    {
        $categories = Category::active()
            ->rootCategories()
            ->ordered()
            ->with(['children' => function ($query) {
                $query->active()->ordered();
            }])
            ->get();

        $tree = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'color' => $category->color,
                'products_count' => $category->getActiveProductsCount(),
                'children' => $category->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'icon' => $child->icon,
                        'color' => $child->color,
                        'products_count' => $child->getActiveProductsCount(),
                    ];
                }),
            ];
        });

        return $this->successResponse($tree, 'Arbre des catégories récupéré');
    }
}
