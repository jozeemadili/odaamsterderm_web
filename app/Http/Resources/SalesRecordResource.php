<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_date' => $this->sale_date->toDateString(),
            'amount' => (float) $this->amount,
            'subtotal' => $this->subtotal !== null ? (float) $this->subtotal : null,
            'discount_amount' => (float) $this->discount_amount,
            'payment_method' => $this->payment_method,
            'amount_tendered' => $this->amount_tendered !== null ? (float) $this->amount_tendered : null,
            'change_due' => $this->change_due !== null ? (float) $this->change_due : null,
            'items_count' => $this->items_count,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
