<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'reorder_level' => (float) $this->reorder_level,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_low_stock' => $this->isLowStock(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'is_expired' => $this->isExpired(),
            'image_url' => $this->image()?->getUrl(),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
        ];
    }
}
