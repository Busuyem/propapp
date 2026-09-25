<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Listing
 */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'address' => $this->address,
            'price' => (float) $this->price,
            'bedrooms' => $this->bedrooms,
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            // Only a radius search selects this column.
            'distance_km' => $this->whenHas('distance_km', fn ($distance): float => round((float) $distance, 2)),
            'agent_id' => $this->agent_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
