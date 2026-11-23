<?php

namespace App\Services\CatalogExcel;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Product categories (two levels) in and out of Excel. */
class CategorySheet extends CatalogSheet
{
    private const COLUMNS = [
        ['Code', 10, false, 'Leave blank for a new category; a code is created (C80 for a main category, C3070 for a subcategory). Keep it when editing.'],
        ['Name', 32, true, ''],
        ['Swahili name', 30, false, ''],
        ['Main category', 30, false, 'Leave blank to make this a main category. Pick one to make it a subcategory of it.'],
        ['Description', 46, false, ''],
        ['Sort order', 10, false, 'Lower numbers show first.'],
        ['Active', 10, false, 'Yes / No'],
        ['Products', 10, false, 'For information only.'],
        ['Image', 30, false, 'Photo file name inside the ZIP you upload with this sheet (e.g. dewormers.jpg), or a https:// link.'],
    ];

    public function name(): string
    {
        return 'categories';
    }

    public function sheetTitle(): string
    {
        return 'Categories';
    }

    public function workbook(bool $withData): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $this->dataSheet($book, $this->sheetTitle(), self::COLUMNS);
        $mains = ProductCategory::whereNull('parent_id')->orderBy('code')->get();
        $ranges = $this->listsSheet($book, ['Main category' => $mains->map(fn ($c) => self::label($c))->all(), 'Yes/No' => ['Yes', 'No']]);
        $this->dropdown($sheet, 4, $ranges['Main category']);
        $this->dropdown($sheet, 7, $ranges['Yes/No']);
        $this->wholeNumber($sheet, 6, 0, 10000);

        if ($withData) {
            $r = 2;
            $all = ProductCategory::with(['parent', 'media'])->withCount('products')->get()
                ->sortBy(fn ($c) => ($c->parent?->code ?? $c->code).'|'.($c->parent ? $c->code : ''));
            foreach ($all as $c) {
                $sheet->fromArray([$c->code, $c->name, $c->name_sw, $c->parent ? self::label($c->parent) : '', $c->description, $c->sort_order, $c->is_active ? 'Yes' : 'No', $c->products_count, $c->imageUrl()], null, "A{$r}", true);
                $r++;
            }
        } else {
            $this->exampleRow($sheet, ['EXAMPLE', 'Dewormers', 'Dawa za minyoo', $mains->first() ? self::label($mains->first()) : '', 'Medicines that remove internal worms.', 20, 'Yes', '', 'dewormers.jpg']);
        }

        $this->readMeSheet($book, 'Product categories: bulk upload', [
            ['How to use', 'One row per category on the "Categories" sheet, then upload it on Admin › Catalog › Categories. Download "All categories" to edit: keep the Code so rows update.'],
            ['Two levels', 'A row with no Main category is a main category (e.g. Medicines). A row with a Main category is a subcategory (e.g. Dewormers under Medicines). Subcategories cannot have their own subcategories.'],
            ['New main categories', 'To add a main category and its subcategories in one upload, put the main category first. The dropdown only lists main categories that already exist, so you may type the new name in the Main category cell.'],
            ['Blank cells', 'When updating, a blank cell leaves the current value unchanged, except Main category: blank makes it a main category.'],
            ['Images', 'Put the photo file name in the Image column and upload a ZIP of the photos with the sheet, or paste a https:// link. JPG or PNG.'],
        ]);

        return $book;
    }

    public function import(string $xlsxPath, ?string $zipPath, User $by): ImportResult
    {
        $result = new ImportResult;
        $zip = $this->openZip($zipPath, $result);

        foreach ($this->rows($xlsxPath, $this->sheetTitle(), $result) as $n => $row) {
            $v = Validator::make($row, ['name' => ['required', 'max:255'], 'swahili_name' => ['nullable', 'max:255'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
            if ($v->fails()) {
                foreach ($v->errors()->all() as $m) {
                    $result->fail($n, $m);
                }

                continue;
            }

            $parent = null;
            if (($row['main_category'] ?? '') !== '') {
                $ref = self::codeOf($row['main_category']);
                $parent = ProductCategory::whereNull('parent_id')->where(fn ($q) => $q->where('code', $ref)->orWhereRaw('LOWER(name) = ?', [Str::lower($ref)]))->first();
                if (! $parent) {
                    $result->fail($n, "Main category \"{$row['main_category']}\" was not found. Add it in an earlier row first.");

                    continue;
                }
            }

            $category = ($row['code'] ?? '') !== ''
                ? ProductCategory::where('code', $row['code'])->first()
                : ProductCategory::whereRaw('LOWER(name) = ?', [Str::lower($row['name'])])->where('parent_id', $parent?->id)->first();
            if (($row['code'] ?? '') !== '' && ! $category) {
                $result->fail($n, "No category has the code {$row['code']}. Clear the Code to create a new one.");

                continue;
            }
            if ($category && $parent && $category->children()->exists()) {
                $result->fail($n, "{$category->name} has subcategories, so it cannot go under another category.");

                continue;
            }
            if ($category && $parent && $parent->id === $category->id) {
                $result->fail($n, 'A category cannot be its own main category.');

                continue;
            }

            $data = array_filter([
                'name' => $row['name'],
                'name_sw' => $row['swahili_name'] ?? '',
                'description' => $row['description'] ?? '',
                'sort_order' => ($row['sort_order'] ?? '') !== '' ? (int) $row['sort_order'] : null,
                'is_active' => self::yes($row['active'] ?? ''),
            ], fn ($value) => $value !== '' && $value !== null);
            // The parent is always as written: blank means "main category".
            $data['parent_id'] = $parent?->id;

            if ($category) {
                $category->update($data);
                $result->updated++;
            } else {
                $category = ProductCategory::create($data + [
                    'code' => $this->newCode($parent),
                    'slug' => $this->slug(($parent ? $parent->name.' ' : '').$row['name']),
                    'is_active' => $data['is_active'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                ]);
                $result->created++;
            }

            $ref = $row['image'] ?? '';
            if ($ref !== '' && $ref !== $category->imageUrl()) {
                if ($file = $this->photo($ref, $zip)) {
                    $category->addMedia($file)->toMediaCollection('image');
                    $result->photos++;
                } else {
                    $result->warn($n, "Image \"{$ref}\" was not found".($zip ? ' in the ZIP' : ' (upload a ZIP of photos with the sheet)').' or is not an image.');
                }
            }
        }

        $zip?->close();

        return $result;
    }

    public static function label(ProductCategory $c): string
    {
        return $c->code.' · '.($c->parent ? $c->parent->name.' › ' : '').$c->name;
    }

    private function newCode(?ProductCategory $parent): string
    {
        if ($parent) {
            $n = ProductCategory::where('parent_id', $parent->id)->pluck('code')
                ->map(fn ($c) => (int) Str::after((string) $c, $parent->code))->max() ?? 0;
            do {
                $n += 10;
                $code = $parent->code.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            } while (ProductCategory::where('code', $code)->exists());

            return $code;
        }
        $n = ProductCategory::whereNull('parent_id')->pluck('code')->map(fn ($c) => (int) Str::after((string) $c, 'C'))->max() ?? 0;
        do {
            $n += 10;
            $code = 'C'.$n;
        } while (ProductCategory::where('code', $code)->exists());

        return $code;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (ProductCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
