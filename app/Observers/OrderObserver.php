<?php
// app/Observers/OrderObserver.php

namespace App\Observers;

use App\Models\Order;
use App\Models\User;
use App\Mail\NewOrderNotification;
use App\Mail\OrderStatusChanged;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        Log::info("New order created: {$order->order_number}");

        try {
            // Load relationships
            $order->load(['user', 'items']);

            // Get all admin users
            $admins = User::where('role', 'admin')->get();

            // Send email to all admins
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new NewOrderNotification($order));
            }

            Log::info("New order notification sent to admins for order: {$order->order_number}");
        } catch (\Exception $e) {
            Log::error("Failed to send new order notification: " . $e->getMessage());
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if status changed
        if ($order->isDirty('status')) {
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            Log::info("Order {$order->order_number} status changed from {$oldStatus} to: {$newStatus}");

            try {
                // Load relationships
                $order->load(['user', 'items']);

                // Send status update email to customer
                Mail::to($order->user->email)->send(new OrderStatusChanged($order, $oldStatus));

                Log::info("Order status notification sent to customer for order: {$order->order_number}");
            } catch (\Exception $e) {
                Log::error("Failed to send order status notification: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        Log::info("Order deleted: {$order->order_number}");
    }
}
