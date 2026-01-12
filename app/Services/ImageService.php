<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Upload product image with WebP compression.
     */
    public function uploadProductImage(Product $product, UploadedFile $file): ProductImage
    {
        if (!$this->isValidImage($file)) {
            throw new \Exception('Invalid image file.');
        }

        $filename = time() . '_' . Str::random(10) . '.webp';
        $path = "uploads/products/{$product->id}";
        $fullPath = storage_path("app/public/{$path}");

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $this->convertToWebP($file->getRealPath(), $fullPath . '/' . $filename);

        $filePath = "{$path}/{$filename}";
        $displayOrder = $product->images()->max('display_order') ?? 0;
        $displayOrder += 1;
        $isPrimary = $product->images()->count() === 0;

        $productImage = $product->images()->create([
            'image_path' => $filePath,
            'is_primary' => $isPrimary,
            'display_order' => $displayOrder,
        ]);

        return $productImage;
    }

    protected function convertToWebP(string $source, string $destination, int $quality = 85): bool
    {
        $info = getimagesize($source);

        if ($info === false) {
            throw new \Exception('Could not read image file');
        }

        $image = false;

        switch ($info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($source);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($source);
                break;
            default:
                throw new \Exception('Unsupported image type');
        }

        if ($image === false) {
            throw new \Exception('Failed to create image resource');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > 1200) {
            $newWidth = 1200;
            $newHeight = (int) ($height * ($newWidth / $width));

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);

            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }

        $result = imagewebp($image, $destination, $quality);
        imagedestroy($image);

        if (!$result) {
            throw new \Exception('Failed to convert image to WebP');
        }

        return true;
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
                \Log::error("Failed to upload image: " . $e->getMessage());
            }
        }
        return $uploadedImages;
    }

    public function deleteProductImage(ProductImage $image): bool
    {
        $wasPrimary = $image->is_primary;
        $productId = $image->product_id;

        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
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
