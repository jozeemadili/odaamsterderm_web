<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'merchant_id', 'customer_code', 'name', 'phone', 'email', 'address',
    'tin_number', 'credit_allowed', 'credit_limit', 'notes',
])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'credit_allowed' => 'boolean',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(SalesRecord::class, 'customer_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
