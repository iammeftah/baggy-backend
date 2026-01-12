<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'total_amount' => (string) number_format($this->total_amount, 2, '.', ''),
            'shipping_address' => $this->shipping_address,
            'shipping_city' => $this->shipping_city,
            'shipping_phone' => $this->shipping_phone,
            'notes' => $this->notes,

            // User/Customer Information
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'full_name' => $this->user->first_name . ' ' . $this->user->last_name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ];
            }),

            // Order Items
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    $productData = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'product_price' => (string) number_format($item->product_price, 2, '.', ''),
                        'quantity' => $item->quantity,
                        'subtotal' => (string) number_format($item->subtotal, 2, '.', ''),
                    ];

                    // Check if product relationship is loaded
                    if ($item->relationLoaded('product') && $item->product) {
                        $productData['product'] = [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'slug' => $item->product->slug,
                            'primary_image' => null,
                        ];

                        // Check if primaryImage relationship is loaded
                        if ($item->product->relationLoaded('primaryImage') && $item->product->primaryImage) {
                            $productData['product']['primary_image'] = [
                                'id' => $item->product->primaryImage->id,
                                'url' => $item->product->primaryImage->url,
                                'image_path' => $item->product->primaryImage->image_path,
                            ];
                        }
                    }

                    return $productData;
                });
            }),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
