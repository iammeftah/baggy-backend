<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'specifications' => $this->specifications,
            'price' => (string) number_format($this->price, 2, '.', ''), // FIXED: No thousand separator
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'is_active' => $this->is_active,
            'category_id' => $this->category_id, // Add this for admin forms
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => $this->when(
                $this->relationLoaded('images'),
                $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_path' => $image->image_path,
                        'image_url' => $image->url,
                        'url' => $image->url,
                        'is_primary' => $image->is_primary,
                        'display_order' => $image->display_order,
                    ];
                })->sortBy('display_order')->values()
            ),
            'primary_image' => $this->when(
                $this->relationLoaded('primaryImage') && $this->primaryImage,
                function () {
                    return [
                        'id' => $this->primaryImage->id,
                        'image_path' => $this->primaryImage->image_path,
                        'image_url' => $this->primaryImage->url,
                        'url' => $this->primaryImage->url,
                        'is_primary' => true,
                        'display_order' => $this->primaryImage->display_order,
                    ];
                }
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
