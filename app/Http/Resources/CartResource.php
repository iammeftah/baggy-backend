<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Calculate total and items
        $items = $this->items->map(function ($item) {
            $product = $item->product;
            $primaryImage = $product->images->firstWhere('is_primary', true)
                ?? $product->images->first();

            return [
                'id' => $item->id,
                'cart_id' => $item->cart_id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'subtotal' => (float) ($product->price * $item->quantity),
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (string) number_format($product->price, 2, '.', ''),
                    'stock_quantity' => $product->stock_quantity,
                    'is_active' => $product->is_active,
                    'primary_image' => $primaryImage ? [
                        'id' => $primaryImage->id,
                        'url' => $primaryImage->url,
                        'is_primary' => $primaryImage->is_primary,
                    ] : null,
                    'images' => $product->images->map(function ($img) {
                        return [
                            'id' => $img->id,
                            'url' => $img->url,
                            'is_primary' => $img->is_primary,
                        ];
                    }),
                ],
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        $total = $items->sum('subtotal');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => $items,
            'total' => $total,
            'total_items' => $items->sum('quantity'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
