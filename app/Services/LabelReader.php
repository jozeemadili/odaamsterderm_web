<?php

namespace App\Services;

use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\Producer;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\ProductRequest;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiUnavailableException;
use RuntimeException;

/**
 * Drafts a catalogue product from the photos of a pack's front and back
 * label, for the data team to check and approve. The model reads only what
 * is printed and uses the catalogue's own codes; fields it can't read stay
 * empty rather than guessed.
 */
class LabelReader
{
    /** Longest side of a photo sent to the model, in pixels. */
    private const MAX_SIDE = 1568;

    public function __construct(private readonly AiClient $ai) {}

    public function available(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * @return array<string, mixed> the draft (see schema())
     *
     * @throws AiUnavailableException
     */
    public function draft(ProductRequest $request): array
    {
        $images = [];
        foreach ([ProductRequest::FRONT => 'Front of the pack', ProductRequest::BACK => 'Back of the pack'] as $side => $label) {
            if ($path = $request->photoPath($side)) {
                $images[] = ['type' => 'text', 'text' => $label.':'];
                $images[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => $this->jpegBase64($path)]];
            }
        }
        if ($images === []) {
            throw new RuntimeException('This request has no label photos.');
        }

        $hint = collect([
            "The agent calls it: {$request->name}",
            $request->producerLabel() ? "Producer (from the agent): {$request->producerLabel()}" : null,
            $request->pack_size ? "Pack size (from the agent): {$request->pack_size}" : null,
            $request->notes ? "Agent's note: {$request->notes}" : null,
        ])->filter()->implode("\n");

        $response = $this->ai->structured(
            $this->systemPrompt(),
            [['role' => 'user', 'content' => [...$images, ['type' => 'text', 'text' => $hint."\n\nDraft the catalogue product from these label photos."]]]],
            $this->schema(),
            4000,
        );

        if ($response->refused) {
            throw new AiUnavailableException('The model declined to read these photos.');
        }

        return $response->data;
    }

    protected function systemPrompt(): string
    {
        $categories = ProductCategory::with('parent')->whereNotNull('parent_id')->where('is_active', true)->orderBy('code')->get()
            ->map(fn ($c) => "{$c->code}: {$c->parent?->name} > {$c->name}")->implode("\n");
        $vaccines = ProductCategory::whereIn('code', ['C40', 'C4010', 'C4020', 'C4030', 'C4040'])->get()->map(fn ($c) => "{$c->code}: {$c->name}")->implode("\n");
        $animals = AnimalType::where('is_active', true)->get()->map(fn ($a) => "{$a->code}: {$a->name}")->implode("\n");
        $stages = LifeStage::with('animalType')->get()->map(fn ($s) => "{$s->code}: ".($s->animalType?->name ?? 'Any').' - '.$s->name)->implode("\n");
        $producers = Producer::where('is_active', true)->orderBy('name')->pluck('name')->implode(', ');
        $opt = fn (string $key) => collect(ProductAttribute::options($key))->map(fn ($label, $code) => "{$code} ({$label})")->implode(', ');

        return <<<PROMPT
        You read photos of animal health and farm product labels (feeds, medicines, vaccines, supplements, equipment) for a Tanzanian agrovet marketplace, and draft one catalogue product for the data team to check.

        Rules:
        - Use only what is printed on the label. Never guess a dose, withdrawal period, registration number or ingredient; leave a field empty (null or []) when it is not readable.
        - Copy figures exactly as printed, with units (e.g. "1 g per 2 L of drinking water for 5 days").
        - Give the product name without the pack size; give pack sizes separately.
        - Choose codes only from the lists below.
        - Put anything uncertain in "doubts", briefly.

        Categories (code: group > category):
        {$categories}
        {$vaccines}

        Animals (code: name):
        {$animals}

        Life stages (code: animal - stage):
        {$stages}

        Known producers: {$producers}

        Forms: {$opt('form')}
        Uses (what it is for / treats): {$opt('use_cases')}
        Diseases it protects against: {$opt('disease_target')}
        How to give it: {$opt('administration_route')}
        Power sources (equipment): {$opt('power_source')}
        PROMPT;
    }

    /** @return array<string, mixed> */
    protected function schema(): array
    {
        $nullable = fn (string $type) => ['anyOf' => [['type' => $type], ['type' => 'null']]];
        $str = $nullable('string');
        $int = $nullable('integer');
        $list = ['type' => 'array', 'items' => ['type' => 'string']];

        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'producer_name' => $str,
                'category_code' => $str,
                'animal_codes' => $list,
                'life_stage_codes' => $list,
                'form' => $str,
                'pack_sizes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'value' => $nullable('number'),
                            'unit' => $str,
                            'selling_unit' => ['type' => 'string'],
                        ],
                        'required' => ['name', 'value', 'unit', 'selling_unit'],
                        'additionalProperties' => false,
                    ],
                ],
                'description' => $str,
                'active_ingredients' => $str,
                'dosage' => $str,
                'administration_route' => $list,
                'use_cases' => $list,
                'disease_target' => $list,
                'withdrawal_meat_days' => $int,
                'withdrawal_eggs_days' => $int,
                'withdrawal_milk_days' => $int,
                'registration_no' => $str,
                'storage' => $str,
                'prescription_required' => $nullable('boolean'),
                'cold_chain_required' => $nullable('boolean'),
                'crude_protein_pct' => $nullable('number'),
                'capacity' => $str,
                'power_source' => $str,
                'breed_strain' => $str,
                'doubts' => $str,
            ],
            'required' => [
                'name', 'producer_name', 'category_code', 'animal_codes', 'life_stage_codes', 'form', 'pack_sizes', 'description',
                'active_ingredients', 'dosage', 'administration_route', 'use_cases', 'disease_target', 'withdrawal_meat_days',
                'withdrawal_eggs_days', 'withdrawal_milk_days', 'registration_no', 'storage', 'prescription_required',
                'cold_chain_required', 'crude_protein_pct', 'capacity', 'power_source', 'breed_strain', 'doubts',
            ],
            'additionalProperties' => false,
        ];
    }

    /** The photo as JPEG, no larger than MAX_SIDE on its longest side, base64. */
    protected function jpegBase64(string $path): string
    {
        $data = (string) file_get_contents($path);
        $image = @imagecreatefromstring($data);
        if (! $image) {
            return base64_encode($data);
        }

        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($w * $scale), (int) round($h * $scale));
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return base64_encode((string) ob_get_clean());
    }
}
