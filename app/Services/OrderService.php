<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OrderService
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Create order from cart.
     *
     * @param User $user
     * @param array $data
     * @return Order
     * @throws \Exception
     */
    public function createOrder(User $user, array $data): Order
    {
        $cart = $this->cartService->getCart($user);

        // Check if cart is empty
        if ($cart->isEmpty()) {
            throw new \Exception('Cart is empty.');
        }

        // Validate stock for all items
        foreach ($cart->items as $item) {
            if (!$item->product->hasStock($item->quantity)) {
                throw new \Exception("Insufficient stock for product: {$item->product->name}");
            }
        }

        // Calculate total
        $totalAmount = 0;
        foreach ($cart->items as $item) {
            $totalAmount += $item->product->price * $item->quantity;
        }

        // Create order
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => $totalAmount,
            'shipping_address' => $data['shipping_address'],
            'shipping_city' => $data['shipping_city'],
            'shipping_phone' => $data['shipping_phone'],
            'notes' => $data['notes'] ?? null,
        ]);

        // Create order items and decrease stock
        foreach ($cart->items as $cartItem) {
            $order->items()->create([
                'product_id' => $cartItem->product_id,
                'product_name' => $cartItem->product->name,
                'product_price' => $cartItem->product->price,
                'quantity' => $cartItem->quantity,
                'subtotal' => $cartItem->product->price * $cartItem->quantity,
            ]);

            // Decrease stock
            $cartItem->product->decreaseStock($cartItem->quantity);
        }

        // Clear cart
        $cart->clear();

        return $order->load('items.product');
    }

    /**
     * Get customer orders.
     *
     * @param User $user
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getCustomerOrders(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->orders()->with('items');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get order by order number.
     *
     * @param User $user
     * @param string $orderNumber
     * @return Order
     */
    public function getOrderByNumber(User $user, string $orderNumber): Order
    {
        return $user->orders()
            ->where('order_number', $orderNumber)
            ->with(['items.product.primaryImage'])
            ->firstOrFail();
    }

    /**
     * Get all orders for admin.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAllOrders(array $filters = []): LengthAwarePaginator
    {
        $query = Order::query()->with(['user', 'items']);

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Search
        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }


    /**
     * Get order details for admin.
     *
     * @param string $orderNumber
     * @return Order
     */
    public function getOrderDetails(string $orderNumber): Order
    {
        return Order::where('order_number', $orderNumber)
            ->with(['user', 'items.product.primaryImage', 'items.product.images'])
            ->firstOrFail();
    }

    /**
     * Update order status.
     *
     * @param Order $order
     * @param string $status
     * @return Order
     * @throws \Exception
     */
    public function updateOrderStatus(Order $order, string $status): Order
    {
        if (!in_array($status, ['pending', 'shipping', 'delivered'])) {
            throw new \Exception('Invalid order status.');
        }

        $order->updateStatus($status);

        return $order->fresh(['user', 'items']);
    }

    /**
     * Get dashboard statistics.
     *
     * @return array
     */
    public function getDashboardStats(): array
    {
        return [
            'total_orders' => Order::count(),
            'pending_orders' => Order::pending()->count(),
            'total_revenue' => Order::delivered()->sum('total_amount'),
            'recent_orders' => Order::with(['user', 'items'])
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }
}
