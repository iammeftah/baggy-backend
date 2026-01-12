<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by order number or customer name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }


    // app/Http/Controllers/Api/Admin/OrderController.php

    public function show(string $orderNumber)
    {
        $order = Order::with([
            'user',
            'items.product.primaryImage',  // Load primaryImage, not images
            'items.product'
        ])
        ->where('order_number', $orderNumber)
        ->firstOrFail();

        return new OrderResource($order);
    }

    public function updateStatus(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,shipping,delivered',
        ]);

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $order->update(['status' => $request->status]);

        $order->load([
            'user',
            'items.product.primaryImage',
            'items.product'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => new OrderResource($order),
        ]);
    }
}
