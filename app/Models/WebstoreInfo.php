<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WebstoreInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_name',
        'store_description',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'instagram_url',
        'tiktok_url',
        'facebook_url',
        'whatsapp_number',
        'working_hours',
        'logo_path',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    /**
     * Get the logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }
        return Storage::url($this->logo_path);
    }

    /**
     * Get the active webstore info (singleton pattern)
     */
    public static function getActive()
    {
        return static::where('is_active', true)->first() ?? static::first();
    }

    /**
     * Get coordinates as array
     */
    public function getCoordinatesAttribute(): ?array
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        return [
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
        ];
    }

    /**
     * Get social media links
     */
    public function getSocialLinksAttribute(): array
    {
        return array_filter([
            'instagram' => $this->instagram_url,
            'tiktok' => $this->tiktok_url,
            'facebook' => $this->facebook_url,
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($info) {
            if ($info->logo_path && Storage::disk('public')->exists($info->logo_path)) {
                Storage::disk('public')->delete($info->logo_path);
            }
        });
    }
}
