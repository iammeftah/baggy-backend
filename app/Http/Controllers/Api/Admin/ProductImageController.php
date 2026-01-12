<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductImageController extends Controller
{
    public function store(UploadImageRequest $request, Product $product): JsonResponse
    {
        Log::info('=== IMAGE UPLOAD STARTED ===');
        Log::info('Product ID: ' . $product->id);
        Log::info('Product Name: ' . $product->name);

        try {
            if (!$request->hasFile('image')) {
                Log::error('NO IMAGE FILE IN REQUEST');
                return response()->json([
                    'success' => false,
                    'message' => 'No image file provided',
                ], 400);
            }

            $file = $request->file('image');
            Log::info('File name: ' . $file->getClientOriginalName());
            Log::info('File size: ' . $file->getSize() . ' bytes');
            Log::info('File mime: ' . $file->getMimeType());

            if (!$file->isValid()) {
                Log::error('INVALID IMAGE FILE');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image file',
                ], 400);
            }

            // Store image in public disk
            $path = $file->store('products', 'cloudinary');
            Log::info('Image stored at path: ' . $path);

            // Determine display order
            $maxOrder = $product->images()->max('display_order') ?? 0;

            // Check if this is the first image
            $isFirstImage = $product->images()->count() === 0;

            // Create image record
            $image = $product->images()->create([
                'image_path' => $path,
                'is_primary' => $isFirstImage, // First image is automatically primary
                'display_order' => $maxOrder + 1,
            ]);

            Log::info('Image uploaded successfully! ID: ' . $image->id);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => Storage::url($image->image_path),
                    'is_primary' => $image->is_primary,
                    'display_order' => $image->display_order,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('IMAGE UPLOAD EXCEPTION: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(ProductImage $image): JsonResponse
    {
        try {
            // Delete file from storage
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }

            $image->delete();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function setPrimary(ProductImage $image): JsonResponse
    {
        try {
            // Remove primary flag from other images
            ProductImage::where('product_id', $image->product_id)
                ->where('id', '!=', $image->id)
                ->update(['is_primary' => false]);

            // Set this image as primary
            $image->update(['is_primary' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Primary image updated',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update primary image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
