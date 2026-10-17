<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'credit_allowed' => $this->credit_allowed,
            'credit_limit' => $this->credit_limit !== null ? (float) $this->credit_limit : null,
            'notes' => $this->notes,
        ];
    }
}
