<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\AdminActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected AdminActivityService $activityService;

    public function __construct(AdminActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

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

    public function show(string $orderNumber)
    {
        $order = Order::with([
            'user',
            'items.product.primaryImage',
            'items.product'
        ])
        ->where('order_number', $orderNumber)
        ->firstOrFail();

        return new OrderResource($order);
    }

    /**
     * Update order status with transaction security and activity logging
     */
    public function updateStatus(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,shipping,delivered,cancelled,failed',
        ]);

        $admin = auth()->user();

        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $order = Order::where('order_number', $orderNumber)
                ->lockForUpdate() // Prevent concurrent modifications
                ->firstOrFail();

            $oldStatus = $order->status;
            $newStatus = $request->status;

            // Prevent duplicate status updates
            if ($oldStatus === $newStatus) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order is already in this status.',
                ], 400);
            }

            // Business logic: Prevent certain status transitions
            $invalidTransitions = [
                'delivered' => ['pending', 'shipping'], // Can't go back from delivered
                'cancelled' => ['delivered'], // Can't cancel delivered orders
            ];

            if (isset($invalidTransitions[$oldStatus]) &&
                in_array($newStatus, $invalidTransitions[$oldStatus])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Cannot change status from {$oldStatus} to {$newStatus}.",
                ], 400);
            }

            // Update order status only
            $order->update([
                'status' => $newStatus,
            ]);

            // ✅ FIX: If order is marked as delivered, log revenue collection AND set return deadline
            // This happens ONCE, not twice like before
            if ($newStatus === 'delivered') {
                $this->activityService->logRevenueCollection($admin, $order);
                $order->setReturnDeadline(7); // 7 days return policy
            }

            // Log the status change activity
            $this->activityService->logOrderStatusChange($admin, $order, $oldStatus, $newStatus);

            // ❌ REMOVED DUPLICATE BLOCK - revenue logging was happening twice!

            DB::commit();

            $order->load([
                'user',
                'items.product.primaryImage',
                'items.product'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => new OrderResource($order),
                'activity_logged' => true,
                'updated_by' => [
                    'id' => $admin->id,
                    'name' => $admin->first_name . ' ' . $admin->last_name,
                    'email' => $admin->email,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order activity history
     */
    public function getActivityHistory(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $activities = \App\Models\AdminActivity::forEntity('Order', $order->id);

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'admin' => [
                        'id' => $activity->admin->id,
                        'name' => $activity->admin->first_name . ' ' . $activity->admin->last_name,
                        'email' => $activity->admin->email,
                    ],
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            }),
        ]);
    }
}
