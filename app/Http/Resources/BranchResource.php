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
            'name' => $this->name,
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
            'is_marketplace_enabled' => (bool) $this->is_marketplace_enabled,
            'status' => $this->status,
            'is_primary' => $this->is_primary,
            'product_count' => $this->inventory_items_count ?? $this->inventoryItems()->count(),
            'stock_value' => $summary['stockValue'],
            'selling_value' => $summary['sellingValue'],
            'expected_profit' => $summary['expectedProfit'],
        ];
    }
}
