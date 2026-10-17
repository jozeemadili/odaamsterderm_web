<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['merchant_id', 'name', 'contact_person', 'phone', 'address', 'notes'])]
class Supplier extends Model
{
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    public function stockReceipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function outstandingBalance(): float
    {
        return (float) $this->transactions()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'purchase' THEN amount ELSE -amount END), 0) as balance")
            ->value('balance');
    }
}
