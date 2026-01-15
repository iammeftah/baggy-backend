<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderReturnResource;
use App\Models\OrderReturn;
use App\Services\AdminActivityService;
use App\Services\OrderReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderReturnController extends Controller
{
    protected OrderReturnService $returnService;
    protected AdminActivityService $activityService;

    public function __construct(
        OrderReturnService $returnService,
        AdminActivityService $activityService
    ) {
        $this->returnService = $returnService;
        $this->activityService = $activityService;
    }

    /**
     * Get all return requests with filters
     * ✅ FIX: Added eager loading to prevent N+1 queries
     */
    public function index(Request $request)
    {
        $query = OrderReturn::with([
            'user',
            'order',
            'items.orderItem.product.primaryImage', // ✅ Added eager loading
            'processedBy' // ✅ Added eager loading
        ])
        ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by reason
        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $returns = $query->paginate($request->get('per_page', 15));

        return OrderReturnResource::collection($returns);
    }

    /**
     * Get a specific return request
     */
    public function show(string $returnNumber)
    {
        $return = OrderReturn::where('return_number', $returnNumber)
            ->with([
                'user',
                'order.items.product',
                'items.orderItem.product.primaryImage',
                'images',
                'processedBy'
            ])
            ->firstOrFail();

        return new OrderReturnResource($return);
    }

    /**
     * Approve a return request
     */
    public function approve(Request $request, string $returnNumber): JsonResponse
    {
        $request->validate([
            'refund_method' => 'required|in:original_payment,store_credit,bank_transfer',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $admin = $request->user();
            $return = OrderReturn::where('return_number', $returnNumber)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$return->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be approved',
                ], 400);
            }

            $this->returnService->approveReturn($return, $admin, [
                'refund_method' => $request->refund_method,
                'admin_notes' => $request->admin_notes,
            ]);

            // Log activity
            $this->activityService->log(
                admin: $admin,
                action: 'return_approved',
                entityType: 'OrderReturn',
                entityId: $return->id,
                description: "Approved return request {$return->return_number} for order {$return->order->order_number}",
                metadata: [
                    'return_number' => $return->return_number,
                    'order_number' => $return->order->order_number,
                    'refund_amount' => (float) $return->refund_amount,
                    'refund_method' => $request->refund_method,
                ]
            );

            DB::commit();

            $return->load([
                'user',
                'order',
                'items.orderItem.product',
                'processedBy'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return request approved successfully',
                'data' => new OrderReturnResource($return),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a return request
     */
    public function reject(Request $request, string $returnNumber): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $admin = $request->user();
            $return = OrderReturn::where('return_number', $returnNumber)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$return->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be rejected',
                ], 400);
            }

            $this->returnService->rejectReturn($return, $admin, $request->admin_notes);

            // Log activity
            $this->activityService->log(
                admin: $admin,
                action: 'return_rejected',
                entityType: 'OrderReturn',
                entityId: $return->id,
                description: "Rejected return request {$return->return_number} for order {$return->order->order_number}",
                metadata: [
                    'return_number' => $return->return_number,
                    'order_number' => $return->order->order_number,
                    'reason' => $request->admin_notes,
                ]
            );

            DB::commit();

            $return->load([
                'user',
                'order',
                'items.orderItem.product',
                'processedBy'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return request rejected',
                'data' => new OrderReturnResource($return),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete a return (mark as completed after refund is processed)
     */
    public function complete(Request $request, string $returnNumber): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $admin = $request->user();
            $return = OrderReturn::where('return_number', $returnNumber)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($return->status, ['approved', 'processing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved or processing returns can be completed',
                ], 400);
            }

            $this->returnService->completeReturn($return, $admin, $request->admin_notes);

            // Log activity
            $this->activityService->log(
                admin: $admin,
                action: 'return_completed',
                entityType: 'OrderReturn',
                entityId: $return->id,
                description: "Completed return request {$return->return_number} for order {$return->order->order_number}",
                metadata: [
                    'return_number' => $return->return_number,
                    'order_number' => $return->order->order_number,
                    'refund_amount' => (float) $return->refund_amount,
                ]
            );

            DB::commit();

            $return->load([
                'user',
                'order',
                'items.orderItem.product',
                'processedBy'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return completed successfully',
                'data' => new OrderReturnResource($return),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get return statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $stats = $this->returnService->getStatistics($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
