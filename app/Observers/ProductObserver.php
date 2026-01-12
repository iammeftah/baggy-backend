<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        \Log::info("New product created: {$product->name}");
    }

    public function updated(Product $product): void
    {
        if ($product->isDirty('stock_quantity')) {
            \Log::info("Product {$product->name} stock updated to: {$product->stock_quantity}");

            if ($product->stock_quantity < 5) {
                \Log::warning("Low stock alert for product: {$product->name}");
            }
        }
    }

    public function deleting(Product $product): void
    {
        // Delete associated images before product is deleted
        $product->images()->delete();
    }

    public function deleted(Product $product): void
    {
        \Log::info("Product deleted: {$product->name}");
    }
}
