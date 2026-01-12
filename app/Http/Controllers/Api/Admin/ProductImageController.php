<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProductImageController extends Controller
{
    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

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

            // Upload using ImageService
            $image = $this->imageService->uploadProductImage($product, $file);

            Log::info('Image uploaded successfully! ID: ' . $image->id);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => $image->url,
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
            $this->imageService->deleteProductImage($image);

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
            $this->imageService->setAsPrimary($image);

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
