<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats(): array
    {
        return [
            'overview' => $this->getOverview(),
            'sales_trend' => $this->getSalesTrend(),
            'monthly_revenue' => $this->getMonthlyRevenue(),
            'order_status_trend' => $this->getOrderStatusTrend(),
            'top_products' => $this->getTopProducts(),
            'category_performance' => $this->getCategoryPerformance(),
            'top_customers' => $this->getTopCustomers(),
            'recent_orders' => $this->getRecentOrders(),
            'profit_analysis' => $this->getProfitAnalysis(), // NEW
            'alerts' => $this->getAlerts(),
        ];
    }

    /**
     * Get dashboard overview metrics
     */
    private function getOverview(): array
    {
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Orders
        $totalOrders = Order::count();
        $ordersThisWeek = Order::where('created_at', '>=', $startOfWeek)->count();
        $ordersThisMonth = Order::where('created_at', '>=', $startOfMonth)->count();
        $ordersPreviousMonth = Order::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
        $ordersGrowth = $ordersPreviousMonth > 0 ? (($ordersThisMonth - $ordersPreviousMonth) / $ordersPreviousMonth) * 100 : 0;

        // Revenue
        $totalRevenue = Order::whereIn('status', ['delivered'])->sum('total_amount');
        $revenueThisMonth = Order::whereIn('status', ['delivered'])
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total_amount');
        $revenuePreviousMonth = Order::whereIn('status', ['delivered'])
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('total_amount');
        $revenueGrowth = $revenuePreviousMonth > 0 ? (($revenueThisMonth - $revenuePreviousMonth) / $revenuePreviousMonth) * 100 : 0;

        $revenueToday = Order::whereIn('status', ['delivered'])
            ->whereDate('created_at', $now->toDateString())
            ->sum('total_amount');
        $revenueYesterday = Order::whereIn('status', ['delivered'])
            ->whereDate('created_at', $now->copy()->subDay()->toDateString())
            ->sum('total_amount');
        $dailyRevenueGrowth = $revenueYesterday > 0 ? (($revenueToday - $revenueYesterday) / $revenueYesterday) * 100 : 0;

        $pendingRevenue = Order::where('status', 'pending')->sum('total_amount');

        // Order Status
        $pendingOrders = Order::where('status', 'pending')->count();
        $shippingOrders = Order::where('status', 'shipping')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();
        $failedOrders = Order::where('status', 'failed')->count();

        // Products
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $outOfStock = Product::where('stock_quantity', 0)->count();
        $lowStock = Product::where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10)->count();
        $criticalStock = Product::where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5)->count();

        // Customers
        $totalCustomers = User::where('role', 'customer')->count();
        $newCustomersThisMonth = User::where('role', 'customer')
            ->where('created_at', '>=', $startOfMonth)
            ->count();
        $newCustomersPreviousMonth = User::where('role', 'customer')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();
        $customerGrowth = $newCustomersPreviousMonth > 0 ? (($newCustomersThisMonth - $newCustomersPreviousMonth) / $newCustomersPreviousMonth) * 100 : 0;

        // Metrics
        $avgOrderValue = $deliveredOrders > 0 ? $totalRevenue / $deliveredOrders : 0;
        $conversionRate = $totalCustomers > 0 ? ($totalOrders / $totalCustomers) * 100 : 0;
        $repeatCustomers = User::where('role', 'customer')
            ->whereHas('orders', function ($q) {
                $q->select(DB::raw('count(*)'))
                  ->groupBy('user_id')
                  ->havingRaw('count(*) > 1');
            })->count();
        $repeatCustomerRate = $totalCustomers > 0 ? ($repeatCustomers / $totalCustomers) * 100 : 0;

        return [
            // Orders
            'total_orders' => $totalOrders,
            'orders_this_week' => $ordersThisWeek,
            'orders_growth' => round($ordersGrowth, 2),

            // Revenue
            'total_revenue' => round($totalRevenue, 2),
            'revenue_this_month' => round($revenueThisMonth, 2),
            'revenue_growth' => round($revenueGrowth, 2),
            'revenue_today' => round($revenueToday, 2),
            'daily_revenue_growth' => round($dailyRevenueGrowth, 2),
            'pending_revenue' => round($pendingRevenue, 2),

            // Order Status
            'pending_orders' => $pendingOrders,
            'shipping_orders' => $shippingOrders,
            'delivered_orders' => $deliveredOrders,
            'cancelled_orders' => $cancelledOrders,
            'failed_orders' => $failedOrders,

            // Products
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'critical_stock' => $criticalStock,

            // Customers
            'total_customers' => $totalCustomers,
            'new_customers_this_month' => $newCustomersThisMonth,
            'customer_growth' => round($customerGrowth, 2),

            // Metrics
            'avg_order_value' => round($avgOrderValue, 2),
            'conversion_rate' => round($conversionRate, 2),
            'repeat_customer_rate' => round($repeatCustomerRate, 2),
        ];
    }

    /**
     * NEW: Get profit analysis with inventory metrics
     */
    private function getProfitAnalysis(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Calculate total profit from delivered orders (CURRENT PROFIT)
        $deliveredOrders = Order::with('items.product')
            ->where('status', 'delivered')
            ->get();

        $totalProfit = 0;
        $totalCost = 0;
        $totalRevenue = 0;

        foreach ($deliveredOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $cost = $item->product->buying_price * $item->quantity;
                    $revenue = $item->product->selling_price * $item->quantity;
                    $totalCost += $cost;
                    $totalRevenue += $revenue;
                    $totalProfit += ($revenue - $cost);
                }
            }
        }

        // Calculate INVENTORY VALUE (current stock worth)
        $allProducts = Product::where('is_active', true)->get();

        $stockValue = 0; // How much we spent to buy all current stock
        $stockEstimatedSales = 0; // How much it's worth selling all stock
        $estimatedProfit = 0; // Potential profit if all stock sells

        foreach ($allProducts as $product) {
            $stockValue += ($product->buying_price * $product->stock_quantity);
            $stockEstimatedSales += ($product->selling_price * $product->stock_quantity);
            $estimatedProfit += (($product->selling_price - $product->buying_price) * $product->stock_quantity);
        }

        // This month's profit
        $thisMonthOrders = Order::with('items.product')
            ->where('status', 'delivered')
            ->where('created_at', '>=', $startOfMonth)
            ->get();

        $monthProfit = 0;
        $monthCost = 0;
        $monthRevenue = 0;

        foreach ($thisMonthOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $cost = $item->product->buying_price * $item->quantity;
                    $revenue = $item->product->selling_price * $item->quantity;
                    $monthCost += $cost;
                    $monthRevenue += $revenue;
                    $monthProfit += ($revenue - $cost);
                }
            }
        }

        // Previous month's profit
        $previousMonthOrders = Order::with('items.product')
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->get();

        $previousMonthProfit = 0;
        foreach ($previousMonthOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $cost = $item->product->buying_price * $item->quantity;
                    $revenue = $item->product->selling_price * $item->quantity;
                    $previousMonthProfit += ($revenue - $cost);
                }
            }
        }

        $profitGrowth = $previousMonthProfit > 0 ? (($monthProfit - $previousMonthProfit) / $previousMonthProfit) * 100 : 0;

        // Average profit margin
        $avgProfitMargin = Product::where('is_active', true)
            ->where('buying_price', '>', 0)
            ->avg('profit_margin');

        // Today's profit
        $todayOrders = Order::with('items.product')
            ->where('status', 'delivered')
            ->whereDate('created_at', $now->toDateString())
            ->get();

        $todayProfit = 0;
        foreach ($todayOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $cost = $item->product->buying_price * $item->quantity;
                    $revenue = $item->product->selling_price * $item->quantity;
                    $todayProfit += ($revenue - $cost);
                }
            }
        }

        return [
            // Current profit from delivered orders
            'current_profit' => round($totalProfit, 2),
            'total_cost' => round($totalCost, 2),
            'total_revenue' => round($totalRevenue, 2),
            'profit_margin_percentage' => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0,

            // Inventory value metrics
            'stock_value' => round($stockValue, 2), // How much we spent to buy all stock
            'stock_estimated_sales' => round($stockEstimatedSales, 2), // How much it's worth selling all stock
            'estimated_profit' => round($estimatedProfit, 2), // Profit if all stock sells

            // Monthly metrics
            'profit_this_month' => round($monthProfit, 2),
            'profit_growth' => round($profitGrowth, 2),
            'avg_profit_margin' => round($avgProfitMargin ?? 0, 2),
            'profit_today' => round($todayProfit, 2),
            'cost_this_month' => round($monthCost, 2),
            'revenue_this_month' => round($monthRevenue, 2),

            // Legacy field (now same as current_profit)
            'total_profit' => round($totalProfit, 2),
            'potential_profit' => round($estimatedProfit, 2), // Alias for estimated_profit
        ];
    }

    /**
     * Get sales trend (last 7 days)
     */
    private function getSalesTrend(): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $orders = Order::whereDate('created_at', $date->toDateString())->count();
            $revenue = Order::whereDate('created_at', $date->toDateString())
                ->whereIn('status', ['delivered'])
                ->sum('total_amount');

            $trend[] = [
                'date' => $date->toDateString(),
                'day' => $date->format('D'),
                'orders' => $orders,
                'revenue' => round($revenue, 2),
            ];
        }

        return $trend;
    }

    /**
     * Get monthly revenue (last 6 months)
     */
    private function getMonthlyRevenue(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $revenue = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->whereIn('status', ['delivered'])
                ->sum('total_amount');

            $orders = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

            $months[] = [
                'month' => $date->format('F Y'),
                'month_short' => $date->format('M'),
                'revenue' => round($revenue, 2),
                'orders' => $orders,
            ];
        }

        return $months;
    }

    /**
     * Get order status trend
     */
    private function getOrderStatusTrend(): array
    {
        return [
            'pending' => Order::where('status', 'pending')->count(),
            'shipping' => Order::where('status', 'shipping')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Get top selling products
     */
    private function getTopProducts(int $limit = 5): array
    {
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->with('product.category')
            ->get();

        return $topProducts->map(function ($item) {
            $product = $item->product;
            if (!$product) return null;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category' => $product->category?->name,
                'stock_quantity' => $product->stock_quantity,
                'total_sold' => $item->total_sold,
                'total_revenue' => round($item->total_revenue, 2),
                'needs_restock' => $product->stock_quantity <= 10,
                'profit_margin' => round($product->profit_margin, 2), // NEW
                'potential_profit' => round($product->potential_profit, 2), // NEW
            ];
        })->filter()->values()->toArray();
    }

    /**
     * Get category performance
     */
    private function getCategoryPerformance(): array
    {
        $performance = OrderItem::join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.id', 'categories.name', DB::raw('SUM(order_items.quantity) as items_sold'), DB::raw('SUM(order_items.subtotal) as revenue'))
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->get();

        return $performance->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'items_sold' => $item->items_sold,
                'revenue' => round($item->revenue, 2),
            ];
        })->toArray();
    }

    /**
     * Get top customers
     */
    private function getTopCustomers(int $limit = 5): array
    {
        $topCustomers = User::where('role', 'customer')
            ->withCount('orders')
            ->withSum('orders', 'total_amount')
            ->orderBy('orders_sum_total_amount', 'desc')
            ->limit($limit)
            ->get();

        return $topCustomers->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->first_name . ' ' . $customer->last_name,
                'email' => $customer->email,
                'total_orders' => $customer->orders_count,
                'total_spent' => round($customer->orders_sum_total_amount ?? 0, 2),
            ];
        })->toArray();
    }

    /**
     * Get recent orders
     */
    private function getRecentOrders(int $limit = 10): array
    {
        $orders = Order::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => (string) $order->total_amount,
                'user' => [
                    'id' => $order->user->id,
                    'first_name' => $order->user->first_name,
                    'last_name' => $order->user->last_name,
                    'email' => $order->user->email,
                ],
                'created_at' => $order->created_at->toDateTimeString(),
                'time_ago' => $order->created_at->diffForHumans(),
            ];
        })->toArray();
    }

    /**
     * Get system alerts
     */
    private function getAlerts(): array
    {
        $urgentOrders = Order::where('status', 'pending')
            ->where('created_at', '<', Carbon::now()->subHours(24))
            ->count();

        $criticalStock = Product::where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 5)
            ->count();

        // Out of stock popular products (sold > 20 units)
        $popularProductIds = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->having('total_sold', '>', 20)
            ->pluck('product_id');

        $outOfStockPopular = Product::whereIn('id', $popularProductIds)
            ->where('stock_quantity', 0)
            ->count();

        return [
            'urgent_orders' => $urgentOrders,
            'critical_stock' => $criticalStock,
            'out_of_stock_popular' => $outOfStockPopular,
        ];
    }
}
