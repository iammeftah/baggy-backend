<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private ?Cloudinary $cloudinary = null;
    private bool $isConfigured = false;

    public function __construct()
    {
        // Get config values
        $cloudName = config('services.cloudinary.cloud_name', env('CLOUDINARY_CLOUD_NAME'));
        $apiKey = config('services.cloudinary.api_key', env('CLOUDINARY_API_KEY'));
        $apiSecret = config('services.cloudinary.api_secret', env('CLOUDINARY_API_SECRET'));

        // Check if all required config values are present
        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            Log::warning('Cloudinary is not configured. Missing credentials.', [
                'has_cloud_name' => !empty($cloudName),
                'has_api_key' => !empty($apiKey),
                'has_api_secret' => !empty($apiSecret),
            ]);
            $this->isConfigured = false;
            return;
        }

        try {
            // Try initializing with cloudinary:// URL format first
            $cloudinaryUrl = sprintf(
                'cloudinary://%s:%s@%s',
                $apiKey,
                $apiSecret,
                $cloudName
            );

            $this->cloudinary = new Cloudinary($cloudinaryUrl);
            $this->isConfigured = true;

            Log::info('Cloudinary configured successfully', [
                'cloud_name' => $cloudName
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to configure Cloudinary', [
                'error' => $e->getMessage()
            ]);
            $this->isConfigured = false;
        }
    }

    public function upload($file, string $folder = 'products', array $options = []): array
    {
        if (!$this->isConfigured || !$this->cloudinary) {
            throw new \Exception('Cloudinary is not configured. Please check your environment variables.');
        }

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
        if (!$this->isConfigured || !$this->cloudinary) {
            Log::warning('Attempted to delete from Cloudinary but it is not configured', [
                'public_id' => $publicId
            ]);
            return [
                'success' => false,
                'result' => 'not_configured',
            ];
        }

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
        if (!$this->isConfigured || !$this->cloudinary) {
            Log::warning('Attempted to generate Cloudinary URL but it is not configured', [
                'public_id' => $publicId
            ]);
            return '';
        }

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
        $cloudName = config('services.cloudinary.cloud_name', env('CLOUDINARY_CLOUD_NAME'));
        $apiKey = config('services.cloudinary.api_key', env('CLOUDINARY_API_KEY'));
        $apiSecret = config('services.cloudinary.api_secret', env('CLOUDINARY_API_SECRET'));

        return !empty($cloudName) && !empty($apiKey) && !empty($apiSecret);
    }

    public function isReady(): bool
    {
        return $this->isConfigured;
    }
}
