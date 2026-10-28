<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'pack_size' => $this->pack_size,
            'unit' => $this->unit,
            'brand' => $this->brand,
            'producer' => $this->makerSummary(),
            'description' => $this->description,
            'status' => $this->status,
            'review_notes' => $this->when($this->status === 'rejected', $this->review_notes),
            'image_url' => $this->image()?->getUrl(),
            'back_image_url' => $this->backImage()?->getUrl(),
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType ? [
                'id' => $this->animalType->id,
                'name' => $this->animalType->name,
            ] : null),
            'code' => $this->code,
            'regulatory_class' => $this->regulatory_class,
            'prescription_required' => (bool) $this->prescription_required,
            'cold_chain_required' => (bool) $this->cold_chain_required,
            'animal_types' => $this->whenLoaded('animalTypes', fn () => $this->animalTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->where('is_active', true)->map(fn ($v) => $v->summary())->values()),
            'life_stages' => $this->whenLoaded('lifeStages', fn () => $this->lifeStages->map(fn ($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->values()),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->fullName(),
            ] : null),
        ];
    }
}
