<?php

namespace App\Http\Resources\Market;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A marketplace order as both customer and seller see it. */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isSeller = $user && $user->merchant_id === $this->merchant_id;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_reason' => $this->status_reason,
            'total' => (float) $this->total,
            'items_count' => $this->items_count,
            'customer_note' => $this->customer_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'can_cancel' => $this->canBeCancelledByCustomer(),
            'next_statuses' => $isSeller
                ? collect($this->nextSellerStatuses())->map(fn ($s) => ['status' => $s, 'label' => \App\Models\Order::LABELS[$s]])->values()
                : [],
            'seller' => $this->whenLoaded('merchant', fn () => ['id' => $this->merchant->id, 'name' => $this->merchant->business_name]),
            'store' => new PublicStoreResource($this->whenLoaded('branch')),
            // Sellers need to know who is collecting; customers already know.
            'customer' => $this->when($isSeller, fn () => ['name' => $this->customer_name, 'phone' => $this->customer_phone]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'name' => $i->name,
                'unit' => $i->unit,
                'unit_price' => (float) $i->unit_price,
                'quantity' => (float) $i->quantity,
                'line_total' => (float) $i->line_total,
            ])->values()),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'status' => $e->status,
                'label' => \App\Models\Order::LABELS[$e->status] ?? $e->status,
                'note' => $e->note,
                'at' => $e->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
