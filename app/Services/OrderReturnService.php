<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderReturnService
{
    /**
     * Create a new return request
     */
    public function createReturn(User $user, array $data): OrderReturn
    {
        $order = Order::where('user_id', $user->id)
            ->where('order_number', $data['order_number'])
            ->firstOrFail();

        if (!$order->canBeReturned()) {
            throw new \Exception('This order is not eligible for return');
        }

        DB::beginTransaction();

        try {
            // Calculate refund amount
            $refundAmount = 0;
            foreach ($data['items'] as $itemData) {
                $orderItem = $order->items()->findOrFail($itemData['order_item_id']);

                if ($itemData['quantity'] > $orderItem->quantity) {
                    throw new \Exception("Invalid quantity for item {$orderItem->product_name}");
                }

                $refundAmount += $orderItem->product_price * $itemData['quantity'];
            }

            // Create return
            $return = OrderReturn::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'reason' => $data['reason'],
                'description' => $data['description'],
                'refund_amount' => $refundAmount,
            ]);

            // Create return items
            foreach ($data['items'] as $itemData) {
                $orderItem = $order->items()->findOrFail($itemData['order_item_id']);

                $return->items()->create([
                    'order_item_id' => $orderItem->id,
                    'quantity' => $itemData['quantity'],
                    'refund_amount' => $orderItem->product_price * $itemData['quantity'],
                    'item_condition' => $itemData['condition'] ?? null,
                ]);
            }

            // Handle image uploads
            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $path = $image->store('returns', 'public');
                    $return->images()->create(['image_path' => $path]);
                }
            }

            // Mark order as having a return
            $order->update(['has_return' => true]);

            DB::commit();

            return $return->load(['items.orderItem.product', 'images']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve a return request
     */
    public function approveReturn(OrderReturn $return, User $admin, array $data): OrderReturn
    {
        $return->update([
            'status' => 'approved',
            'refund_method' => $data['refund_method'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'processed_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        return $return;
    }

    /**
     * Reject a return request
     */
    public function rejectReturn(OrderReturn $return, User $admin, string $reason): OrderReturn
    {
        $return->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
            'processed_by_admin_id' => $admin->id,
            'rejected_at' => now(),
        ]);

        // Update order
        $return->order->update(['has_return' => false]);

        return $return;
    }

    /**
     * Complete a return (after refund is processed)
     */
    public function completeReturn(OrderReturn $return, User $admin, ?string $notes = null): OrderReturn
    {
        DB::beginTransaction();

        try {
            // Restore stock for returned items
            foreach ($return->items as $returnItem) {
                $product = $returnItem->orderItem->product;
                $product->increment('stock_quantity', $returnItem->quantity);
            }

            $return->update([
                'status' => 'completed',
                'admin_notes' => $notes ? ($return->admin_notes . "\n" . $notes) : $return->admin_notes,
                'processed_by_admin_id' => $admin->id,
                'completed_at' => now(),
            ]);

            DB::commit();

            return $return;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a return request (by customer)
     */
    public function cancelReturn(OrderReturn $return): OrderReturn
    {
        $return->update([
            'status' => 'cancelled',
        ]);

        $return->order->update(['has_return' => false]);

        return $return;
    }

    /**
     * Get return statistics
     */
    public function getStatistics(string $dateFrom, string $dateTo): array
    {
        $query = OrderReturn::whereBetween('created_at', [$dateFrom, $dateTo]);

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'total_returns' => $query->count(),
            'pending_returns' => (clone $query)->pending()->count(),
            'approved_returns' => (clone $query)->approved()->count(),
            'rejected_returns' => (clone $query)->rejected()->count(),
            'completed_returns' => (clone $query)->completed()->count(),
            'total_refund_amount' => (clone $query)->completed()->sum('refund_amount'),
            'returns_by_reason' => (clone $query)
                ->select('reason', DB::raw('COUNT(*) as count'))
                ->groupBy('reason')
                ->get(),
            'average_processing_time' => (clone $query)
                ->whereNotNull('completed_at')
                ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, completed_at)')),
        ];
    }
}
