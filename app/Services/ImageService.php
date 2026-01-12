<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ImageService
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Upload product image to Cloudinary.
     */
    public function uploadProductImage(Product $product, UploadedFile $file): ProductImage
    {
        if (!$this->isValidImage($file)) {
            throw new \Exception('Invalid image file.');
        }

        // Upload to Cloudinary
        $result = $this->cloudinary->upload(
            $file,
            'products/' . $product->id
        );

        Log::info('Image uploaded to Cloudinary', [
            'product_id' => $product->id,
            'public_id' => $result['public_id'],
            'url' => $result['secure_url']
        ]);

        $displayOrder = $product->images()->max('display_order') ?? 0;
        $displayOrder += 1;
        $isPrimary = $product->images()->count() === 0;

        $productImage = $product->images()->create([
            'image_path' => $result['public_id'],
            'is_primary' => $isPrimary,
            'display_order' => $displayOrder,
        ]);

        return $productImage;
    }

    protected function isValidImage(UploadedFile $file): bool
    {
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($file->getMimeType(), $allowedMimes);
    }

    public function uploadMultipleProductImages(Product $product, array $files): array
    {
        $uploadedImages = [];
        foreach ($files as $file) {
            try {
                $uploadedImages[] = $this->uploadProductImage($product, $file);
            } catch (\Exception $e) {
                Log::error("Failed to upload image: " . $e->getMessage());
            }
        }
        return $uploadedImages;
    }

    public function deleteProductImage(ProductImage $image): bool
    {
        $wasPrimary = $image->is_primary;
        $productId = $image->product_id;

        try {
            // Delete from Cloudinary
            $this->cloudinary->delete($image->image_path);
        } catch (\Exception $e) {
            Log::error('Failed to delete image from Cloudinary', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
        }

        $deleted = $image->delete();

        if ($deleted && $wasPrimary) {
            $newPrimary = ProductImage::where('product_id', $productId)->first();
            if ($newPrimary) {
                $newPrimary->setAsPrimary();
            }
        }

        return $deleted;
    }

    public function setAsPrimary(ProductImage $image): ProductImage
    {
        $image->setAsPrimary();
        return $image->fresh();
    }

    public function updateDisplayOrder(Product $product, array $imageIds): void
    {
        foreach ($imageIds as $order => $imageId) {
            ProductImage::where('product_id', $product->id)
                ->where('id', $imageId)
                ->update(['display_order' => $order]);
        }
    }
}
