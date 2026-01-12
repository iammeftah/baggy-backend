<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
    ];

    protected $appends = ['image_url'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::deleting(function ($category) {
            if ($category->image_path && Storage::disk('public')->exists($category->image_path)) {
                Storage::disk('public')->delete($category->image_path);
            }
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts()
    {
        return $this->hasMany(Product::class)->where('is_active', true);
    }

   public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        // Check if Cloudinary is configured and working
        try {
            if (config('filesystems.disks.cloudinary.cloud.cloud_name')) {
                return Storage::disk('cloudinary')->url($this->image_path);
            }
        } catch (\Exception $e) {
            \Log::error('Cloudinary error in Category', [
                'error' => $e->getMessage(),
                'path' => $this->image_path
            ]);
        }

        // Fallback to public disk
        return Storage::disk('public')->url($this->image_path);
    }

    // REMOVE this method - let it use ID by default
    // public function getRouteKeyName()
    // {
    //     return 'slug';
    // }
}
