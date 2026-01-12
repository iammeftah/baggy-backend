<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // Get date ranges for comparisons
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisWeekStart = Carbon::now()->startOfWeek();
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // ============================================================================
        // OVERVIEW STATS WITH COMPARISONS
        // ============================================================================

        // Total Orders (with weekly comparison)
        $totalOrders = Order::count();
        $ordersThisWeek = Order::where('created_at', '>=', $thisWeekStart)->count();
        $ordersLastWeek = Order::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $ordersGrowth = $this->calculateGrowth($ordersThisWeek, $ordersLastWeek);

        // Revenue Stats (with monthly comparison)
        $totalRevenue = Order::where('status', 'delivered')->sum('total_amount');
        $revenueThisMonth = Order::where('status', 'delivered')
            ->where('created_at', '>=', $thisMonthStart)
            ->sum('total_amount');
        $revenueLastMonth = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_amount');
        $revenueGrowth = $this->calculateGrowth($revenueThisMonth, $revenueLastMonth);

        // Today's Revenue
        $revenuesToday = Order::where('status', 'delivered')
            ->whereDate('created_at', $today)
            ->sum('total_amount');
        $revenuesYesterday = Order::where('status', 'delivered')
            ->whereDate('created_at', $yesterday)
            ->sum('total_amount');
        $dailyRevenueGrowth = $this->calculateGrowth($revenuesToday, $revenuesYesterday);

        // Order Status Distribution
        $pendingOrders = Order::where('status', 'pending')->count();
        $shippingOrders = Order::where('status', 'shipping')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();

        // Product Stats
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $outOfStock = Product::where('stock_quantity', 0)->count();
        $lowStock = Product::where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 10)
            ->count();

        // Customer Stats
        $totalCustomers = User::where('role', 'customer')->count();
        $newCustomersThisMonth = User::where('role', 'customer')
            ->where('created_at', '>=', $thisMonthStart)
            ->count();
        $newCustomersLastMonth = User::where('role', 'customer')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();
        $customerGrowth = $this->calculateGrowth($newCustomersThisMonth, $newCustomersLastMonth);

        // Average Order Value
        $avgOrderValue = Order::where('status', 'delivered')->avg('total_amount') ?? 0;

        // ============================================================================
        // SALES TREND - Last 7 Days
        // ============================================================================
        $salesTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dailySales = Order::whereDate('created_at', $date->toDateString())
                ->sum('total_amount');
            $dailyOrders = Order::whereDate('created_at', $date->toDateString())
                ->count();

            $salesTrend[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'revenue' => (float) number_format($dailySales, 2, '.', ''),
                'orders' => $dailyOrders,
            ];
        }

        // ============================================================================
        // MONTHLY REVENUE TREND - Last 6 Months
        // ============================================================================
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();

            $revenue = Order::where('status', 'delivered')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $orders = Order::whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $monthlyRevenue[] = [
                'month' => $monthStart->format('M Y'),
                'month_short' => $monthStart->format('M'),
                'revenue' => (float) number_format($revenue, 2, '.', ''),
                'orders' => $orders,
            ];
        }

        // ============================================================================
        // TOP SELLING PRODUCTS (Last 30 Days)
        // ============================================================================
        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                'products.id',
                'products.name',
                'products.slug',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.slug')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'total_sold' => (int) $item->total_sold,
                    'total_revenue' => (float) number_format($item->total_revenue, 2, '.', ''),
                ];
            });

        // ============================================================================
        // ORDER STATUS TREND - Last 30 Days
        // ============================================================================
        $orderStatusTrend = [
            'pending' => Order::where('status', 'pending')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count(),
            'shipping' => Order::where('status', 'shipping')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count(),
            'delivered' => Order::where('status', 'delivered')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count(),
        ];

        // ============================================================================
        // RECENT ORDERS
        // ============================================================================
        $recentOrders = Order::with(['user', 'items'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => (string) number_format($order->total_amount, 2, '.', ''),
                    'user' => [
                        'id' => $order->user->id,
                        'first_name' => $order->user->first_name,
                        'last_name' => $order->user->last_name,
                        'email' => $order->user->email,
                    ],
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // ============================================================================
        // CATEGORY PERFORMANCE
        // ============================================================================
        $categoryPerformance = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(order_items.quantity) as items_sold'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'items_sold' => (int) $item->items_sold,
                    'revenue' => (float) number_format($item->revenue, 2, '.', ''),
                ];
            });

        // ============================================================================
        // RESPONSE
        // ============================================================================
        return response()->json([
            'success' => true,
            'data' => [
                // Overview Stats
                'overview' => [
                    'total_orders' => $totalOrders,
                    'orders_this_week' => $ordersThisWeek,
                    'orders_growth' => $ordersGrowth,

                    'total_revenue' => (float) number_format($totalRevenue, 2, '.', ''),
                    'revenue_this_month' => (float) number_format($revenueThisMonth, 2, '.', ''),
                    'revenue_growth' => $revenueGrowth,

                    'revenue_today' => (float) number_format($revenuesToday, 2, '.', ''),
                    'daily_revenue_growth' => $dailyRevenueGrowth,

                    'pending_orders' => $pendingOrders,
                    'shipping_orders' => $shippingOrders,
                    'delivered_orders' => $deliveredOrders,

                    'total_products' => $totalProducts,
                    'active_products' => $activeProducts,
                    'out_of_stock' => $outOfStock,
                    'low_stock' => $lowStock,

                    'total_customers' => $totalCustomers,
                    'new_customers_this_month' => $newCustomersThisMonth,
                    'customer_growth' => $customerGrowth,

                    'avg_order_value' => (float) number_format($avgOrderValue, 2, '.', ''),
                ],

                // Trends
                'sales_trend' => $salesTrend,
                'monthly_revenue' => $monthlyRevenue,
                'order_status_trend' => $orderStatusTrend,

                // Performance
                'top_products' => $topProducts,
                'category_performance' => $categoryPerformance,

                // Recent Activity
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    /**
     * Calculate percentage growth between two values
     */
    private function calculateGrowth(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        $growth = (($current - $previous) / $previous) * 100;
        return (float) number_format($growth, 2, '.', '');
    }
}
