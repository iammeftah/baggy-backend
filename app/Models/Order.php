<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'total_amount',
        'shipping_address',
        'shipping_city',
        'shipping_phone',
        'notes',
        'is_returnable',
        'has_return',
        'return_deadline',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'is_returnable' => 'boolean',
        'has_return' => 'boolean',
        'return_deadline' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate order number automatically when creating
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Generate a unique order number.
     *
     * @return string
     */
    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', now())->count() + 1;
        return sprintf('ORD-%s-%04d', $date, $count);
    }

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for the order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include shipping orders.
     */
    public function scopeShipping($query)
    {
        return $query->where('status', 'shipping');
    }

    /**
     * Scope a query to only include delivered orders.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope a query to search orders.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('order_number', 'like', "%{$search}%")
            ->orWhere('shipping_phone', 'like', "%{$search}%")
            ->orWhereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
    }

    /**
     * Update order status.
     *
     * @param string $status
     * @return bool
     */
    public function updateStatus(string $status): bool
    {
        if (!in_array($status, ['pending', 'shipping', 'delivered'])) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    /**
     * Check if order is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if order is shipping.
     *
     * @return bool
     */
    public function isShipping(): bool
    {
        return $this->status === 'shipping';
    }

    /**
     * Check if order is delivered.
     *
     * @return bool
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'order_number';
    }

    /**
     * Get the return for the order.
     */
    public function return()
    {
        return $this->hasOne(OrderReturn::class);
    }

    /**
     * Check if order can be returned
     * ✅ FIX: Added fallback logic for orders with null return_deadline
     */
    public function canBeReturned(): bool
    {
        // Can only return delivered orders
        if ($this->status !== 'delivered') {
            return false;
        }

        // Check if already has a return
        if ($this->has_return) {
            return false;
        }

        // Check if order is returnable
        if (!$this->is_returnable) {
            return false;
        }

        // ✅ FIX: Handle both set deadlines and orders delivered before feature was added
        if (!$this->return_deadline) {
            // Fallback: Orders without deadline should use updated_at + 30 days
            // This handles orders delivered before the return feature was added
            $estimatedDeliveryDate = $this->updated_at;
            $fallbackDeadline = $estimatedDeliveryDate->copy()->addDays(30);

            if (now()->greaterThan($fallbackDeadline)) {
                return false;
            }
        } else {
            // Use the explicitly set deadline
            if (now()->greaterThan($this->return_deadline)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set return deadline when order is delivered
     * ✅ This is called when order status changes to 'delivered'
     */
    public function setReturnDeadline(int $days = 30): void
    {
        $this->return_deadline = now()->addDays($days);
        $this->save();
    }

    /**
     * Get days remaining for return
     *
     * @return int|null
     */
    public function getDaysRemainingForReturn(): ?int
    {
        if (!$this->return_deadline) {
            // Use fallback logic
            $estimatedDeliveryDate = $this->updated_at;
            $fallbackDeadline = $estimatedDeliveryDate->copy()->addDays(30);
            return now()->diffInDays($fallbackDeadline, false);
        }

        return now()->diffInDays($this->return_deadline, false);
    }
}
