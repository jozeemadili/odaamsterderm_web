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
            'back_image_url' => $this->backImage()?->getUrl(),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => $this->product ? new ProductResource($this->product) : null),
            'marketplace' => [
                'is_visible' => (bool) $this->is_marketplace_visible,
                'quantity' => (float) $this->marketplace_quantity,
                'reserved_quantity' => (float) $this->reserved_quantity,
                'available_quantity' => $this->availableQuantity(),
                'status' => $this->marketplace_status,
                // Only computed when the relations are loaded, to avoid N+1 on lists.
                'blockers' => $this->when(
                    $this->relationLoaded('merchant') && $this->relationLoaded('product') && $this->relationLoaded('branch'),
                    fn () => $this->marketplaceBlockers(),
                ),
            ],
        ];
    }
}
