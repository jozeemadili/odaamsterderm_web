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
            'is_primary' => $this->is_primary,
            'product_count' => $this->inventory_items_count ?? $this->inventoryItems()->count(),
            'stock_value' => $summary['stockValue'],
            'selling_value' => $summary['sellingValue'],
            'expected_profit' => $summary['expectedProfit'],
        ];
    }
}
