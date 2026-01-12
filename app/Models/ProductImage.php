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
        // Check if Cloudinary is properly configured
        if (!config('cloudinary.cloud_name')) {
            // Fallback to public disk or return a placeholder
            return Storage::disk('public')->url($this->image_path);
        }

        return Storage::disk('cloudinary')->url($this->image_path);
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
