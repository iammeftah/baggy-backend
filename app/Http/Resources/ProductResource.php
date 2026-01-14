<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->when(
                strlen($this->description) > 150,
                substr($this->description, 0, 150) . '...',
                $this->description
            ),
            'price' => (string) number_format($this->price, 2, '.', ''),
            'buying_price' => (string) number_format($this->buying_price, 2, '.', ''),
            'selling_price' => (string) number_format($this->selling_price, 2, '.', ''),
            'profit_margin' => (string) number_format($this->profit_margin, 2, '.', ''),
            'profit' => (string) number_format($this->profit, 2, '.', ''),
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'is_active' => $this->is_active,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'primary_image' => $this->when(
                $this->relationLoaded('primaryImage') && $this->primaryImage,
                function () {
                    return [
                        'id' => $this->primaryImage->id,
                        'image_path' => $this->primaryImage->image_path,
                        'url' => $this->primaryImage->url,
                        'is_primary' => true,
                    ];
                }
            ),
            'images' => $this->when(
                $this->relationLoaded('images'),
                $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url,
                        'is_primary' => $image->is_primary,
                    ];
                })
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
