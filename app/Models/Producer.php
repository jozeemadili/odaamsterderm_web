<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
#[Fillable(['name', 'slug', 'country', 'website', 'description', 'is_active'])]
class Producer extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
            ->logOnly(['name', 'country', 'website', 'is_active'])
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
}
