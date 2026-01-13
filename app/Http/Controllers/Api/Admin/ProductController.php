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
use App\Services\AdminActivityService;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected ProductService $productService;
    protected ImageService $imageService;
    protected AdminActivityService $activityService;

    public function __construct(
        ProductService $productService,
        ImageService $imageService,
        AdminActivityService $activityService
    ) {
        $this->productService = $productService;
        $this->imageService = $imageService;
        $this->activityService = $activityService;
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
        $admin = auth()->user();

        try {
            if (!CloudinaryService::isConfigured()) {
                Log::error('Cloudinary configuration missing');
                throw new \Exception('Cloudinary is not configured properly. Please check your environment variables.');
            }

            DB::beginTransaction();

            // Create the product using the service
            $product = $this->productService->createProduct($request->validated());

            // Set created_by_admin_id
            $product->update(['created_by_admin_id' => $admin->id]);

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

            // Log activity
            $this->activityService->logProductCreated($admin, $product);

            DB::commit();

            $product->load(['category', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => new ProductDetailResource($product),
                'activity_logged' => true,
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
        $admin = auth()->user();

        try {
            DB::beginTransaction();

            // Track changes
            $changes = [];
            foreach ($request->validated() as $key => $value) {
                if ($product->{$key} != $value) {
                    $changes[$key] = [
                        'old' => $product->{$key},
                        'new' => $value,
                    ];
                }
            }

            // Update product using the service
            $product = $this->productService->updateProduct($product, $request->validated());

            // Set updated_by_admin_id
            $product->update(['updated_by_admin_id' => $admin->id]);

            // Handle new image uploads
            if ($request->hasFile('images')) {
                $uploadedFiles = $request->file('images');

                Log::info('Adding new images to product', [
                    'product_id' => $product->id,
                    'new_images_count' => count($uploadedFiles)
                ]);

                $this->imageService->uploadMultipleProductImages($product, $uploadedFiles);
            }

            // Log activity if there were changes
            if (!empty($changes)) {
                $this->activityService->logProductUpdated($admin, $product, $changes);
            }

            DB::commit();

            $product->load(['category', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => new ProductDetailResource($product),
                'activity_logged' => !empty($changes),
                'changes' => $changes,
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
        $admin = auth()->user();

        DB::beginTransaction();

        try {
            // Store product data before deletion
            $productData = $product->toArray();

            $this->productService->deleteProduct($product);

            // Log activity
            $this->activityService->logProductDeleted($admin, (object)$productData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
                'activity_logged' => true,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleStatus(Product $product): JsonResponse
    {
        $admin = auth()->user();

        DB::beginTransaction();

        try {
            $oldStatus = $product->is_active;
            $product = $this->productService->toggleStatus($product);
            $product->update(['updated_by_admin_id' => $admin->id]);

            // Log activity
            $changes = [
                'is_active' => [
                    'old' => $oldStatus,
                    'new' => $product->is_active,
                ]
            ];
            $this->activityService->logProductUpdated($admin, $product, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product status updated',
                'data' => [
                    'is_active' => $product->is_active,
                ],
                'activity_logged' => true,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle product status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Adjust product stock with admin tracking
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $admin = auth()->user();

        $request->validate([
            'stock_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $oldStock = $product->stock_quantity;
            $newStock = $request->stock_quantity;

            $product->update([
                'stock_quantity' => $newStock,
                'updated_by_admin_id' => $admin->id,
            ]);

            // Log stock adjustment
            $this->activityService->logStockAdjustment(
                $admin,
                $product,
                $oldStock,
                $newStock,
                $request->reason
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'difference' => $newStock - $oldStock,
                ],
                'activity_logged' => true,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock: ' . $e->getMessage(),
            ], 500);
        }
    }
}
