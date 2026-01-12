<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key' => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->cloudinary = new Cloudinary();
    }

    public function upload($file, string $folder = 'products', array $options = []): array
    {
        try {
            $defaultOptions = [
                'folder' => $folder,
                'resource_type' => 'auto',
                'quality' => 'auto',
                'fetch_format' => 'auto',
            ];

            $uploadOptions = array_merge($defaultOptions, $options);

            if (is_object($file) && method_exists($file, 'getRealPath')) {
                $file = $file->getRealPath();
            }

            $result = $this->cloudinary->uploadApi()->upload($file, $uploadOptions);

            Log::info('Cloudinary upload successful', [
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
            ]);

            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'url' => $result['url'],
                'format' => $result['format'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'folder' => $folder,
            ]);

            throw new \Exception('Failed to upload to Cloudinary: ' . $e->getMessage());
        }
    }

    public function delete(string $publicId): array
    {
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId);

            Log::info('Cloudinary delete successful', [
                'public_id' => $publicId,
                'result' => $result['result'],
            ]);

            return [
                'success' => true,
                'result' => $result['result'],
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId,
            ]);

            throw new \Exception('Failed to delete from Cloudinary: ' . $e->getMessage());
        }
    }

    public function url(string $publicId, array $transformations = []): string
    {
        try {
            $url = $this->cloudinary->image($publicId)->toUrl();
            return $url;
        } catch (\Exception $e) {
            Log::error('Failed to generate Cloudinary URL', [
                'error' => $e->getMessage(),
                'public_id' => $publicId,
            ]);

            return '';
        }
    }

    public static function isConfigured(): bool
    {
        return !empty(env('CLOUDINARY_CLOUD_NAME')) &&
               !empty(env('CLOUDINARY_API_KEY')) &&
               !empty(env('CLOUDINARY_API_SECRET'));
    }
}
