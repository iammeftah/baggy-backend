<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'specifications',
        'price',
        'buying_price',
        'selling_price',
        'profit_margin',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'buying_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }

            // Auto-calculate profit margin
            static::calculateProfitMargin($product);
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name, $product->id);
            }

            // Recalculate profit margin if prices changed
            if ($product->isDirty(['buying_price', 'selling_price'])) {
                static::calculateProfitMargin($product);
            }
        });
    }

    /**
     * Calculate profit margin percentage
     */
    protected static function calculateProfitMargin($product): void
    {
        if ($product->buying_price > 0) {
            $profit = $product->selling_price - $product->buying_price;
            $product->profit_margin = round(($profit / $product->buying_price) * 100, 2);
        } else {
            $product->profit_margin = 0;
        }
    }

    /**
     * Get the profit amount (not percentage)
     */
    public function getProfitAttribute(): float
    {
        return round($this->selling_price - $this->buying_price, 2);
    }

    /**
     * Get potential profit if all stock sold
     */
    public function getPotentialProfitAttribute(): float
    {
        return round($this->profit * $this->stock_quantity, 2);
    }

    protected static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
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

    protected static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = static::where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('display_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function hasStock(int $quantity): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    public function decreaseStock(int $quantity): bool
    {
        if ($this->hasStock($quantity)) {
            $this->decrement('stock_quantity', $quantity);
            return true;
        }
        return false;
    }

    public function increaseStock(int $quantity): void
    {
        $this->increment('stock_quantity', $quantity);
    }
}
