<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a catalogue product must have, by category, so buyers and the AI get
 * the facts that matter (a dewormer's withdrawal days, a vaccine's doses per
 * vial, a feed's protein). Required fields block approval; recommended ones
 * only count towards the completeness score.
 */
class ProductRequirements
{
    /** Animal codes whose meat/eggs/milk people eat; NOT_FOOD are pets and others. */
    private const POULTRY = ['AN-POU', 'AN-CHK', 'AN-OPO'];

    private const MILKED = ['AN-CAT', 'AN-DCT', 'AN-GTS', 'AN-GOT', 'AN-SHP'];

    private const NOT_FOOD = ['AN-OTH', 'AN-DOG', 'AN-FEL'];

    /**
     * @return array{score:int, required:list<array{key:string,label:string,ok:bool}>, recommended:list<array{key:string,label:string,ok:bool}>, missing:list<string>}
     */
    public static function check(Product $product): array
    {
        $product->loadMissing(['category.parent', 'animalTypes', 'animalType', 'lifeStages', 'variants', 'media']);
        $fields = self::fields();
        $animals = self::animalCodes($product);
        [$required, $recommended] = self::keysFor($product, $animals);

        $row = fn (string $key) => ['key' => $key, 'label' => $fields[$key][0], 'ok' => (bool) $fields[$key][1]($product)];
        $required = array_map($row, $required);
        $recommended = array_map($row, array_values(array_diff($recommended, array_column($required, 'key'))));

        $all = [...$required, ...$recommended];
        $done = count(array_filter($all, fn ($r) => $r['ok']));

        return [
            'score' => $all ? (int) round(100 * $done / count($all)) : 100,
            'required' => $required,
            'recommended' => $recommended,
            'missing' => array_values(array_map(fn ($r) => $r['label'], array_filter($required, fn ($r) => ! $r['ok']))),
        ];
    }

    /**
     * What a product in this category must and should have, before any
     * data is entered (withdrawal periods depend on the animals chosen).
     *
     * @return array{required: list<string>, when: list<string>, recommended: list<string>}
     */
    public static function forCategory(ProductCategory $category): array
    {
        $probe = new Product(['product_category_id' => $category->id]);
        $probe->setRelation('category', $category);
        $probe->setRelation('animalType', null);
        foreach (['animalTypes', 'lifeStages', 'variants', 'media'] as $relation) {
            $probe->setRelation($relation, new Collection);
        }
        $check = self::check($probe);
        $labels = fn (array $rows) => array_values(array_map(fn ($r) => $r['label'], $rows));
        $isMedicine = str_starts_with((string) $category->code, 'C30');

        return [
            'required' => array_values(array_filter($labels($check['required']), fn ($l) => ! str_contains($l, 'withdrawal'))),
            'when' => $isMedicine ? ['Meat withdrawal (days): for food animals', 'Egg withdrawal (days): for poultry', 'Milk withdrawal (days): for cattle, goats, sheep'] : [],
            'recommended' => $labels($check['recommended']),
        ];
    }

    /** @return list<string> labels of required fields that are empty */
    public static function missing(Product $product): array
    {
        return self::check($product)['missing'];
    }

    /**
     * @param  list<string>  $animals
     * @return array{0: list<string>, 1: list<string>} required and recommended keys
     */
    private static function keysFor(Product $product, array $animals): array
    {
        $code = (string) $product->category?->code;
        $top = substr($code, 0, 3);
        $food = $animals === [] || array_diff($animals, self::NOT_FOOD) !== [];

        $required = ['name', 'maker', 'category', 'animals', 'front_photo', 'pack_size'];
        $recommended = ['back_photo', 'description', 'keywords', 'life_stages'];

        $required = [...$required, ...match ($top) {
            'C10' => [
                'life_stages', 'form',
                ...(in_array($code, ['C1010', 'C1020'], true) ? ['protein'] : []),
                ...(in_array($code, ['C1020', 'C1030'], true) ? ['dosage'] : []),
            ],
            'C20' => ['active_ingredients', 'dosage', 'route', 'form'],
            'C30' => [
                'active_ingredients', 'dosage', 'route', 'registration_no',
                ...($food ? ['withdrawal_meat'] : []),
                ...(array_intersect($animals, self::POULTRY) ? ['withdrawal_eggs'] : []),
                ...(array_intersect($animals, self::MILKED) ? ['withdrawal_milk'] : []),
            ],
            'C40' => ['disease_target', 'route', 'doses_per_pack', 'storage', 'registration_no'],
            'C50' => ['active_ingredients', 'dosage'],
            'C60' => [
                ...(in_array($code, ['C6010', 'C6020', 'C6030', 'C6040', 'C6090'], true) ? ['capacity'] : []),
                ...(in_array($code, ['C6020', 'C6040', 'C6090'], true) ? ['power_source'] : []),
            ],
            'C70' => in_array($code, ['C7010', 'C7020', 'C7030'], true) ? ['breed_strain'] : [],
            default => [],
        }];

        $recommended = [...$recommended, ...match ($top) {
            'C30' => ['disease_target', 'storage'],
            'C20', 'C50' => ['storage'],
            default => [],
        }];

        return [array_values(array_unique($required)), array_values(array_unique($recommended))];
    }

    /** @return list<string> */
    private static function animalCodes(Product $product): array
    {
        return $product->animalTypes->pluck('code')
            ->push($product->animalType?->code)
            ->filter()->unique()->values()->all();
    }

    /** @return array<string, array{0:string, 1:callable(Product):bool}> */
    private static function fields(): array
    {
        $filled = fn (string $attr) => fn (Product $p) => trim((string) $p->{$attr}) !== '';
        $set = fn (string $attr) => fn (Product $p) => $p->{$attr} !== null && $p->{$attr} !== '';
        $list = fn (string $attr) => fn (Product $p) => ! empty($p->{$attr});

        return [
            'name' => ['Name', $filled('name')],
            'maker' => ['Producer', fn (Product $p) => $p->producer_id || trim((string) $p->brand) !== ''],
            'category' => ['Category', fn (Product $p) => (bool) $p->product_category_id],
            'animals' => ['Animals', fn (Product $p) => $p->animal_type_id || $p->animalTypes->isNotEmpty()],
            'front_photo' => ['Front photo', fn (Product $p) => $p->media->contains('collection_name', 'image')],
            'back_photo' => ['Back (label) photo', fn (Product $p) => $p->media->contains('collection_name', Product::BACK_PHOTO)],
            'pack_size' => ['Pack size', fn (Product $p) => $p->variants->isNotEmpty() || trim((string) $p->pack_size) !== ''],
            'description' => ['Description', $filled('description')],
            'keywords' => ['Search words (Kiswahili)', $filled('search_keywords')],
            'life_stages' => ['Growth stages', fn (Product $p) => $p->lifeStages->isNotEmpty()],
            'form' => ['Form', $filled('form')],
            'protein' => ['Crude protein %', $set('crude_protein_pct')],
            'dosage' => ['Dosage / use rate', $filled('inclusion_or_dosage')],
            'active_ingredients' => ['Active ingredients', $filled('active_ingredients')],
            'route' => ['How it is given', $list('administration_route')],
            'registration_no' => ['TVLA registration no.', $filled('registration_no')],
            'withdrawal_meat' => ['Meat withdrawal (days)', $set('withdrawal_meat_days')],
            'withdrawal_eggs' => ['Egg withdrawal (days)', $set('withdrawal_eggs_days')],
            'withdrawal_milk' => ['Milk withdrawal (days)', $set('withdrawal_milk_days')],
            'disease_target' => ['Diseases it treats / prevents', $list('disease_target')],
            'storage' => ['Storage', $filled('storage')],
            'doses_per_pack' => ['Doses per vial / pack', fn (Product $p) => $p->variants->contains(fn ($v) => $v->pack_size_unit === 'doses' && (float) $v->pack_size_value > 0)
                || preg_match('/\d+\s*(doses?|ds)\b/i', (string) $p->pack_size.' '.$p->variants->pluck('name')->join(' '))],
            'capacity' => ['Capacity', $filled('capacity')],
            'power_source' => ['Power source', $filled('power_source')],
            'breed_strain' => ['Breed / strain', $filled('breed_strain')],
        ];
    }
}
