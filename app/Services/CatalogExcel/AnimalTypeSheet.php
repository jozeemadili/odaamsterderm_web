<?php

namespace App\Services\CatalogExcel;

use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Animal types (two levels) and their growth stages in and out of Excel. */
class AnimalTypeSheet extends CatalogSheet
{
    public const STAGES = 'Growth stages';

    private const COLUMNS = [
        ['Code', 10, false, 'Leave blank for a new animal; a code is created. Keep it when editing.'],
        ['Name', 32, true, ''],
        ['Swahili name', 26, false, ''],
        ['Main animal', 30, false, 'Leave blank for a main group (e.g. Cattle). Pick one to make this a type under it (e.g. Dairy cattle).'],
        ['Description', 40, false, ''],
        ['Sort order', 10, false, 'Lower numbers show first.'],
        ['Active', 10, false, 'Yes / No'],
        ['Image', 30, false, 'Photo file name inside the ZIP you upload with this sheet (e.g. chicken.jpg), or a https:// link.'],
    ];

    private const STAGE_COLUMNS = [
        ['Code', 14, false, 'Leave blank for a new stage; a code is created. Keep it when editing.'],
        ['Animal', 30, false, 'The main animal group this stage belongs to. Blank = any animal (e.g. "All stages").'],
        ['Name', 34, true, 'e.g. Starter / chick (0–4 wks)'],
        ['Swahili name', 26, false, ''],
        ['Description', 40, false, ''],
        ['Sort order', 10, false, ''],
        ['Active', 10, false, 'Yes / No'],
        ['Image', 30, false, 'Photo file name inside the ZIP you upload with this sheet (e.g. chicken.jpg), or a https:// link.'],
    ];

    public function name(): string
    {
        return 'animal-types';
    }

    public function sheetTitle(): string
    {
        return 'Animal types';
    }

    public function workbook(bool $withData): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $this->dataSheet($book, $this->sheetTitle(), self::COLUMNS);
        $stages = $this->dataSheet($book, self::STAGES, self::STAGE_COLUMNS, 1);
        $mains = AnimalType::whereNull('parent_id')->orderBy('sort_order')->get();
        $ranges = $this->listsSheet($book, ['Main animal' => $mains->map(fn ($a) => self::label($a))->all(), 'Yes/No' => ['Yes', 'No']]);
        $this->dropdown($sheet, 4, $ranges['Main animal']);
        $this->dropdown($sheet, 7, $ranges['Yes/No']);
        $this->wholeNumber($sheet, 6, 0, 10000);
        $this->dropdown($stages, 2, $ranges['Main animal']);
        $this->dropdown($stages, 7, $ranges['Yes/No']);
        $this->wholeNumber($stages, 6, 0, 10000);

        if ($withData) {
            $r = 2;
            foreach (AnimalType::with(['parent', 'media'])->get()->sortBy(fn ($a) => sprintf('%05d|%s|%05d', $a->parent?->sort_order ?? $a->sort_order, $a->parent?->code ?? $a->code, $a->parent ? $a->sort_order : 0)) as $a) {
                $sheet->fromArray([$a->code, $a->name, $a->name_sw, $a->parent ? self::label($a->parent) : '', $a->description, $a->sort_order, $a->is_active ? 'Yes' : 'No', $a->imageUrl()], null, "A{$r}", true);
                $r++;
            }
            $r = 2;
            foreach (LifeStage::with(['animalType', 'media'])->orderBy('animal_type_id')->orderBy('sort_order')->get() as $s) {
                $stages->fromArray([$s->code, $s->animalType ? self::label($s->animalType) : '', $s->name, $s->name_sw, $s->description, $s->sort_order, $s->is_active ? 'Yes' : 'No', $s->imageUrl()], null, "A{$r}", true);
                $r++;
            }
        } else {
            $this->exampleRow($sheet, ['EXAMPLE', 'Dairy cattle', 'Ng\'ombe wa maziwa', $mains->first() ? self::label($mains->first()) : '', 'Cows kept for milk.', 10, 'Yes', 'dairy-cattle.jpg']);
            $this->exampleRow($stages, ['EXAMPLE', $mains->first() ? self::label($mains->first()) : '', 'Starter / chick (0–4 wks)', 'Vifaranga (wiki 0–4)', '', 10, 'Yes', 'starter.jpg']);
        }

        $this->readMeSheet($book, 'Animal types and growth stages: bulk upload', [
            ['How to use', 'Fill the "Animal types" sheet and, if needed, the "Growth stages" sheet, then upload the file on Admin › Catalog › Animal types. Download "All animal types" to edit: keep the Code so rows update.'],
            ['Two levels', 'A row with no Main animal is a main group (Poultry, Cattle…). A row with a Main animal is a type under it (Chicken, Dairy cattle…).'],
            ['Growth stages', 'Stages belong to a main animal group (Poultry: Starter, Grower…). Leave Animal blank for a stage that fits any animal.'],
            ['Blank cells', 'When updating, a blank cell leaves the current value unchanged, except Main animal: blank makes it a main group.'],
            ['Images', 'Put the photo file name in the Image column and upload a ZIP of the photos with the sheet, or paste a https:// link. JPG or PNG.'],
        ]);

        return $book;
    }

    public function import(string $xlsxPath, ?string $zipPath, User $by): ImportResult
    {
        $result = new ImportResult;
        $zip = $this->openZip($zipPath, $result);

        foreach ($this->rows($xlsxPath, $this->sheetTitle(), $result) as $n => $row) {
            if (! $this->valid($row, $n, $result)) {
                continue;
            }
            $parent = null;
            if (($row['main_animal'] ?? '') !== '') {
                $parent = $this->mainAnimal($row['main_animal']);
                if (! $parent) {
                    $result->fail($n, "Main animal \"{$row['main_animal']}\" was not found. Add it in an earlier row first.");

                    continue;
                }
            }
            $animal = ($row['code'] ?? '') !== ''
                ? AnimalType::where('code', $row['code'])->first()
                : AnimalType::whereRaw('LOWER(name) = ?', [Str::lower($row['name'])])->first();
            if (($row['code'] ?? '') !== '' && ! $animal) {
                $result->fail($n, "No animal type has the code {$row['code']}. Clear the Code to create a new one.");

                continue;
            }
            if ($animal && $parent && ($parent->id === $animal->id || $animal->children()->exists())) {
                $result->fail($n, "{$animal->name} cannot go under {$parent->name}.");

                continue;
            }

            $data = $this->common($row) + ['parent_id' => $parent?->id];
            if ($animal) {
                $animal->update($data);
                $result->updated++;
            } else {
                $animal = AnimalType::create($data + [
                    'code' => $this->code('AN-', $row['name'], fn ($c) => AnimalType::where('code', $c)->exists()),
                    'slug' => $this->slug($row['name'], fn ($s) => AnimalType::where('slug', $s)->exists()),
                    'is_active' => $data['is_active'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                ]);
                $result->created++;
            }
            $this->image($animal, $row['image'] ?? '', $zip, $n, $result);
        }

        // Growth stages: row numbers are offset so messages say which sheet.
        foreach ($this->rows($xlsxPath, self::STAGES, $result, false) as $n => $row) {
            $key = 100000 + $n;
            if (! isset($row['name']) || ! $this->valid($row, $key, $result)) {
                continue;
            }
            $animal = null;
            if (($row['animal'] ?? '') !== '') {
                $animal = $this->mainAnimal($row['animal']);
                if (! $animal) {
                    $result->fail($key, "Animal \"{$row['animal']}\" was not found.");

                    continue;
                }
            }
            $stage = ($row['code'] ?? '') !== ''
                ? LifeStage::where('code', $row['code'])->first()
                : LifeStage::whereRaw('LOWER(name) = ?', [Str::lower($row['name'])])->where('animal_type_id', $animal?->id)->first();
            if (($row['code'] ?? '') !== '' && ! $stage) {
                $result->fail($key, "No growth stage has the code {$row['code']}.");

                continue;
            }
            $data = $this->common($row) + ['animal_type_id' => $animal?->id];
            if ($stage) {
                $stage->update($data);
                $result->updated++;
            } else {
                $prefix = 'LS-'.($animal ? Str::upper(Str::substr(Str::after($animal->code, 'AN-'), 0, 3)) : 'ALL').'-';
                $stage = LifeStage::create($data + [
                    'code' => $this->code($prefix, $row['name'], fn ($c) => LifeStage::where('code', $c)->exists()),
                    'slug' => $this->slug(($animal?->name ?? 'any').' '.$row['name'], fn ($s) => LifeStage::where('slug', $s)->exists()),
                    'is_active' => $data['is_active'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                ]);
                $result->created++;
            }
            $this->image($stage, $row['image'] ?? '', $zip, $key, $result);
        }

        $zip?->close();

        return $result;
    }

    /** @param  AnimalType|LifeStage  $model */
    private function image($model, string $ref, ?\ZipArchive $zip, int $row, ImportResult $result): void
    {
        if ($ref === '' || $ref === $model->imageUrl()) {
            return;
        }
        if ($file = $this->photo($ref, $zip)) {
            $model->addMedia($file)->toMediaCollection('image');
            $result->photos++;
        } else {
            $result->warn($row, "Image \"{$ref}\" was not found".($zip ? ' in the ZIP' : ' (upload a ZIP of photos with the sheet)').' or is not an image.');
        }
    }

    public static function label(AnimalType $a): string
    {
        return $a->code.' · '.$a->name;
    }

    /** Row numbers above 100000 are on the Growth stages sheet. */
    public static function where(int $row): string
    {
        return $row > 100000 ? self::STAGES.' row '.($row - 100000) : 'Row '.$row;
    }

    private function valid(array $row, int $n, ImportResult $result): bool
    {
        $v = Validator::make($row, ['name' => ['required', 'max:255'], 'swahili_name' => ['nullable', 'max:255'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        foreach ($v->errors()->all() as $m) {
            $result->fail($n, $m);
        }

        return $v->passes();
    }

    private function common(array $row): array
    {
        return array_filter([
            'name' => $row['name'],
            'name_sw' => $row['swahili_name'] ?? '',
            'description' => $row['description'] ?? '',
            'sort_order' => ($row['sort_order'] ?? '') !== '' ? (int) $row['sort_order'] : null,
            'is_active' => self::yes($row['active'] ?? ''),
        ], fn ($value) => $value !== '' && $value !== null);
    }

    private function mainAnimal(string $value): ?AnimalType
    {
        $ref = self::codeOf($value);

        return AnimalType::whereNull('parent_id')->where(fn ($q) => $q->where('code', $ref)->orWhereRaw('LOWER(name) = ?', [Str::lower($ref)]))->first();
    }

    private function code(string $prefix, string $name, callable $taken): string
    {
        $base = $prefix.Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($name)) ?: 'NEW', 0, 3));
        $code = $base;
        $i = 2;
        while ($taken($code)) {
            $code = $base.$i++;
        }

        return $code;
    }

    private function slug(string $name, callable $taken): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;
        while ($taken($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
