<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['image_url'];

    /**
     * Boot method for model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug when creating a category
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // Auto-update slug when updating category name
        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // Delete image when category is deleted
        static::deleting(function ($category) {
        if ($category->image_path) {
            try {
                if (env('CLOUDINARY_CLOUD_NAME')) {
                    $cloudinary = app(\App\Services\CloudinaryService::class);
                    $cloudinary->delete($category->image_path);
                    Log::info('Category image deleted from Cloudinary', [
                        'category_id' => $category->id,
                        'path' => $category->image_path
                    ]);
                } elseif (Storage::disk('public')->exists($category->image_path)) {
                    Storage::disk('public')->delete($category->image_path);
                    Log::info('Category image deleted from public disk', [
                        'category_id' => $category->id,
                        'path' => $category->image_path
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete category image', [
                    'category_id' => $category->id,
                    'path' => $category->image_path,
                    'error' => $e->getMessage()
                ]);
            }
        }
    });
    }

    /**
     * Relationships
     */

    /**
     * Get all products for this category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get only active products for this category.
     */
    public function activeProducts()
    {
        return $this->hasMany(Product::class)->where('is_active', true);
    }

    /**
     * Accessors & Mutators
     */

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        try {
            // Check if Cloudinary is configured
            if (env('CLOUDINARY_CLOUD_NAME')) {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                return $cloudinary->url($this->image_path);
            }
        } catch (\Exception $e) {
            Log::error('Cloudinary error in Category', [
                'category_id' => $this->id,
                'error' => $e->getMessage(),
                'path' => $this->image_path
            ]);
        }

        // Fallback to public disk
        return Storage::disk('public')->exists($this->image_path)
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    /**
     * Scopes
     */

    /**
     * Scope to get categories with active products count.
     */
    public function scopeWithActiveProductsCount($query)
    {
        return $query->withCount(['products as active_products_count' => function ($q) {
            $q->where('is_active', true);
        }]);
    }

    /**
     * Scope to get only categories that have active products.
     */
    public function scopeHasActiveProducts($query)
    {
        return $query->whereHas('products', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Helper Methods
     */

    /**
     * Check if category has any products.
     */
    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    /**
     * Check if category has any active products.
     */
    public function hasActiveProducts(): bool
    {
        return $this->activeProducts()->exists();
    }

    /**
     * Get the count of active products.
     */
    public function getActiveProductsCountAttribute(): int
    {
        return $this->activeProducts()->count();
    }

    /**
     * Upload and store category image.
     */
    public function uploadImage($file): bool
    {
        try {
            // Delete old image if exists
            if ($this->image_path) {
                if (env('CLOUDINARY_CLOUD_NAME')) {
                    $cloudinary = app(\App\Services\CloudinaryService::class);
                    $cloudinary->delete($this->image_path);
                } else {
                    Storage::disk('public')->delete($this->image_path);
                }
            }

            // Upload new image
            if (env('CLOUDINARY_CLOUD_NAME')) {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                $path = $cloudinary->upload($file, 'categories');
            } else {
                $path = $file->store('categories', 'public');
            }

            if ($path) {
                $this->image_path = $path;
                $this->save();

                Log::info('Category image uploaded', [
                    'category_id' => $this->id,
                    'path' => $path
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to upload category image', [
                'category_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete category image.
     */
    public function deleteImage(): bool
    {
        if (!$this->image_path) {
            return false;
        }

        try {
            if (env('CLOUDINARY_CLOUD_NAME')) {
                $cloudinary = app(\App\Services\CloudinaryService::class);
                $cloudinary->delete($this->image_path);
            } else {
                Storage::disk('public')->delete($this->image_path);
            }

            $this->image_path = null;
            $this->save();

            Log::info('Category image deleted', ['category_id' => $this->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete category image', [
                'category_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate a unique slug for the category.
     */
    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::slugExists($slug, $ignoreId)) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    /**
     * Check if slug already exists.
     */
    protected static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = static::where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
