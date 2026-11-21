<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * The factory or company that makes a catalog product ("Chicken Grower
 * 25KG by Silverlands"). The same product name from two producers is two
 * catalog products, so buyers and sellers can tell them apart.
 */
#[Fillable(['code', 'name', 'slug', 'country', 'role', 'deals_in', 'website', 'phone', 'location', 'description', 'is_active', 'on_marketplace', 'is_demo'])]
class Producer extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    /** What a company produces: day-old chicks, feeds, or both. */
    public const DEALS_CHICKS = 'chicks';

    public const DEALS_FEED = 'feed';

    public const DEALS_CHICKS_FEED = 'chicks_feed';

    public const DEALS = [
        self::DEALS_CHICKS => 'Chicks',
        self::DEALS_FEED => 'Feed',
        self::DEALS_CHICKS_FEED => 'Chicks and feed',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'on_marketplace' => 'boolean', 'is_demo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Producer $producer) {
            $producer->slug ??= Str::slug($producer->name) ?: 'producer';
        });

        // Products keep the producer's name in "brand" for search and filters.
        static::updated(function (Producer $producer) {
            if ($producer->wasChanged('name')) {
                $producer->products()->update(['brand' => $producer->name]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'country', 'website', 'is_active', 'on_marketplace'])
            ->logOnlyDirty()
            ->useLogName('catalog');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Chick types this hatchery produces. */
    public function chickTypes(): BelongsToMany
    {
        return $this->belongsToMany(ChickType::class);
    }

    public function hatchSchedules(): HasMany
    {
        return $this->hasMany(HatchSchedule::class);
    }

    public function makesChicks(): bool
    {
        return in_array($this->deals_in, [self::DEALS_CHICKS, self::DEALS_CHICKS_FEED], true);
    }

    /** Hatcheries: companies that make day-old chicks. */
    public function scopeHatcheries(Builder $query): Builder
    {
        return $query->whereIn('deals_in', [self::DEALS_CHICKS, self::DEALS_CHICKS_FEED]);
    }
}
