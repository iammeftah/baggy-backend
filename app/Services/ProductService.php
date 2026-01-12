<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    protected $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'images', 'primaryImage'])
            ->where('is_active', true);

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $query->inStock();
        }

        $sortBy = $filters['sort'] ?? 'created_at';
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
            default:
                $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getProductBySlug(string $slug): ?Product
    {
        return Product::where('slug', $slug)
            ->with(['category', 'images', 'primaryImage'])
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function getAllProductsForAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'images']);

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function createProduct(array $data): Product
    {
        $product = Product::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'],
            'specifications' => $data['specifications'] ?? null,
            'price' => $data['price'],
            'category_id' => $data['category_id'] ?? null,
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $product->load(['category', 'images']);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update([
            'name' => $data['name'] ?? $product->name,
            'slug' => $data['slug'] ?? $product->slug,
            'description' => $data['description'] ?? $product->description,
            'specifications' => $data['specifications'] ?? $product->specifications,
            'price' => $data['price'] ?? $product->price,
            'category_id' => $data['category_id'] ?? $product->category_id,
            'stock_quantity' => $data['stock_quantity'] ?? $product->stock_quantity,
            'is_active' => $data['is_active'] ?? $product->is_active,
        ]);

        return $product->fresh(['category', 'images']);
    }

    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }

    public function toggleStatus(Product $product): Product
    {
        $product->update([
            'is_active' => !$product->is_active,
        ]);

        return $product;
    }

    public function getFeaturedProducts(int $limit = 8): Collection
    {
        return Product::active()
            ->inStock()
            ->with(['category', 'primaryImage'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
