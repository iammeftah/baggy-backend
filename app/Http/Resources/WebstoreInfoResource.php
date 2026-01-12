<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebstoreInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'contact' => [
                'email' => $this->email,
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp_number,
            ],
            'location' => [
                'address' => $this->address,
                'city' => $this->city,
                'country' => $this->country,
                'coordinates' => [
                    'latitude' => $this->latitude ? (float) $this->latitude : null,
                    'longitude' => $this->longitude ? (float) $this->longitude : null,
                ],
            ],
            'social_media' => [
                'instagram' => $this->instagram_url,
                'tiktok' => $this->tiktok_url,
                'facebook' => $this->facebook_url,
            ],
            'working_hours' => $this->working_hours,
            'logo_url' => $this->logo_url,
        ];
    }
}
