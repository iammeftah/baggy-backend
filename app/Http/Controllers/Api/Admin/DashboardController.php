<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\AdminActivity;
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

        // NEW: Pending Revenue (Orders not yet delivered)
        $pendingRevenue = Order::whereIn('status', ['pending', 'shipping'])
            ->sum('total_amount');

        // Order Status Distribution
        $pendingOrders = Order::where('status', 'pending')->count();
        $shippingOrders = Order::where('status', 'shipping')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();

        // NEW: Cancelled/Failed Orders
        $cancelledOrders = Order::where('status', 'cancelled')->count();
        $failedOrders = Order::where('status', 'failed')->count();

        // Product Stats
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $outOfStock = Product::where('stock_quantity', 0)->count();
        $lowStock = Product::where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 10)
            ->count();

        // NEW: Critical Low Stock (<=3 items)
        $criticalStock = Product::where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 3)
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

        // NEW: Conversion Metrics
        $totalCustomersThisMonth = User::where('role', 'customer')
            ->where('created_at', '>=', $thisMonthStart)
            ->count();
        $customersWithOrdersThisMonth = User::where('role', 'customer')
            ->where('created_at', '>=', $thisMonthStart)
            ->whereHas('orders')
            ->count();
        $conversionRate = $totalCustomersThisMonth > 0
            ? ($customersWithOrdersThisMonth / $totalCustomersThisMonth) * 100
            : 0;

        // NEW: Repeat Customer Rate
        $repeatCustomers = User::where('role', 'customer')
            ->has('orders', '>=', 2)
            ->count();
        $repeatCustomerRate = $totalCustomers > 0
            ? ($repeatCustomers / $totalCustomers) * 100
            : 0;

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
                'products.stock_quantity',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.slug', 'products.stock_quantity')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'stock_quantity' => $item->stock_quantity,
                    'total_sold' => (int) $item->total_sold,
                    'total_revenue' => (float) number_format($item->total_revenue, 2, '.', ''),
                    'needs_restock' => $item->stock_quantity <= 10,
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
            'cancelled' => Order::where('status', 'cancelled')
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
                    'time_ago' => $order->created_at->diffForHumans(),
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
        // NEW: ACTIONABLE ALERTS
        // ============================================================================
        $alerts = [
            'urgent_orders' => Order::where('status', 'pending')
                ->where('created_at', '<', Carbon::now()->subHours(24))
                ->count(),
            'critical_stock' => Product::where('stock_quantity', '>', 0)
                ->where('stock_quantity', '<=', 3)
                ->count(),
            'out_of_stock_popular' => DB::table('products')
                ->join('order_items', 'products.id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('products.stock_quantity', 0)
                ->where('orders.created_at', '>=', Carbon::now()->subDays(30))
                ->select('products.id')
                ->groupBy('products.id')
                ->havingRaw('SUM(order_items.quantity) > 0')
                ->count(),
        ];

        // ============================================================================
        // NEW: ADMIN ACTIVITY LOG (Last 24 hours)
        // ============================================================================
        $adminActivities = AdminActivity::with('admin')
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'admin' => [
                        'id' => $activity->admin->id,
                        'name' => $activity->admin->first_name . ' ' . $activity->admin->last_name,
                        'email' => $activity->admin->email,
                    ],
                    'action' => $activity->action,
                    'entity_type' => $activity->entity_type,
                    'entity_id' => $activity->entity_id,
                    'description' => $activity->description,
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            });

        // ============================================================================
        // NEW: TOP CUSTOMERS (By Total Spent)
        // ============================================================================
        $topCustomers = DB::table('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('users.role', 'customer')
            ->where('orders.status', 'delivered')
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('SUM(orders.total_amount) as total_spent')
            )
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'email' => $customer->email,
                    'total_orders' => (int) $customer->total_orders,
                    'total_spent' => (float) number_format($customer->total_spent, 2, '.', ''),
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

                    'pending_revenue' => (float) number_format($pendingRevenue, 2, '.', ''),

                    'pending_orders' => $pendingOrders,
                    'shipping_orders' => $shippingOrders,
                    'delivered_orders' => $deliveredOrders,
                    'cancelled_orders' => $cancelledOrders,
                    'failed_orders' => $failedOrders,

                    'total_products' => $totalProducts,
                    'active_products' => $activeProducts,
                    'out_of_stock' => $outOfStock,
                    'low_stock' => $lowStock,
                    'critical_stock' => $criticalStock,

                    'total_customers' => $totalCustomers,
                    'new_customers_this_month' => $newCustomersThisMonth,
                    'customer_growth' => $customerGrowth,

                    'avg_order_value' => (float) number_format($avgOrderValue, 2, '.', ''),
                    'conversion_rate' => (float) number_format($conversionRate, 2, '.', ''),
                    'repeat_customer_rate' => (float) number_format($repeatCustomerRate, 2, '.', ''),
                ],

                // Trends
                'sales_trend' => $salesTrend,
                'monthly_revenue' => $monthlyRevenue,
                'order_status_trend' => $orderStatusTrend,

                // Performance
                'top_products' => $topProducts,
                'category_performance' => $categoryPerformance,
                'top_customers' => $topCustomers,

                // Recent Activity
                'recent_orders' => $recentOrders,
                'admin_activities' => $adminActivities,

                // Alerts
                'alerts' => $alerts,
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
