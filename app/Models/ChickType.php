<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A kind of day-old chick customers book: Broiler, Layers, Sasso, Tanbro. */
#[Fillable(['code', 'name', 'name_sw', 'purpose', 'summary', 'summary_sw', 'description', 'description_sw', 'sort_order', 'is_active'])]
class ChickType extends Model
{
    public const PURPOSES = ['meat' => 'Meat', 'eggs' => 'Eggs', 'dual' => 'Meat and eggs'];

    public const PURPOSES_SW = ['meat' => 'Nyama', 'eggs' => 'Mayai', 'dual' => 'Nyama na mayai'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Hatcheries that produce this type. */
    public function producers(): BelongsToMany
    {
        return $this->belongsToMany(Producer::class);
    }

    public function hatchSchedules(): HasMany
    {
        return $this->hasMany(HatchSchedule::class);
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? $this->purpose;
    }

    /** For the API: both languages, so the app picks its own. */
    public function summaryArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_sw' => $this->name_sw,
            'purpose' => $this->purpose,
            'purpose_label' => $this->purposeLabel(),
            'purpose_label_sw' => self::PURPOSES_SW[$this->purpose] ?? null,
            'summary' => $this->summary,
            'summary_sw' => $this->summary_sw,
            'description' => $this->description,
            'description_sw' => $this->description_sw,
        ];
    }
}
