<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'receipt_date' => $this->receipt_date->toDateString(),
            'notes' => $this->notes,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($line) => [
                'id' => $line->id,
                'inventory_item_id' => $line->inventory_item_id,
                'product_name' => $line->inventoryItem?->name,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'subtotal' => (float) $line->subtotal,
            ])),
        ];
    }
}
