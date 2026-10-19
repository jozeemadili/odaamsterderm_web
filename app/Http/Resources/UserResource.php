<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Support\StoreAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'user_type' => $this->user_type,
            'role' => $this->roles->first()?->name,
            'role_label' => $this->roleLabel(),
            'merchant_id' => $this->merchant_id,
            // Stores this person works in (seller accounts only).
            'all_stores' => $this->merchant_id ? StoreAccess::seesAll($this->resource) : null,
            'stores' => $this->merchant_id
                ? Branch::whereIn('id', StoreAccess::branchIds($this->resource))->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()
                : [],
            'location' => $this->hasLocation() ? [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'label' => $this->location_label,
            ] : null,
        ];
    }
}
