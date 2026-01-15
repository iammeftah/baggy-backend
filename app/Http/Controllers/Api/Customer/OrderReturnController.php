<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateOrderReturnRequest;
use App\Http\Resources\OrderReturnResource;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\OrderReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    protected OrderReturnService $returnService;

    public function __construct(OrderReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    /**
     * Get all returns for the authenticated customer
     */
    public function index(Request $request)
    {
        $returns = OrderReturn::where('user_id', $request->user()->id)
            ->with(['order', 'items.orderItem.product.primaryImage'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return OrderReturnResource::collection($returns);
    }

    /**
     * Check if an order is eligible for return
     */
    public function checkEligibility(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $eligible = $order->canBeReturned();
        $reason = null;

        if (!$eligible) {
            if ($order->status !== 'delivered') {
                $reason = 'Order must be delivered before requesting a return';
            } elseif ($order->has_return) {
                $reason = 'A return request already exists for this order';
            } elseif (!$order->is_returnable) {
                $reason = 'This order is not eligible for returns';
            } elseif ($order->return_deadline && now()->greaterThan($order->return_deadline)) {
                $reason = 'Return deadline has passed';
            }
        }

        return response()->json([
            'success' => true,
            'eligible' => $eligible,
            'reason' => $reason,
            'return_deadline' => $order->return_deadline?->format('Y-m-d H:i:s'),
            'days_remaining' => $order->return_deadline ? now()->diffInDays($order->return_deadline, false) : null,
        ]);
    }

    /**
     * Create a new return request
     */
    public function store(CreateOrderReturnRequest $request): JsonResponse
    {
        try {
            $return = $this->returnService->createReturn(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Return request submitted successfully',
                'data' => new OrderReturnResource($return),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get a specific return request
     */
    public function show(Request $request, string $returnNumber)
    {
        $return = OrderReturn::where('user_id', $request->user()->id)
            ->where('return_number', $returnNumber)
            ->with([
                'order',
                'items.orderItem.product.primaryImage',
                'images',
                'processedBy'
            ])
            ->firstOrFail();

        return new OrderReturnResource($return);
    }

    /**
     * Cancel a pending return request
     */
    public function cancel(Request $request, string $returnNumber): JsonResponse
    {
        try {
            $return = OrderReturn::where('user_id', $request->user()->id)
                ->where('return_number', $returnNumber)
                ->firstOrFail();

            if (!$return->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be cancelled',
                ], 400);
            }

            $this->returnService->cancelReturn($return);

            return response()->json([
                'success' => true,
                'message' => 'Return request cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
