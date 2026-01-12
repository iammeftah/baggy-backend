<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomepageWebstoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Parse phone numbers - can be comma-separated
        $phones = $this->phone ? array_map('trim', explode(',', $this->phone)) : [];

        return [
            'store_name' => $this->store_name,
            'contact' => [
                'phones' => $phones,
                'email' => $this->email,
            ],
            'location' => [
                'coordinates' => [
                    'latitude' => $this->latitude ? (float) $this->latitude : null,
                    'longitude' => $this->longitude ? (float) $this->longitude : null,
                ],
            ],
            'social_media' => [
                'instagram' => $this->instagram_url,
                'tiktok' => $this->tiktok_url,
            ],
        ];
    }
}
