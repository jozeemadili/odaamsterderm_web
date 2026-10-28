<?php

namespace App\Http\Resources\Market;

use App\Models\Merchant;
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
            'seller' => $this->whenLoaded('merchant', fn () => [
                'id' => $this->merchant->id,
                'name' => $this->merchant->business_name,
                'logo_url' => $this->merchant->logo()?->getUrl(),
                'brand_color' => $this->merchant->brandColor(),
                'brand_accent_color' => $this->merchant->accentColor(),
                'brand_text_color' => Merchant::textColorOn($this->merchant->brandColor()),
            ]),
            'store_type' => $this->store_type,
            'address' => $this->address,
            'city' => $this->city,
            'area' => $this->area,
            'landmark' => $this->landmark,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'bio' => $this->bio,
            'bio_sw' => $this->bio_sw,
            'pickup_available' => (bool) $this->pickup_available,
            'cold_chain_capable' => (bool) $this->cold_chain_capable,
            'vet_advice_on_site' => (bool) $this->vet_advice_on_site,
            'delivery' => $this->offersDelivery() ? [
                'radius_km' => $this->delivery_radius_km,
                'base_fee' => $this->delivery_base_fee,
                'fee_per_km' => $this->delivery_fee_per_km,
                // Estimate to the customer's location, when known and in range.
                'fee_to_you' => $this->deliveryFeeFor($distance),
            ] : null,
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
