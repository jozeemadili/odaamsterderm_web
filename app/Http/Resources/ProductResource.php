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
            'animal_type' => $this->whenLoaded('animalType', fn () => $this->animalType ? [
                'id' => $this->animalType->id,
                'name' => $this->animalType->name,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->fullName(),
            ] : null),
        ];
    }
}
