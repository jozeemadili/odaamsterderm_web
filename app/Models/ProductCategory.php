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

#[Fillable(['parent_id', 'name', 'slug', 'sort_order', 'is_active'])]
class ProductCategory extends Model
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

    /** Categories usable for the given animal type (no restriction = usable everywhere). */
    public function scopeForAnimalType(Builder $query, ?int $animalTypeId): Builder
    {
        if (! $animalTypeId) {
            return $query;
        }

        $type = AnimalType::find($animalTypeId);
        $typeIds = $type ? array_filter([$type->id, $type->parent_id]) : [$animalTypeId];

        return $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('animalTypes')
            ->orWhereHas('animalTypes', fn (Builder $t) => $t->whereIn('animal_types.id', $typeIds)));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function animalTypes(): BelongsToMany
    {
        return $this->belongsToMany(AnimalType::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** "Feed › Starter" */
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
