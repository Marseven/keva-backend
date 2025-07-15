<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/dashboard",
     *     summary="Get admin dashboard statistics",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="stats", type="object",
     *                     @OA\Property(property="users", type="object",
     *                         @OA\Property(property="total", type="integer", example=1500),
     *                         @OA\Property(property="active", type="integer", example=1200),
     *                         @OA\Property(property="new_this_month", type="integer", example=85),
     *                         @OA\Property(property="growth_percentage", type="number", example=12.5)
     *                     ),
     *                     @OA\Property(property="products", type="object",
     *                         @OA\Property(property="total", type="integer", example=2500),
     *                         @OA\Property(property="active", type="integer", example=2100),
     *                         @OA\Property(property="new_this_month", type="integer", example=150),
     *                         @OA\Property(property="low_stock", type="integer", example=45)
     *                     ),
     *                     @OA\Property(property="orders", type="object",
     *                         @OA\Property(property="total", type="integer", example=3200),
     *                         @OA\Property(property="pending", type="integer", example=25),
     *                         @OA\Property(property="completed", type="integer", example=2800),
     *                         @OA\Property(property="this_month", type="integer", example=320)
     *                     ),
     *                     @OA\Property(property="revenue", type="object",
     *                         @OA\Property(property="total", type="number", example=15000000),
     *                         @OA\Property(property="this_month", type="number", example=1200000),
     *                         @OA\Property(property="last_month", type="number", example=1100000),
     *                         @OA\Property(property="growth_percentage", type="number", example=9.1)
     *                     )
     *                 ),
     *                 @OA\Property(property="recent_orders", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="recent_users", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="top_products", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="payment_methods", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="sales_chart", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $stats = $this->getOverallStats();
        $recentOrders = $this->getRecentOrders();
        $recentUsers = $this->getRecentUsers();
        $topProducts = $this->getTopProducts();
        $paymentMethods = $this->getPaymentMethodsStats();
        $salesChart = $this->getSalesChart();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_orders' => $recentOrders,
                'recent_users' => $recentUsers,
                'top_products' => $topProducts,
                'payment_methods' => $paymentMethods,
                'sales_chart' => $salesChart
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/stats",
     *     summary="Get detailed statistics",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Period for statistics",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "week", "month", "year"}, example="month")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');
        $stats = $this->getStatsByPeriod($period);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/analytics",
     *     summary="Get analytics data",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Analytics type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"sales", "users", "products", "orders"}, example="sales")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Period for analytics",
     *         required=false,
     *         @OA\Schema(type="string", enum={"7d", "30d", "90d", "1y"}, example="30d")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function analytics(Request $request): JsonResponse
    {
        $type = $request->input('type', 'sales');
        $period = $request->input('period', '30d');
        
        $analytics = $this->getAnalytics($type, $period);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/recent-activity",
     *     summary="Get recent activity",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of activities to return",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recent activity retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);
        $activities = $this->getRecentActivity($limit);

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    private function getOverallStats(): array
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // Users stats
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $newUsersThisMonth = User::where('created_at', '>=', $currentMonth)->count();
        $newUsersLastMonth = User::whereBetween('created_at', [$lastMonth, $endOfLastMonth])->count();
        $usersGrowth = $newUsersLastMonth > 0 ? (($newUsersThisMonth - $newUsersLastMonth) / $newUsersLastMonth) * 100 : 0;

        // Products stats
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $newProductsThisMonth = Product::where('created_at', '>=', $currentMonth)->count();
        $lowStockProducts = Product::where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->count();

        // Orders stats
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $completedOrders = Order::where('status', 'completed')->count();
        $ordersThisMonth = Order::where('created_at', '>=', $currentMonth)->count();

        // Revenue stats
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $revenueThisMonth = Payment::where('status', 'completed')
            ->where('created_at', '>=', $currentMonth)
            ->sum('amount');
        $revenueLastMonth = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonth, $endOfLastMonth])
            ->sum('amount');
        $revenueGrowth = $revenueLastMonth > 0 ? (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100 : 0;

        return [
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'new_this_month' => $newUsersThisMonth,
                'growth_percentage' => round($usersGrowth, 1)
            ],
            'products' => [
                'total' => $totalProducts,
                'active' => $activeProducts,
                'new_this_month' => $newProductsThisMonth,
                'low_stock' => $lowStockProducts
            ],
            'orders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'completed' => $completedOrders,
                'this_month' => $ordersThisMonth
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'this_month' => $revenueThisMonth,
                'last_month' => $revenueLastMonth,
                'growth_percentage' => round($revenueGrowth, 1)
            ]
        ];
    }

    private function getRecentOrders(): array
    {
        return Order::with(['user', 'items.product'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_name' => $order->user->full_name,
                    'total_amount' => $order->total_amount,
                    'formatted_total' => $order->formatted_total,
                    'status' => $order->status,
                    'status_badge' => $order->status_badge,
                    'items_count' => $order->items_count,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s')
                ];
            })
            ->toArray();
    }

    private function getRecentUsers(): array
    {
        return User::latest()
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'business_name' => $user->business_name,
                    'is_active' => $user->is_active,
                    'avatar_url' => $user->avatar_url,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s')
                ];
            })
            ->toArray();
    }

    private function getTopProducts(): array
    {
        return Product::with(['user', 'category'])
            ->orderBy('sales_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'user_name' => $product->user->full_name,
                    'sales_count' => $product->sales_count,
                    'price' => $product->price,
                    'formatted_price' => $product->formatted_price,
                    'featured_image_url' => $product->featured_image_url,
                    'stock_status' => $product->stock_status
                ];
            })
            ->toArray();
    }

    private function getPaymentMethodsStats(): array
    {
        return Payment::where('status', 'completed')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($payment) {
                return [
                    'method' => $payment->payment_method,
                    'method_name' => $payment->method_display_name,
                    'count' => $payment->count,
                    'total' => $payment->total,
                    'formatted_total' => number_format($payment->total, 0, ',', ' ') . ' XAF'
                ];
            })
            ->toArray();
    }

    private function getSalesChart(): array
    {
        $days = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $sales = Payment::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('amount');
            
            $days->push([
                'date' => $date->format('Y-m-d'),
                'sales' => $sales,
                'formatted_sales' => number_format($sales, 0, ',', ' ') . ' XAF'
            ]);
        }

        return [
            'labels' => $days->pluck('date')->toArray(),
            'data' => $days->pluck('sales')->toArray(),
            'formatted_data' => $days->pluck('formatted_sales')->toArray()
        ];
    }

    private function getStatsByPeriod(string $period): array
    {
        $startDate = match ($period) {
            'day' => Carbon::now()->startOfDay(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth()
        };

        $orders = Order::where('created_at', '>=', $startDate)->count();
        $revenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->sum('amount');
        $users = User::where('created_at', '>=', $startDate)->count();
        $products = Product::where('created_at', '>=', $startDate)->count();

        return [
            'period' => $period,
            'orders' => $orders,
            'revenue' => $revenue,
            'users' => $users,
            'products' => $products,
            'formatted_revenue' => number_format($revenue, 0, ',', ' ') . ' XAF'
        ];
    }

    private function getAnalytics(string $type, string $period): array
    {
        $days = match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30
        };

        $data = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $value = $this->getAnalyticsValue($type, $date);
            
            $data->push([
                'date' => $date->format('Y-m-d'),
                'value' => $value
            ]);
        }

        return [
            'type' => $type,
            'period' => $period,
            'data' => $data->toArray()
        ];
    }

    private function getAnalyticsValue(string $type, Carbon $date): int
    {
        return match ($type) {
            'sales' => Payment::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('amount'),
            'users' => User::whereDate('created_at', $date)->count(),
            'products' => Product::whereDate('created_at', $date)->count(),
            'orders' => Order::whereDate('created_at', $date)->count(),
            default => 0
        };
    }

    private function getRecentActivity(int $limit): array
    {
        $activities = collect();

        // Recent orders
        $recentOrders = Order::with('user')
            ->latest()
            ->limit($limit / 2)
            ->get()
            ->map(function ($order) {
                return [
                    'type' => 'order',
                    'title' => "Nouvelle commande #{$order->order_number}",
                    'description' => "Par {$order->user->full_name}",
                    'amount' => $order->total_amount,
                    'created_at' => $order->created_at
                ];
            });

        // Recent users
        $recentUsers = User::latest()
            ->limit($limit / 2)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'user',
                    'title' => "Nouvel utilisateur inscrit",
                    'description' => $user->full_name,
                    'amount' => null,
                    'created_at' => $user->created_at
                ];
            });

        return $activities->merge($recentOrders)
            ->merge($recentUsers)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->toArray();
    }
}
