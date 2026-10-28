<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->stockSummary();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'short_code' => $this->short_code,
            'name' => $this->name,
            'store_type' => $this->store_type,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'region_id' => $this->region_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'has_location' => $this->hasLocation(),
            'pickup_instructions' => $this->pickup_instructions,
            'opening_hours' => $this->opening_hours,
            'area' => $this->area,
            'landmark' => $this->landmark,
            'whatsapp' => $this->whatsapp,
            'manager_name' => $this->manager_name,
            'delivery_radius_km' => $this->delivery_radius_km,
            'delivery_base_fee' => $this->delivery_base_fee,
            'delivery_fee_per_km' => $this->delivery_fee_per_km,
            'offers_delivery' => $this->offersDelivery(),
            'pickup_available' => (bool) $this->pickup_available,
            'cold_chain_capable' => (bool) $this->cold_chain_capable,
            'vet_advice_on_site' => (bool) $this->vet_advice_on_site,
            'bio' => $this->bio,
            'bio_sw' => $this->bio_sw,
            'is_marketplace_enabled' => (bool) $this->is_marketplace_enabled,
            'status' => $this->status,
            'suspension_reason' => $this->suspension_reason,
            'is_primary' => $this->is_primary,
            'product_count' => $this->inventory_items_count ?? $this->inventoryItems()->count(),
            'stock_value' => $summary['stockValue'],
            'selling_value' => $summary['sellingValue'],
            'expected_profit' => $summary['expectedProfit'],
        ];
    }
}
