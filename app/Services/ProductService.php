<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Get all products for admin with filters and pagination
     */
    public function getAllProductsForAdmin(array $filters): LengthAwarePaginator
    {
        $query = Product::with(['category', 'images'])
            ->withCount('images');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('specifications', 'like', "%{$search}%")
                    ->orWhereHas('category', function (Builder $catQuery) use ($search) {
                        $catQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Apply status filter (is_active)
        if (isset($filters['is_active']) && $filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        // Apply stock filter
        if (isset($filters['in_stock'])) {
            if ($filters['in_stock']) {
                $query->where('stock_quantity', '>', 0);
            } else {
                $query->where('stock_quantity', '=', 0);
            }
        }

        // Apply sorting
        $sortBy = $filters['sort'] ?? 'newest';
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate results
        $perPage = $filters['per_page'] ?? 10;
        return $query->paginate($perPage);
    }

    /**
     * Get all products for public (customers)
     */
    public function getAllProducts(array $filters): LengthAwarePaginator
    {
        $query = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0);

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Apply sorting
        $sortBy = $filters['sort'] ?? 'newest';
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $perPage = $filters['per_page'] ?? 12;
        return $query->paginate($perPage);
    }

    /**
     * Create a new product
     */
    public function createProduct(array $data): Product
    {
        $data['slug'] = Str::slug($data['name']);

        // Ensure unique slug
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Product::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        return Product::create($data);
    }

    /**
     * Update a product
     */
    public function updateProduct(Product $product, array $data): Product
    {
        // Update slug if name changed
        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = Str::slug($data['name']);

            // Ensure unique slug (excluding current product)
            $originalSlug = $data['slug'];
            $counter = 1;
            while (Product::where('slug', $data['slug'])
                ->where('id', '!=', $product->id)
                ->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $product->update($data);
        return $product->fresh();
    }

    /**
     * Delete a product
     */
    public function deleteProduct(Product $product): bool
    {
        // Delete associated images
        foreach ($product->images as $image) {
            $image->delete();
        }

        return $product->delete();
    }

    /**
     * Toggle product status
     */
    public function toggleStatus(Product $product): Product
    {
        $product->update(['is_active' => !$product->is_active]);
        return $product->fresh();
    }

    /**
     * Get product by slug
     */
    public function getProductBySlug(string $slug): ?Product
    {
        return Product::with(['category', 'images'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
