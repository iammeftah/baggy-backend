<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images']);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->latest()->paginate($request->get('per_page', 15));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            // Check if Cloudinary is configured
            if (!config('filesystems.disks.cloudinary.cloud_name')) {
                throw new \Exception('Cloudinary is not configured properly');
            }

            DB::beginTransaction();

            // Create the product
            $product = Product::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'specifications' => $request->specifications,
                'price' => $request->price,
                'stock_quantity' => $request->stock_quantity,
                'is_active' => $request->get('is_active', true),
            ]);

            Log::info('Product created', ['product_id' => $product->id, 'name' => $product->name]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $uploadedFiles = $request->file('images');

                Log::info('Storing product images', [
                    'product_id' => $product->id,
                    'images_count' => count($uploadedFiles)
                ]);

                foreach ($uploadedFiles as $index => $file) {
                    try {
                        // Store image in Cloudinary
                        $path = $file->store('products', 'cloudinary');

                        // Create image record
                        $product->images()->create([
                            'image_path' => $path,
                            'is_primary' => $index === 0,
                            'display_order' => $index + 1,
                        ]);
                    } catch (\Exception $imageError) {
                        Log::error('Image upload failed', [
                            'product_id' => $product->id,
                            'image_index' => $index,
                            'error' => $imageError->getMessage()
                        ]);
                        throw $imageError;
                    }
                }

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

            $product->update([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'specifications' => $request->specifications,
                'price' => $request->price,
                'stock_quantity' => $request->stock_quantity,
                'is_active' => $request->get('is_active', $product->is_active),
            ]);

            // Handle new image uploads
            if ($request->hasFile('images')) {
                $uploadedFiles = $request->file('images');

                Log::info('Adding new images to product', [
                    'product_id' => $product->id,
                    'new_images_count' => count($uploadedFiles)
                ]);

                $currentMaxOrder = $product->images()->max('display_order') ?? 0;

                foreach ($uploadedFiles as $index => $file) {
                    $path = $file->store('products', 'cloudinary');

                    $product->images()->create([
                        'image_path' => $path,
                        'is_primary' => false, // Don't change primary when updating
                        'display_order' => $currentMaxOrder + $index + 1,
                    ]);
                }
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
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    public function toggleStatus(Product $product): JsonResponse
    {
        $product->update([
            'is_active' => !$product->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated',
            'data' => [
                'is_active' => $product->is_active,
            ],
        ]);
    }
}
