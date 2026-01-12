<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\ImageService;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected ProductService $productService;
    protected ImageService $imageService;

    public function __construct(ProductService $productService, ImageService $imageService)
    {
        $this->productService = $productService;
        $this->imageService = $imageService;
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->search,
            'status' => $request->status,
            'category_id' => $request->category_id,
            'per_page' => $request->get('per_page', 15),
        ];

        $products = $this->productService->getAllProductsForAdmin($filters);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            if (!CloudinaryService::isConfigured()) {
                Log::error('Cloudinary configuration missing');
                throw new \Exception('Cloudinary is not configured properly. Please check your environment variables.');
            }

            DB::beginTransaction();

            // Create the product using the service
            $product = $this->productService->createProduct($request->validated());

            Log::info('Product created', ['product_id' => $product->id, 'name' => $product->name]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $uploadedFiles = $request->file('images');

                Log::info('Storing product images', [
                    'product_id' => $product->id,
                    'images_count' => count($uploadedFiles)
                ]);

                $this->imageService->uploadMultipleProductImages($product, $uploadedFiles);

                Log::info('Images stored successfully', [
                    'product_id' => $product->id,
                    'images_stored' => count($uploadedFiles)
                ]);
            }

            DB::commit();

            $product->load(['category', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => new ProductDetailResource($product),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Product $product)
    {
        $product->load(['category', 'images']);
        return new ProductDetailResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Update product using the service
            $product = $this->productService->updateProduct($product, $request->validated());

            // Handle new image uploads
            if ($request->hasFile('images')) {
                $uploadedFiles = $request->file('images');

                Log::info('Adding new images to product', [
                    'product_id' => $product->id,
                    'new_images_count' => count($uploadedFiles)
                ]);

                $this->imageService->uploadMultipleProductImages($product, $uploadedFiles);
            }

            DB::commit();

            $product->load(['category', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => new ProductDetailResource($product),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Product update failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->deleteProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    public function toggleStatus(Product $product): JsonResponse
    {
        $product = $this->productService->toggleStatus($product);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated',
            'data' => [
                'is_active' => $product->is_active,
            ],
        ]);
    }
}
