<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['code', 'parent_id', 'name', 'name_sw', 'slug', 'description', 'sort_order', 'is_active'])]
class AnimalType extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    /** Shown when an animal has no photo yet. */
    private const EMOJI = [
        'AN-POU' => '🐔', 'AN-CHK' => '🐓', 'AN-OPO' => '🦆', 'AN-CAT' => '🐄', 'AN-DCT' => '🐄', 'AN-BCT' => '🐂',
        'AN-GTS' => '🐐', 'AN-GOT' => '🐐', 'AN-SHP' => '🐑', 'AN-PIG' => '🐖', 'AN-FSH' => '🐟', 'AN-TIL' => '🐟',
        'AN-CAF' => '🐟', 'AN-OTH' => '🐇', 'AN-DOG' => '🐕', 'AN-FEL' => '🐈',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function imageUrl(): ?string
    {
        return $this->getFirstMediaUrl('image') ?: null;
    }

    public function emoji(): string
    {
        return self::EMOJI[$this->code] ?? ($this->parent ? $this->parent->emoji() : '🐾');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** "Other livestock / animals (rabbits, dogs, bees)" → "Other livestock / animals" */
    public function shortName(): string
    {
        return trim((string) preg_replace('/\s*\(.*\)$/', '', $this->name)) ?: $this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->useLogName('catalog');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function lifeStages(): HasMany
    {
        return $this->hasMany(LifeStage::class)->orderBy('sort_order');
    }

    /** Products whose main animal is this one. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Every product tagged with this animal, main or not. */
    public function taggedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** "Poultry › Chicken" */
    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /**
     * Ids of this type and its direct children, so filtering by "Poultry"
     * also finds products tagged "Chicken".
     *
     * @return array<int, int>
     */
    public function selfAndChildIds(): array
    {
        return [$this->id, ...$this->children()->pluck('id')->all()];
    }

    /**
     * Animal tags that match a filter on this type: itself, its children,
     * and its parent, so "Chicken" also finds a drinker tagged "Poultry".
     *
     * @return array<int, int>
     */
    public function filterIds(): array
    {
        return array_values(array_filter([...$this->selfAndChildIds(), $this->parent_id]));
    }
}
