<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamageReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'product_name' => $this->inventoryItem?->name,
            'quantity' => (float) $this->quantity,
            'description' => $this->description,
            'photo_url' => $this->photo()?->getUrl(),
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,
            'reported_by' => $this->reportedBy?->name,
            'reported_at' => $this->reported_at?->toDateString(),
        ];
    }
}
