<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Log an admin activity
     */
    public static function log(
        User $admin,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $metadata = null
    ): self {
        return self::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get recent activities
     */
    public static function recent(int $limit = 50)
    {
        return self::with('admin')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get activities for a specific entity
     */
    public static function forEntity(string $entityType, int $entityId)
    {
        return self::with('admin')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest()
            ->get();
    }
}
