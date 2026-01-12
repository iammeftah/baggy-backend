<?php

namespace App\Models;

use App\Services\CloudinaryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'is_primary',
        'display_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'display_order' => 'integer',
    ];

    protected $appends = ['url'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($image) {
            try {
                app(CloudinaryService::class)->delete($image->image_path);
            } catch (\Exception $e) {
                Log::error('Failed to delete image from Cloudinary', [
                    'image_id' => $image->id,
                    'path' => $image->image_path,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        try {
            if (CloudinaryService::isConfigured()) {
                $cloudinary = app(CloudinaryService::class);
                return $cloudinary->url($this->image_path);
            }
        } catch (\Exception $e) {
            Log::error('Cloudinary error in ProductImage', [
                'error' => $e->getMessage(),
                'path' => $this->image_path
            ]);
        }

        return '/images/placeholder.jpg';
    }

    public function setAsPrimary(): void
    {
        static::where('product_id', $this->product_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
