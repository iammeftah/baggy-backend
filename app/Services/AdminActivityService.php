<?php

namespace App\Services;

use App\Models\AdminActivity;
use App\Models\User;

class AdminActivityService
{
    /**
     * Generic log method for any admin activity
     */
    public function log(User $admin, string $action, string $entityType, int $entityId, string $description, array $metadata = []): void
    {
        AdminActivity::log(
            admin: $admin,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            metadata: $metadata
        );
    }

    /**
     * Log order status change
     */
    public function logOrderStatusChange(User $admin, $order, string $oldStatus, string $newStatus): void
    {
        $description = "{$admin->first_name} {$admin->last_name} changed order #{$order->order_number} status from {$oldStatus} to {$newStatus}";

        AdminActivity::log(
            admin: $admin,
            action: 'status_changed',
            entityType: 'Order',
            entityId: $order->id,
            description: $description,
            metadata: [
                'order_number' => $order->order_number,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'order_total' => $order->total_amount,
                'customer_id' => $order->user_id,
                'customer_name' => $order->user->first_name . ' ' . $order->user->last_name,
            ]
        );
    }

    /**
     * Log product creation
     */
    public function logProductCreated(User $admin, $product): void
    {
        $description = "{$admin->first_name} {$admin->last_name} created product: {$product->name}";

        AdminActivity::log(
            admin: $admin,
            action: 'created',
            entityType: 'Product',
            entityId: $product->id,
            description: $description,
            metadata: [
                'product_name' => $product->name,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
                'category_id' => $product->category_id,
            ]
        );
    }

    /**
     * Log product update
     */
    public function logProductUpdated(User $admin, $product, array $changes): void
    {
        $description = "{$admin->first_name} {$admin->last_name} updated product: {$product->name}";

        AdminActivity::log(
            admin: $admin,
            action: 'updated',
            entityType: 'Product',
            entityId: $product->id,
            description: $description,
            metadata: [
                'product_name' => $product->name,
                'changes' => $changes,
            ]
        );
    }

    /**
     * Log product deletion
     */
    public function logProductDeleted(User $admin, $product): void
    {
        $description = "{$admin->first_name} {$admin->last_name} deleted product: {$product->name}";

        AdminActivity::log(
            admin: $admin,
            action: 'deleted',
            entityType: 'Product',
            entityId: $product->id,
            description: $description,
            metadata: [
                'product_name' => $product->name,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
            ]
        );
    }

    /**
     * Log product stock adjustment
     */
    public function logStockAdjustment(User $admin, $product, int $oldStock, int $newStock, string $reason): void
    {
        $difference = $newStock - $oldStock;
        $action = $difference > 0 ? 'increased' : 'decreased';

        $description = "{$admin->first_name} {$admin->last_name} {$action} stock for {$product->name} from {$oldStock} to {$newStock}. Reason: {$reason}";

        AdminActivity::log(
            admin: $admin,
            action: 'stock_adjusted',
            entityType: 'Product',
            entityId: $product->id,
            description: $description,
            metadata: [
                'product_name' => $product->name,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'difference' => $difference,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Log category operations
     */
    public function logCategoryOperation(User $admin, string $action, $category): void
    {
        $actions = [
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
        ];

        $actionText = $actions[$action] ?? $action;
        $description = "{$admin->first_name} {$admin->last_name} {$actionText} category: {$category->name}";

        AdminActivity::log(
            admin: $admin,
            action: $action,
            entityType: 'Category',
            entityId: $category->id,
            description: $description,
            metadata: [
                'category_name' => $category->name,
            ]
        );
    }

    /**
     * Log revenue collection (when order is marked as delivered)
     */
    public function logRevenueCollection(User $admin, $order): void
    {
        $description = "{$admin->first_name} {$admin->last_name} marked order #{$order->order_number} as delivered. Revenue collected: " . number_format($order->total_amount, 2);

        AdminActivity::log(
            admin: $admin,
            action: 'revenue_collected',
            entityType: 'Order',
            entityId: $order->id,
            description: $description,
            metadata: [
                'order_number' => $order->order_number,
                'amount' => $order->total_amount,
                'customer_id' => $order->user_id,
                'customer_name' => $order->user->first_name . ' ' . $order->user->last_name,
            ]
        );
    }

    /**
     * Get admin activity summary
     */
    public function getAdminSummary(User $admin, string $period = 'today'): array
    {
        $query = AdminActivity::where('admin_id', $admin->id);

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
        }

        $activities = $query->get();

        return [
            'total_actions' => $activities->count(),
            'orders_updated' => $activities->where('entity_type', 'Order')->count(),
            'products_modified' => $activities->where('entity_type', 'Product')->count(),
            'revenue_collected' => $activities->where('action', 'revenue_collected')
                ->sum(fn($activity) => $activity->metadata['amount'] ?? 0),
        ];
    }

}
