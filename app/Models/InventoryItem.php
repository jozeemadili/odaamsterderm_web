<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'merchant_id', 'category_id', 'branch_id', 'product_id', 'name', 'sku', 'barcode', 'unit', 'quantity_on_hand', 'reorder_level',
    'unit_cost', 'unit_price', 'expiry_date', 'is_marketplace_visible', 'marketplace_quantity', 'reserved_quantity', 'marketplace_status',
])]
class InventoryItem extends Model implements HasMedia
{
    use BelongsToStore, InteractsWithMedia;

    public const MARKET_ACTIVE = 'active';

    public const MARKET_OUT_OF_STOCK = 'out_of_stock';

    public const MARKET_INACTIVE = 'inactive';

    /** Mirrors the column defaults so new models are complete before a refresh. */
    protected $attributes = [
        'quantity_on_hand' => 0,
        'is_marketplace_visible' => false,
        'marketplace_quantity' => 0,
        'reserved_quantity' => 0,
        'marketplace_status' => self::MARKET_INACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'expiry_date' => 'date',
            'is_marketplace_visible' => 'boolean',
            'marketplace_quantity' => 'decimal:2',
            'reserved_quantity' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Physical stock not already held for open marketplace orders. */
    public function availableQuantity(): float
    {
        return max(0, (float) $this->quantity_on_hand - (float) $this->reserved_quantity);
    }

    /**
     * Items customers can currently see and order: visible, active, with
     * marketplace stock, linked to an approved catalog product, in an
     * active store with a map location, owned by an approved seller.
     */
    public function scopeOnMarketplace(Builder $query): Builder
    {
        return $query
            ->where('inventory_items.is_marketplace_visible', true)
            ->where('inventory_items.marketplace_status', self::MARKET_ACTIVE)
            ->where('inventory_items.marketplace_quantity', '>', 0)
            ->whereHas('product', fn (Builder $p) => $p->where('status', Product::STATUS_APPROVED))
            ->whereHas('branch', fn (Builder $b) => $b->marketplaceReady())
            ->whereHas('merchant', fn (Builder $m) => $m->marketplaceApproved());
    }

    /**
     * Why this item can't be listed on the marketplace right now, in words
     * the seller can act on. Empty when it can be listed.
     *
     * @return array<int, string>
     */
    public function marketplaceBlockers(): array
    {
        $blockers = [];

        if (! $this->merchant?->canSellOnMarketplace()) {
            $blockers[] = 'Your business is not approved for the marketplace yet.';
        }

        if (! $this->product_id) {
            $blockers[] = 'Link this item to a catalog product.';
        } elseif (! $this->product?->isApproved()) {
            $blockers[] = 'The linked catalog product is waiting for admin approval.';
        }

        if (! $this->branch_id) {
            $blockers[] = 'Assign this item to a store.';
        } elseif (! $this->branch?->hasLocation()) {
            $blockers[] = 'Set the store location on the map.';
        } elseif (! $this->branch->isActive()) {
            $blockers[] = 'This store has been suspended by the platform.';
        } elseif (! $this->branch->is_marketplace_enabled) {
            $blockers[] = 'This store is switched off for the marketplace.';
        }

        return $blockers;
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockReceiptItems(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_level;
    }

    public function isExpiringSoon(): bool
    {
        return $this->expiry_date !== null
            && ! $this->expiry_date->isPast()
            && $this->expiry_date->lte(now()->addMonth());
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function image(): ?Media
    {
        return $this->getFirstMedia('image');
    }

    /** The seller's own photo of this stock, else the catalog product's image. */
    public function photoUrl(): ?string
    {
        return $this->image()?->getUrl() ?? $this->product?->image()?->getUrl();
    }

    public static function generateUniqueBarcode(): string
    {
        do {
            $code = '2'.str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);
        } while (self::where('barcode', $code)->exists());

        return $code;
    }
}
