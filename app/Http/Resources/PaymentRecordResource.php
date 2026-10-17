<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'payer_name' => $this->payer_name,
            'payment_date' => $this->payment_date->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'proof_of_payment_url' => $this->proofOfPayment()?->getUrl(),
        ];
    }
}
