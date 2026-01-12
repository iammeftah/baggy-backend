<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($image) {
            if (Storage::disk('cloudinary')->exists($image->image_path)) {
                Storage::disk('cloudinary')->delete($image->image_path);
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        // Check if Cloudinary is configured and working
        try {
            // Verify Cloudinary config exists
            if (config('filesystems.disks.cloudinary.cloud.cloud_name')) {
                return Storage::disk('cloudinary')->url($this->image_path);
            }
        } catch (\Exception $e) {
            \Log::error('Cloudinary error in ProductImage', [
                'error' => $e->getMessage(),
                'path' => $this->image_path
            ]);
        }

        // Fallback to public disk
        return Storage::disk('public')->url($this->image_path);
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
