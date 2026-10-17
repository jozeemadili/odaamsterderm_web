<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A product in the shared, admin-curated catalog. Sellers stock it per
 * store through InventoryItem::product_id, which is what lets customers
 * compare the same product across sellers.
 */
#[Fillable([
    'name', 'slug', 'animal_type_id', 'product_category_id', 'brand', 'description', 'unit', 'pack_size',
    'status', 'proposed_by_merchant_id', 'reviewed_by', 'reviewed_at', 'review_notes',
])]
class Product extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const UNITS = ['bag', 'kg', 'g', 'litre', 'ml', 'bottle', 'sachet', 'box', 'vial', 'tin', 'piece', 'pack', 'dose'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (! $product->slug) {
                $product->slug = static::uniqueSlug($product->displayName());
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'brand', 'unit', 'pack_size', 'status', 'animal_type_id', 'product_category_id'])
            ->logOnlyDirty()
            ->useLogName('catalog');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function image(): ?Media
    {
        return $this->getFirstMedia('image');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /** Approved products plus the given merchant's own pending proposals. */
    public function scopeUsableBy(Builder $query, int $merchantId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', self::STATUS_APPROVED)
            ->orWhere(fn (Builder $p) => $p->where('status', self::STATUS_PENDING)->where('proposed_by_merchant_id', $merchantId)));
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** "Chicken Starter 25KG" — name plus pack size when the name doesn't already include it. */
    public function displayName(): string
    {
        if ($this->pack_size && ! Str::contains(Str::lower($this->name), Str::lower($this->pack_size))) {
            return $this->name.' '.$this->pack_size;
        }

        return $this->name;
    }

    public function animalType(): BelongsTo
    {
        return $this->belongsTo(AnimalType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'proposed_by_merchant_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
