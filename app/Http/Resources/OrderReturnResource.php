<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'status' => $this->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->status)),
            'reason' => $this->reason,
            'reason_label' => ucfirst(str_replace('_', ' ', $this->reason)),
            'description' => $this->description,
            'refund_amount' => number_format($this->refund_amount, 2),
            'refund_amount_raw' => (float) $this->refund_amount,
            'refund_method' => $this->refund_method,
            'refund_method_label' => $this->refund_method ? ucfirst(str_replace('_', ' ', $this->refund_method)) : null,
            'admin_notes' => $this->admin_notes,

            // Order information
            'order' => $this->whenLoaded('order', [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'total_amount' => number_format($this->order->total_amount, 2),
            ]),

            // Customer information
            'customer' => $this->whenLoaded('user', [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),

            // Return items
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order_item_id' => $item->order_item_id,
                        'product_name' => $item->orderItem->product_name,
                        'product_price' => number_format($item->orderItem->product_price, 2),
                        'quantity' => $item->quantity,
                        'refund_amount' => number_format($item->refund_amount, 2),
                        'item_condition' => $item->item_condition,
                        'product' => $item->orderItem->product ? [
                            'id' => $item->orderItem->product->id,
                            'name' => $item->orderItem->product->name,
                            'slug' => $item->orderItem->product->slug,
                            'image_url' => $item->orderItem->product->primaryImage?->url,
                        ] : null,
                    ];
                });
            }),

            // Return images
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url,
                    ];
                });
            }),

            // Processed by admin
            'processed_by' => $this->whenLoaded('processedBy', function () {
                return $this->processedBy ? [
                    'id' => $this->processedBy->id,
                    'name' => $this->processedBy->first_name . ' ' . $this->processedBy->last_name,
                    'email' => $this->processedBy->email,
                ] : null;
            }),

            // Timestamps
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
