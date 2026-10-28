<?php

namespace App\Http\Resources\Market;

use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A product in marketplace search results, with per-product aggregates. */
class MarketProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nearest = $this->nearest_km !== null ? (float) $this->nearest_km : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'brand' => $this->brand,
            'producer' => $this->makerSummary(),
            'pack_size' => $this->pack_size,
            'unit' => $this->unit,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),
            'image_urls' => $this->imageUrls(),
            'animal_type' => $this->animalType ? ['id' => $this->animalType->id, 'name' => $this->animalType->name] : null,
            'code' => $this->code,
            'regulatory_class' => $this->regulatory_class,
            'prescription_required' => (bool) $this->prescription_required,
            'cold_chain_required' => (bool) $this->cold_chain_required,
            'animal_types' => $this->whenLoaded('animalTypes', fn () => $this->animalTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->where('is_active', true)->map(fn ($v) => $v->summary())->values()),
            'life_stages' => $this->whenLoaded('lifeStages', fn () => $this->lifeStages->map(fn ($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->values()),
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->fullName()] : null,
            'min_price' => $this->min_price !== null ? (float) $this->min_price : null,
            'max_price' => $this->max_price !== null ? (float) $this->max_price : null,
            'sellers_count' => (int) $this->sellers_count,
            'available_quantity' => (float) $this->available_quantity,
            'nearest_km' => $nearest !== null ? round($nearest, 2) : null,
            'nearest_label' => GeoPoint::formatKm($nearest),
        ];
    }
}
