<?php

namespace App\Services\CatalogExcel;

use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Producers (factories, companies) in and out of Excel, with logos. */
class ProducerSheet extends CatalogSheet
{
    private const COLUMNS = [
        ['Code', 12, false, 'Leave blank for a new producer; a code is created. Keep it when editing an existing one.'],
        ['Name', 30, true, 'Company name as customers know it. Must be unique.'],
        ['Country', 16, false, 'e.g. Tanzania'],
        ['What they make', 34, false, 'e.g. Manufacturer (veterinary medicines)'],
        ['Hatchery (chicks)', 16, false, 'Only for companies that supply day-old chicks.'],
        ['Website', 28, false, 'Full link, starting with https://'],
        ['Phone', 16, false, ''],
        ['Location', 22, false, 'Town or region'],
        ['About', 46, false, 'Shown to customers under the product.'],
        ['Active', 10, false, 'Yes / No. Inactive producers cannot be chosen for new products.'],
        ['On marketplace', 14, false, 'Yes / No. No hides their products from customers.'],
        ['Logo', 30, false, 'File name of the logo inside the ZIP you upload with this sheet (e.g. silverlands.png), or a https:// link to the image. Square images look best.'],
        ['Products', 10, false, 'For information only: how many catalogue products this producer has.'],
    ];

    public function name(): string
    {
        return 'producers';
    }

    public function sheetTitle(): string
    {
        return 'Producers';
    }

    public function workbook(bool $withData): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $this->dataSheet($book, $this->sheetTitle(), self::COLUMNS);
        $ranges = $this->listsSheet($book, ['Yes/No' => ['Yes', 'No'], 'Hatchery' => array_values(Producer::DEALS)]);
        $this->dropdown($sheet, 5, $ranges['Hatchery']);
        $this->dropdown($sheet, 10, $ranges['Yes/No']);
        $this->dropdown($sheet, 11, $ranges['Yes/No']);

        if ($withData) {
            $r = 2;
            foreach (Producer::withCount('products')->with('media')->orderBy('name')->get() as $p) {
                $sheet->fromArray([
                    $p->code, $p->name, $p->country, $p->role, Producer::DEALS[$p->deals_in] ?? '', $p->website, $p->phone,
                    $p->location, $p->description, $p->is_active ? 'Yes' : 'No', $p->on_marketplace ? 'Yes' : 'No',
                    $p->logoUrl(), $p->products_count,
                ], null, "A{$r}", true);
                $r++;
            }
        } else {
            $this->exampleRow($sheet, ['EXAMPLE', 'Silverlands Tanzania', 'Tanzania', 'Manufacturer (feed, day-old chicks)', 'Chicks and feed', 'https://silverlands.co.tz', '+255 700 000 000', 'Iringa', 'Feeds and day-old chicks for Tanzanian farmers.', 'Yes', 'Yes', 'silverlands.png', '']);
        }

        $this->readMeSheet($book, 'Producers: bulk upload', [
            ['How to use', 'Fill one row per producer on the "Producers" sheet, then upload it on Admin › Catalog › Producers. Download "All producers" to edit existing ones: keep the Code column so rows update instead of duplicating.'],
            ['Matching', 'A row with a Code updates that producer. A row without a Code updates the producer with the same name, or creates a new one.'],
            ['Blank cells', 'When updating, a blank cell leaves the current value unchanged.'],
            ['Logos', 'Put the logo file name in the Logo column and upload a ZIP of the logo images together with the sheet, or paste a https:// link to the image. PNG or JPG, square works best.'],
        ]);

        return $book;
    }

    public function import(string $xlsxPath, ?string $zipPath, User $by): ImportResult
    {
        $result = new ImportResult;
        $rows = $this->rows($xlsxPath, $this->sheetTitle(), $result);
        $zip = $this->openZip($zipPath, $result);
        $deals = array_flip(array_map('strtolower', Producer::DEALS));

        foreach ($rows as $n => $row) {
            $v = Validator::make($row, [
                'name' => ['required', 'max:255'],
                'website' => ['nullable', 'url', 'max:255'],
                'country' => ['nullable', 'max:100'],
                'phone' => ['nullable', 'max:50'],
                'about' => ['nullable', 'max:2000'],
            ]);
            if ($v->fails()) {
                foreach ($v->errors()->all() as $m) {
                    $result->fail($n, $m);
                }

                continue;
            }

            $producer = ($row['code'] ?? '') !== ''
                ? Producer::where('code', $row['code'])->first()
                : Producer::whereRaw('LOWER(name) = ?', [Str::lower($row['name'])])->first();
            if (($row['code'] ?? '') !== '' && ! $producer) {
                $result->fail($n, "No producer has the code {$row['code']}. Clear the Code to create a new one.");

                continue;
            }
            $clash = Producer::whereRaw('LOWER(name) = ?', [Str::lower($row['name'])])->when($producer, fn ($q) => $q->whereKeyNot($producer->id))->exists();
            if ($clash) {
                $result->fail($n, "Another producer is already called \"{$row['name']}\".");

                continue;
            }

            $hatch = Str::lower($row['hatchery_chicks'] ?? '');
            $data = array_filter([
                'name' => $row['name'],
                'country' => $row['country'] ?? '',
                'role' => $row['what_they_make'] ?? '',
                'deals_in' => $hatch !== '' ? ($deals[$hatch] ?? null) : '',
                'website' => $row['website'] ?? '',
                'phone' => $row['phone'] ?? '',
                'location' => $row['location'] ?? '',
                'description' => $row['about'] ?? '',
                'is_active' => self::yes($row['active'] ?? ''),
                'on_marketplace' => self::yes($row['on_marketplace'] ?? ''),
            ], fn ($value) => $value !== '' && $value !== null);
            if ($hatch !== '' && ! isset($deals[$hatch])) {
                $result->warn($n, "Hatchery \"{$row['hatchery_chicks']}\" is not one of: ".implode(', ', Producer::DEALS).'.');
            }

            DB::transaction(function () use (&$producer, $data, $result) {
                if ($producer) {
                    $producer->update($data);
                    $result->updated++;
                } else {
                    $producer = Producer::create($data + ['code' => $this->newCode($data['name']), 'slug' => $this->slug($data['name'])]);
                    $result->created++;
                }
            });

            $logo = $row['logo'] ?? '';
            if ($logo !== '' && $logo !== $producer->logoUrl()) {
                if ($file = $this->photo($logo, $zip)) {
                    $producer->addMedia($file)->toMediaCollection('logo');
                    $result->photos++;
                } else {
                    $result->warn($n, "Logo \"{$logo}\" was not found".($zip ? ' in the ZIP' : ' (upload a ZIP of logos with the sheet)').' or is not an image.');
                }
            }
        }

        $zip?->close();

        return $result;
    }

    private function newCode(string $name): string
    {
        $base = 'BR-'.Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($name)) ?: 'PRD', 0, 3));
        $code = $base;
        $i = 2;
        while (Producer::where('code', $code)->exists()) {
            $code = $base.$i++;
        }

        return $code;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'producer';
        $slug = $base;
        $i = 2;
        while (Producer::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
