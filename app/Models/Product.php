<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
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
    'code', 'name', 'slug', 'animal_type_id', 'product_category_id', 'producer_id', 'brand', 'description', 'unit', 'pack_size',
    'status', 'proposed_by_merchant_id', 'reviewed_by', 'reviewed_at', 'review_notes',
    'regulatory_class', 'prescription_required', 'cold_chain_required', 'production_purpose', 'use_cases',
    'disease_target', 'administration_route', 'form', 'active_ingredients', 'crude_protein_pct', 'inclusion_or_dosage',
    'withdrawal_meat_days', 'withdrawal_eggs_days', 'withdrawal_milk_days', 'storage', 'registration_no',
    'capacity', 'power_source', 'breed_strain', 'country_of_origin', 'search_keywords', 'is_demo',
])]
class Product extends Model implements HasMedia
{
    /** Media collection for the back-of-pack photo; 'image' is the front. */
    public const BACK_PHOTO = 'image_back';

    use InteractsWithMedia, LogsActivity;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const UNITS = [
        'bag', 'kg', 'g', 'litre', 'ml', 'bottle', 'sachet', 'box', 'vial', 'tin', 'piece', 'pack', 'dose',
        'bale', 'set', 'bird', 'head', 'straw',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'prescription_required' => 'boolean',
            'cold_chain_required' => 'boolean',
            'is_demo' => 'boolean',
            'production_purpose' => 'array',
            'use_cases' => 'array',
            'disease_target' => 'array',
            'administration_route' => 'array',
            'crude_protein_pct' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            // The producer's name is the brand, so search and brand filters keep working.
            if ($product->producer_id && $product->isDirty('producer_id')) {
                $product->brand = Producer::whereKey($product->producer_id)->value('name') ?? $product->brand;
            }
        });

        static::creating(function (Product $product) {
            if (! $product->slug) {
                $product->slug = static::uniqueSlug(trim($product->displayName().' '.$product->brand));
            }
        });

        // The main animal is always one of the product's animal tags.
        static::saved(function (Product $product) {
            if ($product->animal_type_id && ($product->wasRecentlyCreated || $product->wasChanged('animal_type_id'))) {
                $product->animalTypes()->syncWithoutDetaching([$product->animal_type_id]);
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
            ->logOnly([
                'name', 'brand', 'producer_id', 'unit', 'pack_size', 'status', 'animal_type_id', 'product_category_id',
                'regulatory_class', 'prescription_required', 'cold_chain_required',
            ])
            ->logOnlyDirty()
            ->useLogName('catalog');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection(self::BACK_PHOTO)->singleFile();
    }

    public function image(): ?Media
    {
        return $this->getFirstMedia('image');
    }

    public function backImage(): ?Media
    {
        return $this->getFirstMedia(self::BACK_PHOTO);
    }

    /**
     * Photos for the product page, front then back: the catalog's own, else
     * the first seller offer that has a photo.
     *
     * @param  iterable<InventoryItem>  $offers
     */
    public function galleryUrls(iterable $offers = []): array
    {
        if ($this->image()) {
            return $this->imageUrls();
        }

        foreach ($offers as $offer) {
            if ($offer->image()) {
                return array_values(array_filter([$offer->image()->getUrl(), $offer->backImage()?->getUrl()]));
            }
        }

        return array_values(array_filter([$this->getAttribute('seller_image_url')]));
    }

    /** Front first, then back: the photos customers can swipe through. */
    public function imageUrls(): array
    {
        return array_values(array_filter([$this->imageUrl(), $this->backImage()?->getUrl()]));
    }

    /**
     * What customers see: the catalog image, or — when the catalog has none —
     * a seller's photo of this product (set by MarketplaceSearch::attachSellerPhotos).
     */
    public function imageUrl(): ?string
    {
        return $this->image()?->getUrl() ?? $this->getAttribute('seller_image_url');
    }

    /** Products tagged with this animal, its children or its parent (see AnimalType::filterIds). */
    public function scopeForAnimal(Builder $query, int|string|null $animalTypeId): Builder
    {
        $animal = $animalTypeId ? AnimalType::find($animalTypeId) : null;

        return $animal ? $query->whereIn($query->qualifyColumn('id'), static::idsTaggedWith($animal)) : $query;
    }

    /**
     * Subquery of product ids for an animal filter, for queries that join
     * products. The main animal counts too, for rows saved without model
     * events (seeders) that never got their animal tag.
     */
    public static function idsTaggedWith(AnimalType $animal): QueryBuilder
    {
        $ids = $animal->filterIds();

        return DB::table('animal_type_product')->select('product_id')->whereIn('animal_type_id', $ids)
            ->union(DB::table('products')->select('id')->whereIn('animal_type_id', $ids));
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

    /**
     * Label facts buyers should see before they buy, as label => value;
     * empty fields are left out.
     *
     * @return array<string, string>
     */
    public function detailsForBuyers(): array
    {
        $days = fn (?int $n) => $n === null ? null : ($n === 0 ? 'None' : $n.' '.Str::plural('day', $n));
        $labels = fn (string $key, ?array $codes) => $codes
            ? implode(', ', array_map(fn ($code) => ProductAttribute::options($key)[$code] ?? $code, $codes))
            : null;

        return array_filter([
            'Prescription' => $this->prescription_required ? 'Needs a vet prescription' : null,
            'Cold chain' => $this->cold_chain_required ? 'Keep at 2–8 °C' : null,
            'Active ingredients' => $this->active_ingredients,
            'Crude protein' => $this->crude_protein_pct !== null ? rtrim(rtrim((string) $this->crude_protein_pct, '0'), '.').'%' : null,
            'How to give' => $labels('administration_route', $this->administration_route),
            'Dosage' => $this->inclusion_or_dosage,
            'Protects against' => $labels('disease_target', $this->disease_target),
            'Meat withdrawal' => $days($this->withdrawal_meat_days),
            'Egg withdrawal' => $days($this->withdrawal_eggs_days),
            'Milk withdrawal' => $days($this->withdrawal_milk_days),
            'Storage' => $this->storage,
            'Registration no.' => $this->registration_no,
            'Made in' => $this->country_of_origin,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** Who makes it: the producer's name, or the free-text brand when no producer is linked. */
    public function makerName(): ?string
    {
        return $this->producer?->name ?? ($this->brand ?: null);
    }

    /** For APIs: {id, name, logo_url, country}; id null when only a free-text brand is known. */
    public function makerSummary(): ?array
    {
        if ($this->producer) {
            return [
                'id' => $this->producer->id,
                'name' => $this->producer->name,
                'logo_url' => $this->producer->logoUrl(),
                'country' => $this->producer->country,
            ];
        }

        return $this->brand ? ['id' => null, 'name' => $this->brand, 'logo_url' => null, 'country' => null] : null;
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /** The main animal, shown on cards; animalTypes() holds every animal the label covers. */
    public function animalType(): BelongsTo
    {
        return $this->belongsTo(AnimalType::class);
    }

    public function animalTypes(): BelongsToMany
    {
        return $this->belongsToMany(AnimalType::class)->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function lifeStages(): BelongsToMany
    {
        return $this->belongsToMany(LifeStage::class)->orderBy('sort_order');
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

    /** Pack sizes / SKUs, smallest first as listed in the workbook. */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Keeps a product typed in by hand (one pack size on the product) in
     * step with its single variant: creates it, or renames it when the pack
     * size changes. Products with workbook SKUs are left alone.
     */
    public function syncDefaultVariant(): void
    {
        if (! $this->pack_size) {
            return;
        }

        $variants = $this->variants()->get();

        if ($variants->isEmpty()) {
            $this->variants()->create(['name' => $this->pack_size, 'selling_unit' => $this->unit]);
        } elseif ($variants->count() === 1 && ! $variants->first()->sku) {
            $variants->first()->update(['name' => $this->pack_size, 'selling_unit' => $this->unit]);
        }
    }
}
