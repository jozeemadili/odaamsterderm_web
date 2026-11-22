<?php

namespace App\Services\CatalogExcel;

use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\ProductRequirements;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Catalogue products in and out of Excel: every field of the admin product
 * form, with dropdowns fed by the live producers, categories, animals,
 * stages and option lists, and photos from a ZIP or links.
 */
class ProductSheet extends CatalogSheet
{
    /** [header, width, required, note, list name or null] */
    private const COLUMNS = [
        ['Code', 9, false, 'Leave blank for a new product; a code is created (P141…). Keep it when editing an existing product.', null],
        ['Product name', 30, true, 'Name as on the pack, without the pack size. e.g. Albenda Plus', null],
        ['Producer', 24, true, 'Pick the company. Not in the list? Add it on the Producers page first, or leave this blank and fill Brand.', 'Producers'],
        ['Brand (if producer not listed)', 20, false, 'Only when the producer is not in the list.', null],
        ['Category', 34, true, 'The most specific subcategory.', 'Categories'],
        ['Main animal', 30, true, 'The main animal on the label.', 'Animals'],
        ['Also for animal 2', 26, false, 'Other animals on the label.', 'Animals'],
        ['Also for animal 3', 26, false, '', 'Animals'],
        ['Also for animal 4', 26, false, '', 'Animals'],
        ['Growth stage 1', 30, false, 'Required for feeds.', 'Stages'],
        ['Growth stage 2', 30, false, '', 'Stages'],
        ['Growth stage 3', 30, false, '', 'Stages'],
        ['Pack size', 16, true, 'e.g. 25 kg, 100 ml, 1000 doses', null],
        ['Selling unit', 12, true, 'What one unit sold is: bag, bottle, vial…', 'Units'],
        ['Other pack sizes', 24, false, 'More sizes of the same product, separated by ; e.g. 500 ml; 1 L', null],
        ['How it may be sold', 30, false, '', 'Regulatory'],
        ['Prescription needed', 12, false, 'Yes / No', 'Yes/No'],
        ['Keep cold (2-8 C)', 12, false, 'Yes / No', 'Yes/No'],
        ['Form', 18, false, 'Required for feeds and supplements.', 'Forms'],
        ['Active ingredients', 26, false, 'Required for supplements, medicines, disinfectants. e.g. Albendazole 10%', null],
        ['Dosage / use rate', 34, false, 'Exactly as on the label. Required for supplements, medicines, disinfectants, concentrates and premixes.', null],
        ['How it is given 1', 22, false, 'Required for supplements, medicines and vaccines.', 'Routes'],
        ['How it is given 2', 22, false, '', 'Routes'],
        ['Meat withdrawal (days)', 11, false, 'Medicines for food animals. 0 = no withdrawal.', null],
        ['Egg withdrawal (days)', 11, false, 'Medicines for poultry. 0 = no withdrawal.', null],
        ['Milk withdrawal (days)', 11, false, 'Medicines for cattle, goats, sheep. 0 = no withdrawal.', null],
        ['Disease 1', 22, false, 'Required for vaccines.', 'Diseases'],
        ['Disease 2', 22, false, '', 'Diseases'],
        ['Disease 3', 22, false, '', 'Diseases'],
        ['Storage', 22, false, 'Required for vaccines. e.g. 2-8 C, keep away from light', null],
        ['TVLA registration no.', 18, false, 'Required for medicines and vaccines.', null],
        ['Crude protein %', 10, false, 'Complete feeds and concentrates.', null],
        ['Capacity', 14, false, 'Equipment. e.g. 10 L, 500 eggs', null],
        ['Power source', 16, false, 'Brooders, incubators, machinery.', 'Power'],
        ['Breed / strain', 16, false, 'Live animals. e.g. Cobb 500', null],
        ['Description', 40, false, 'One or two sentences for buyers.', null],
        ['Search words', 26, false, 'Kiswahili and local names, separated by | e.g. dawa ya minyoo|minyoo', null],
        ['Front photo', 26, false, 'Required to approve. File name inside the ZIP you upload with this sheet (e.g. albenda-front.jpg) or a https:// link.', null],
        ['Back photo', 26, false, 'Photo of the label. File name in the ZIP or a https:// link.', null],
        ['Missing required details', 30, false, 'Filled by the system when you download. Ignored on upload.', null],
        ['Status', 10, false, 'Filled by the system when you download. Ignored on upload.', null],
    ];

    public function name(): string
    {
        return 'products';
    }

    public function sheetTitle(): string
    {
        return 'Products';
    }

    public function workbook(bool $withData): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $this->dataSheet($book, $this->sheetTitle(), array_map(fn ($c) => array_slice($c, 0, 4), self::COLUMNS));
        $lists = $this->lists();
        $ranges = $this->listsSheet($book, array_map(fn ($l) => array_values($l), $lists));
        foreach (self::COLUMNS as $i => $col) {
            if ($col[4]) {
                $this->dropdown($sheet, $i + 1, $ranges[$col[4]], 'Pick from the list. To add a producer, category or animal, use its admin page first.');
            }
        }
        foreach ([24, 25, 26] as $c) {
            $this->wholeNumber($sheet, $c, 0, 365);
        }

        if ($withData) {
            $r = 2;
            Product::with(['producer', 'category.parent', 'animalType.parent', 'animalTypes.parent', 'lifeStages.animalType', 'variants', 'media'])
                ->orderBy('code')->chunk(200, function ($products) use ($sheet, &$r, $lists) {
                    foreach ($products as $p) {
                        $sheet->fromArray($this->exportRow($p, $lists), null, "A{$r}", true);
                        $r++;
                    }
                });
        } else {
            $this->exampleRow($sheet, $this->example($lists));
        }

        $this->guideSheet($book);
        $this->readMeSheet($book, 'Catalogue products: bulk upload', [
            ['How to use', 'Fill one row per product on the "Products" sheet, then upload it on Admin › Catalog › Products › Excel. Download "All products" to edit existing ones: keep the Code column so rows update instead of duplicating.'],
            ['Dropdowns', 'Producer, Category, animals, growth stages, unit, form, how it is given, diseases and power source are dropdowns with the values in the system. A producer, category or animal that is missing must be added on its admin page first (each page has its own Excel upload).'],
            ['Required', 'Pink columns (*) are needed to save a row. Each category also has required details (see the "Required by category" sheet); a new product that misses them is saved as "Pending review" and the upload report lists what to add. Complete products are approved straight away.'],
            ['Photos', 'Put the photo file names in Front photo / Back photo and upload a ZIP of the photos together with the sheet, or paste https:// links. JPG or PNG.'],
            ['Matching', 'A row with a Code updates that product. A row without a Code updates the product with the same name and producer, or creates a new one.'],
            ['Blank cells', 'When updating, a blank cell leaves the current value unchanged. Filling any "Also for animal", "Growth stage", "How it is given" or "Disease" cell replaces that whole group.'],
            ['Pack sizes', 'Pack size + Selling unit is the main size. "Other pack sizes" adds more sizes of the same product; existing sizes are kept.'],
        ]);

        return $book;
    }

    public function import(string $xlsxPath, ?string $zipPath, User $by): ImportResult
    {
        $result = new ImportResult;
        $rows = $this->rows($xlsxPath, $this->sheetTitle(), $result);
        $zip = $this->openZip($zipPath, $result);
        $lists = $this->lists();
        $flip = fn (string $list) => collect($lists[$list])->mapWithKeys(fn ($label, $code) => [Str::lower($label) => $code, Str::lower((string) $code) => $code])->all();
        $forms = $flip('Forms');
        $routes = $flip('Routes');
        $diseases = $flip('Diseases');
        $power = $flip('Power');
        $regulatory = $flip('Regulatory');

        foreach ($rows as $n => $row) {
            $get = fn (string $key) => trim((string) ($row[$key] ?? ''));

            // Who, what, for which animal.
            $name = $get('product_name');
            if ($name === '') {
                $result->fail($n, 'Product name is required.');

                continue;
            }
            $producer = null;
            if ($get('producer') !== '') {
                $producer = Producer::whereRaw('LOWER(name) = ?', [Str::lower($get('producer'))])->orWhere('code', $get('producer'))->first();
                if (! $producer) {
                    $result->fail($n, "Producer \"{$get('producer')}\" is not in the system. Add it on the Producers page, or use the Brand column.");

                    continue;
                }
            }
            $code = $get('code');
            $product = $code !== ''
                ? Product::where('code', $code)->first()
                : Product::whereRaw('LOWER(name) = ?', [Str::lower($name)])
                    ->when($producer, fn ($q) => $q->where('producer_id', $producer->id), fn ($q) => $q->whereRaw('LOWER(COALESCE(brand, \'\')) = ?', [Str::lower($get('brand_if_producer_not_listed'))]))
                    ->first();
            if ($code !== '' && ! $product) {
                $result->fail($n, "No product has the code {$code}. Clear the Code to create a new one.");

                continue;
            }
            $isNew = ! $product;

            $category = $get('category') !== '' ? $this->category($get('category')) : null;
            $main = $get('main_animal') !== '' ? $this->animal($get('main_animal')) : null;
            foreach ([['category', $category], ['main_animal', $main]] as [$key, $found]) {
                if ($get($key) !== '' && ! $found) {
                    $result->fail($n, Str::headline($key)." \"{$get($key)}\" is not in the system.");
                }
            }
            if ($isNew) {
                foreach (['producer' => $producer || $get('brand_if_producer_not_listed') !== '', 'category' => $category, 'main_animal' => $main, 'pack_size' => $get('pack_size') !== '', 'selling_unit' => $get('selling_unit') !== ''] as $key => $ok) {
                    if (! $ok) {
                        $result->fail($n, Str::headline($key).' is required for a new product.');
                    }
                }
            }
            $unit = Str::lower($get('selling_unit'));
            if ($unit !== '' && ! in_array($unit, Product::UNITS, true)) {
                $result->fail($n, "Selling unit \"{$get('selling_unit')}\" must be one of: ".implode(', ', Product::UNITS).'.');
            }

            // Several-value groups: an unknown value fails the row.
            $group = function (array $keys, callable $find, string $what) use ($get, $n, $result) {
                $values = [];
                foreach ($keys as $key) {
                    if ($get($key) === '') {
                        continue;
                    }
                    $found = $find($get($key));
                    $found === null ? $result->fail($n, "{$what} \"{$get($key)}\" is not in the list.") : $values[] = $found;
                }

                return array_values(array_unique($values));
            };
            $extraAnimals = $group(['also_for_animal_2', 'also_for_animal_3', 'also_for_animal_4'], fn ($v) => $this->animal($v)?->id, 'Animal');
            $stages = $group(['growth_stage_1', 'growth_stage_2', 'growth_stage_3'], fn ($v) => $this->stage($v)?->id, 'Growth stage');
            $routeCodes = $group(['how_it_is_given_1', 'how_it_is_given_2'], fn ($v) => $routes[Str::lower($v)] ?? null, 'How it is given');
            $diseaseCodes = $group(['disease_1', 'disease_2', 'disease_3'], fn ($v) => $diseases[Str::lower($v)] ?? null, 'Disease');
            $single = function (string $key, array $map, string $what) use ($get, $n, $result) {
                if ($get($key) === '') {
                    return '';
                }
                $code = $map[Str::lower($get($key))] ?? null;
                if ($code === null) {
                    $result->fail($n, "{$what} \"{$get($key)}\" is not in the list.");
                }

                return (string) $code;
            };
            $form = $single('form', $forms, 'Form');
            $powerCode = $single('power_source', $power, 'Power source');
            $regCode = $single('how_it_may_be_sold', $regulatory, 'How it may be sold');

            $numbers = [];
            foreach (['meat_withdrawal_days' => 'withdrawal_meat_days', 'egg_withdrawal_days' => 'withdrawal_eggs_days', 'milk_withdrawal_days' => 'withdrawal_milk_days'] as $key => $attr) {
                if ($get($key) === '') {
                    continue;
                }
                ctype_digit($get($key)) && (int) $get($key) <= 365
                    ? $numbers[$attr] = (int) $get($key)
                    : $result->fail($n, Str::headline($key).' must be a whole number of days (0-365).');
            }
            if ($get('crude_protein') !== '') {
                is_numeric($get('crude_protein')) && $get('crude_protein') >= 0 && $get('crude_protein') <= 100
                    ? $numbers['crude_protein_pct'] = (float) $get('crude_protein')
                    : $result->fail($n, 'Crude protein % must be a number from 0 to 100.');
            }

            if ($result->failed($n)) {
                continue;
            }

            $data = array_filter([
                'name' => $name,
                'producer_id' => $producer?->id,
                'brand' => $producer ? $producer->name : $get('brand_if_producer_not_listed'),
                'product_category_id' => $category?->id,
                'animal_type_id' => $main?->id,
                'pack_size' => $get('pack_size'),
                'unit' => $unit,
                'regulatory_class' => $regCode,
                'prescription_required' => self::yes($get('prescription_needed')),
                'cold_chain_required' => self::yes($get('keep_cold_2_8_c')),
                'form' => $form,
                'active_ingredients' => $get('active_ingredients'),
                'inclusion_or_dosage' => $get('dosage_use_rate'),
                'administration_route' => $routeCodes ?: null,
                'disease_target' => $diseaseCodes ?: null,
                'storage' => $get('storage'),
                'registration_no' => $get('tvla_registration_no'),
                'capacity' => $get('capacity'),
                'power_source' => $powerCode,
                'breed_strain' => $get('breed_strain'),
                'description' => $get('description'),
                'search_keywords' => Str::lower($get('search_words')),
            ], fn ($value) => $value !== '' && $value !== null) + $numbers;

            DB::transaction(function () use (&$product, $isNew, $data, $main, $extraAnimals, $stages, $get, $by, $result) {
                if ($isNew) {
                    $product = Product::create($data + [
                        'code' => $this->newCode(),
                        'slug' => Product::uniqueSlug(trim($data['name'].' '.($data['pack_size'] ?? '').' '.($data['brand'] ?? ''))),
                        'status' => Product::STATUS_PENDING,
                        'reviewed_by' => $by->id,
                    ]);
                    $result->created++;
                } else {
                    $product->update($data);
                    $result->updated++;
                }

                if ($extraAnimals) {
                    $product->animalTypes()->sync(array_unique([$product->animal_type_id, ...$extraAnimals]));
                } elseif ($main) {
                    $product->animalTypes()->syncWithoutDetaching([$main->id]);
                }
                if ($stages) {
                    $product->lifeStages()->sync($stages);
                }

                $product->syncDefaultVariant();
                foreach (array_filter(array_map('trim', explode(';', $get('other_pack_sizes')))) as $i => $size) {
                    if (! $product->variants()->whereRaw('LOWER(name) = ?', [Str::lower($size)])->exists()) {
                        $product->variants()->create(['name' => $size, 'selling_unit' => $product->unit, 'sort_order' => 10 + $i, 'is_active' => true]);
                    }
                }
            });

            foreach (['front_photo' => 'image', 'back_photo' => Product::BACK_PHOTO] as $key => $collection) {
                $ref = $get($key);
                if ($ref === '' || $ref === $product->getFirstMediaUrl($collection)) {
                    continue;
                }
                if ($file = $this->photo($ref, $zip)) {
                    $product->addMedia($file)->toMediaCollection($collection);
                    $result->photos++;
                } else {
                    $result->warn($n, Str::headline($key)." \"{$ref}\" was not found".($zip ? ' in the ZIP' : ' (upload a ZIP of photos with the sheet)').' or is not an image.');
                }
            }

            // Complete new products go live; others wait for the missing details.
            $missing = $product->fresh()->refreshQuality()['missing'];
            if ($isNew && ! $missing) {
                $product->forceFill(['status' => Product::STATUS_APPROVED, 'reviewed_at' => now()])->saveQuietly();
            } elseif ($missing) {
                $result->warn($n, ($isNew ? 'Saved as pending review. ' : '').'Still missing: '.implode(', ', $missing).'.');
            }
        }

        $zip?->close();

        return $result;
    }

    /* ---------- lists ---------- */

    /** @return array<string, array<string|int, string>> list name => [code => label] */
    private function lists(): array
    {
        return once(fn () => [
            'Producers' => Producer::active()->orderBy('name')->pluck('name', 'code')->all(),
            'Categories' => ProductCategory::active()->whereNotNull('parent_id')->with('parent')->orderBy('code')->get()
                ->mapWithKeys(fn ($c) => [$c->code => CategorySheet::label($c)])->all(),
            'Animals' => AnimalType::active()->with('parent')->get()
                ->sortBy(fn ($a) => sprintf('%05d|%05d', $a->parent?->sort_order ?? $a->sort_order, $a->parent ? $a->sort_order : 0))
                ->mapWithKeys(fn ($a) => [$a->code => $a->code.' · '.($a->parent ? $a->parent->name.' › ' : '').$a->name])->all(),
            'Stages' => LifeStage::active()->with('animalType')->orderBy('animal_type_id')->orderBy('sort_order')->get()
                ->mapWithKeys(fn ($s) => [$s->code => self::stageLabel($s)])->all(),
            'Units' => array_combine(Product::UNITS, Product::UNITS),
            'Yes/No' => ['yes' => 'Yes', 'no' => 'No'],
            'Regulatory' => ProductAttribute::options('regulatory_class'),
            'Forms' => ProductAttribute::options('form'),
            'Routes' => ProductAttribute::options('administration_route'),
            'Diseases' => ProductAttribute::options('disease_target'),
            'Power' => ProductAttribute::options('power_source'),
        ]);
    }

    private static function stageLabel(LifeStage $s): string
    {
        return $s->code.' · '.($s->animalType?->name ?? 'Any animal').': '.$s->name;
    }

    private function category(string $value): ?ProductCategory
    {
        $ref = self::codeOf($value);

        return ProductCategory::where('code', $ref)->orWhereRaw('LOWER(name) = ?', [Str::lower($ref)])->first();
    }

    private function animal(string $value): ?AnimalType
    {
        $ref = self::codeOf($value);

        return AnimalType::where('code', $ref)->orWhereRaw('LOWER(name) = ?', [Str::lower(Str::afterLast($ref, '› '))])->first();
    }

    private function stage(string $value): ?LifeStage
    {
        return LifeStage::where('code', self::codeOf($value))->first();
    }

    private function newCode(): string
    {
        $n = Product::where('code', 'like', 'P%')->pluck('code')->map(fn ($c) => (int) substr($c, 1))->max() ?? 0;
        do {
            $code = 'P'.str_pad((string) ++$n, 3, '0', STR_PAD_LEFT);
        } while (Product::where('code', $code)->exists());

        return $code;
    }

    /* ---------- export ---------- */

    private function exportRow(Product $p, array $lists): array
    {
        $pick = fn (string $list, ?string $code) => $code !== null && $code !== '' ? ($lists[$list][$code] ?? $code) : '';
        $extras = $p->animalTypes->where('id', '!=', $p->animal_type_id)->values();
        $stages = $p->lifeStages->values();
        $routes = array_values($p->administration_route ?? []);
        $diseases = array_values($p->disease_target ?? []);
        $animal = fn ($a) => $a ? $a->code.' · '.($a->parent ? $a->parent->name.' › ' : '').$a->name : '';
        $others = $p->variants->reject(fn ($v) => Str::lower($v->name) === Str::lower((string) $p->pack_size))->pluck('name')->join('; ');

        return [
            $p->code, $p->name, $p->producer?->name, $p->producer ? '' : $p->brand,
            $p->category ? CategorySheet::label($p->category) : '',
            $animal($p->animalType),
            $animal($extras[0] ?? null), $animal($extras[1] ?? null), $animal($extras[2] ?? null),
            isset($stages[0]) ? self::stageLabel($stages[0]) : '', isset($stages[1]) ? self::stageLabel($stages[1]) : '', isset($stages[2]) ? self::stageLabel($stages[2]) : '',
            $p->pack_size, $p->unit, $others,
            $pick('Regulatory', $p->regulatory_class), $p->prescription_required ? 'Yes' : 'No', $p->cold_chain_required ? 'Yes' : 'No',
            $pick('Forms', $p->form), $p->active_ingredients, $p->inclusion_or_dosage,
            $pick('Routes', $routes[0] ?? null), $pick('Routes', $routes[1] ?? null),
            $p->withdrawal_meat_days, $p->withdrawal_eggs_days, $p->withdrawal_milk_days,
            $pick('Diseases', $diseases[0] ?? null), $pick('Diseases', $diseases[1] ?? null), $pick('Diseases', $diseases[2] ?? null),
            $p->storage, $p->registration_no, $p->crude_protein_pct !== null ? (float) $p->crude_protein_pct : '',
            $p->capacity, $pick('Power', $p->power_source), $p->breed_strain, $p->description, $p->search_keywords,
            $p->getFirstMediaUrl('image') ?: '', $p->getFirstMediaUrl(Product::BACK_PHOTO) ?: '',
            implode(', ', ProductRequirements::missing($p)), ucfirst((string) $p->status),
        ];
    }

    private function example(array $lists): array
    {
        $first = fn (string $list, ?string $code = null) => $code && isset($lists[$list][$code]) ? $lists[$list][$code] : (string) (array_values($lists[$list])[0] ?? '');

        return [
            'EXAMPLE', 'Albenda Plus', $first('Producers'), '', $first('Categories', 'C3020'), $first('Animals', 'AN-CHK'), $first('Animals', 'AN-DCT'), '', '',
            '', '', '', '100 ml', 'bottle', '1 L', $first('Regulatory', 'otc_vet_medicine'), 'No', 'No', $first('Forms', 'liquid'),
            'Albendazole 10%', '1 ml per 10 kg body weight, once', $first('Routes', 'oral_drench'), '', 14, 7, 3, '', '', '',
            'Below 25 C, dry place', 'TVLA/VMP/1234', '', '', '', '', 'Broad-spectrum dewormer for poultry and dairy cattle.', 'dawa ya minyoo|minyoo',
            'albenda-front.jpg', 'albenda-back.jpg', '', '',
        ];
    }

    /** A sheet listing what each subcategory requires, from the same rules the admin screen uses. */
    private function guideSheet(Spreadsheet $book): void
    {
        $sheet = $this->dataSheet($book, 'Required by category', [['Code', 9], ['Category', 40], ['Required', 90], ['Required when', 70]], 1);
        $r = 2;
        foreach (ProductCategory::with('parent')->whereNotNull('parent_id')->orderBy('code')->get() as $c) {
            $probe = new Product(['product_category_id' => $c->id]);
            $probe->setRelation('category', $c);
            foreach (['animalTypes', 'lifeStages', 'variants', 'media'] as $rel) {
                $probe->setRelation($rel, new Collection);
            }
            $check = ProductRequirements::check($probe);
            $required = collect($check['required'])->pluck('label');
            $when = Str::startsWith($c->code, 'C30') ? 'Meat withdrawal (food animals); Egg withdrawal (poultry); Milk withdrawal (cattle, goats, sheep)' : '';
            $sheet->fromArray([$c->code, CategorySheet::label($c), $required->reject(fn ($l) => Str::contains($l, 'withdrawal'))->join(', '), $when], null, "A{$r}", true);
            $r++;
        }
        $sheet->getStyle("C2:D{$r}")->getAlignment()->setWrapText(true);
    }
}
