<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'code', 'parent_id', 'name', 'name_sw', 'slug', 'description', 'tie_break_rule', 'required_attributes',
    'sort_order', 'is_active',
])]
class ProductCategory extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'required_attributes' => 'array',
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

    /**
     * Categories that hold approved products for the given animal type, so a
     * browse filter for "Fish" only offers categories with fish products.
     * A parent counts when it or one of its sub-categories has a match;
     * "Poultry" also matches products tagged "Chicken", and any of a
     * product's animals counts. No animal = all.
     */
    public function scopeWithProductsFor(Builder $query, ?int $animalTypeId): Builder
    {
        if (! $animalTypeId) {
            return $query;
        }

        $matchingProducts = fn (Builder $p) => $p
            ->where('status', Product::STATUS_APPROVED)
            ->forAnimal($animalTypeId);

        return $query->where(fn (Builder $q) => $q
            ->whereHas('products', $matchingProducts)
            ->orWhereHas('children.products', $matchingProducts));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** "Feed › Complete feed" */
    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /** @return array<int, int> */
    public function selfAndChildIds(): array
    {
        return [$this->id, ...$this->children()->pluck('id')->all()];
    }
}
