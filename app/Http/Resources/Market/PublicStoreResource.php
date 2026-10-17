<?php

namespace App\Http\Resources\Market;

use App\Models\Branch;
use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What customers may know about a store: location, contact, hours, pickup. */
class PublicStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $distance = isset($this->distance_km) && $this->distance_km !== null ? (float) $this->distance_km : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'seller' => $this->whenLoaded('merchant', fn () => ['id' => $this->merchant->id, 'name' => $this->merchant->business_name]),
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'pickup_instructions' => $this->pickup_instructions,
            'opening_hours' => $this->opening_hours,
            'opening_hours_lines' => $this->opening_hours ? $this->openingHoursLines() : null,
            'distance_km' => $distance !== null ? round($distance, 2) : null,
            'distance_label' => GeoPoint::formatKm($distance),
            'offers_count' => $this->when(isset($this->offers_count), fn () => (int) $this->offers_count),
            'maps_url' => "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}",
        ];
    }
}
