<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateOrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product.primaryImage'])
            ->latest()
            ->get(); // Changed from paginate() to get()

        return OrderResource::collection($orders);
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $cart = Cart::with(['items.product'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart is empty',
                ], 400);
            }

            // Check stock availability
            foreach ($cart->items as $item) {
                if ($item->product->stock_quantity < $item->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$item->product->name}",
                    ], 400);
                }
            }

            // Calculate total
            $total = $cart->items->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });

            // Generate order number
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(
                Order::whereDate('created_at', today())->count() + 1,
                4,
                '0',
                STR_PAD_LEFT
            );

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'total_amount' => $total,
                'shipping_address' => $request->shipping_address,
                'shipping_city' => $request->shipping_city,
                'shipping_phone' => $request->shipping_phone,
                'notes' => $request->notes,
            ]);

            // Create order items and update stock
            foreach ($cart->items as $cartItem) {
                $order->items()->create([
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product->name,
                    'product_price' => $cartItem->product->price,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $cartItem->product->price * $cartItem->quantity,
                ]);

                // Decrease stock
                $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
            }

            // Clear cart
            $cart->items()->delete();

            DB::commit();

            $order->load(['items.product.primaryImage']);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => new OrderDetailResource($order),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $orderNumber)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->with(['items.product.primaryImage'])
            ->firstOrFail();

        return new OrderDetailResource($order);
    }
}
