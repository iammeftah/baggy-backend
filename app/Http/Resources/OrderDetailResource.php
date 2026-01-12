<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'total_amount' => number_format($this->total_amount, 2),
            'total_amount_raw' => (float) $this->total_amount,
            'shipping_address' => $this->shipping_address,
            'shipping_city' => $this->shipping_city,
            'shipping_phone' => $this->shipping_phone,
            'notes' => $this->notes,
            'customer' => $this->when(
                $this->relationLoaded('user'),
                [
                    'id' => $this->user->id,
                    'name' => $this->user->first_name . ' ' . $this->user->last_name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ]
            ),
            'items' => $this->when(
                $this->relationLoaded('items'),
                $this->items->map(function ($item) {
                    $primaryImage = null;
                    if ($item->relationLoaded('product') && $item->product) {
                        $primaryImage = $item->product->images->where('is_primary', true)->first();
                    }

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'product_slug' => $item->product?->slug,
                        'product_price' => number_format($item->product_price, 2),
                        'product_price_raw' => (float) $item->product_price,
                        'quantity' => $item->quantity,
                        'subtotal' => number_format($item->subtotal, 2),
                        'subtotal_raw' => (float) $item->subtotal,
                        'image_url' => $primaryImage ? Storage::url($primaryImage->image_path) : null,
                    ];
                })
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
