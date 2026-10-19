<?php

namespace App\Http\Resources;

use App\Http\Controllers\TransferNoteController;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'from_store' => ['id' => $this->from_branch_id, 'name' => $this->fromBranch?->name],
            'to_store' => ['id' => $this->to_branch_id, 'name' => $this->toBranch?->name],
            'notes' => $this->notes,
            'sent_by' => $this->sender?->name,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'received_by' => $this->receiver?->name,
            'received_at' => $this->received_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($line) => [
                'id' => $line->id,
                'inventory_item_id' => $line->from_item_id,
                'name' => $line->item_name,
                'unit' => $line->unit,
                'quantity' => (float) $line->quantity,
            ])),
            'can_receive' => $this->canReceive($request->user()),
            'can_cancel' => $this->canCancel($request->user()),
            'note_url' => TransferNoteController::url($this->resource),
        ];
    }
}
