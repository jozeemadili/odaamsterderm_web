<?php

namespace App\Http\Resources\Market;

use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One seller's offer of a product. Customers see the marketplace quantity,
 * never the seller's physical stock.
 */
class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $distance = $this->distance_km !== null ? (float) $this->distance_km : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->product?->displayName() ?? $this->name,
            'producer' => $this->product?->makerSummary(),
            'price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'unit' => $this->product?->unit ?? $this->unit,
            'available_quantity' => (bool) $this->is_available ? (float) $this->marketplace_quantity : 0.0,
            'is_available' => (bool) $this->is_available,
            'status_label' => (bool) $this->is_available ? 'Available' : 'Out of stock',
            'distance_km' => $distance !== null ? round($distance, 2) : null,
            'distance_label' => GeoPoint::formatKm($distance),
            // The seller's own photo first, so customers see what this store has.
            'image_url' => $this->photoUrl(),
            'image_urls' => $this->photoUrls(),
            'seller' => [
                'id' => $this->merchant->id,
                'name' => $this->merchant->business_name,
            ],
            'store' => new PublicStoreResource($this->whenLoaded('branch')),
        ];
    }
}
