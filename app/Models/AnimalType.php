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

#[Fillable(['code', 'parent_id', 'name', 'name_sw', 'slug', 'description', 'sort_order', 'is_active'])]
class AnimalType extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
