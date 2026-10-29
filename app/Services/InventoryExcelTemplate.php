<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The inventory spreadsheet. Every row names a catalog product (picked from
 * the dropdown fed by the "Catalog" sheet), so imported stock is always
 * linked to the shared catalog, with its name, unit and photos.
 */
class InventoryExcelTemplate
{
    public const SHEET = 'Import inventory';

    public const CATALOG_SHEET = 'Catalog';

    public const HEADERS = [
        'Catalog Product', 'Barcode', 'SKU', 'Category', 'Quantity to Add',
        'Unit Cost', 'Selling Price', 'Reorder Level', 'Expiry Date (YYYY-MM-DD)', 'Batch No',
    ];

    /** Rows of the import sheet that get the catalog dropdown. */
    private const DROPDOWN_ROWS = 1000;

    public static function empty(Merchant $merchant): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = self::importSheet($spreadsheet);
        $catalog = self::catalogEntries($merchant->id);

        if ($first = $catalog->first()) {
            $sheet->fromArray([$first['label'], '', '', '', 10, 4500, 5500, 5, '', ''], null, 'A2', true);
            $sheet->getStyle('A2:J2')->getFont()->setItalic(true);
            $sheet->getStyle('A2:J2')->getFont()->getColor()->setRGB('9CA3AF');
            $sheet->getComment('A2')->getText()->createTextRun(
                'Example row — edit or delete before uploading. Pick the product from the dropdown; leave Barcode blank to have one generated.'
            );
        }

        self::addCatalogSheet($spreadsheet, $catalog);

        return $spreadsheet;
    }

    public static function forMerchant(Merchant $merchant): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = self::importSheet($spreadsheet);
        $catalog = self::catalogEntries($merchant->id);
        $labels = $catalog->mapWithKeys(fn ($e) => [$e['product']->id.'-'.($e['variant']?->id ?? '') => $e['label']]);

        $row = 2;

        foreach ($merchant->inventoryItems()->with('category')->orderBy('name')->get() as $item) {
            if (! $item->barcode) {
                $item->update(['barcode' => InventoryItem::generateUniqueBarcode()]);
            }

            $sheet->fromArray([
                $labels[$item->product_id.'-'.($item->product_variant_id ?? '')] ?? $labels[$item->product_id.'-'] ?? $item->name,
                $item->barcode,
                $item->sku,
                $item->category?->name,
                0,
                $item->unit_cost,
                $item->unit_price,
                $item->reorder_level,
                $item->expiry_date?->toDateString(),
                $item->batch_no,
            ], null, "A{$row}", true);
            $row++;
        }

        self::addCatalogSheet($spreadsheet, $catalog);

        return $spreadsheet;
    }

    /**
     * One entry per pack size of every catalog product the seller may stock
     * (approved, plus their own pending proposals). The label ends with a
     * code like [V12] or [P7] so the importer can find it again exactly.
     *
     * @return Collection<int, array{code: string, label: string, product: Product, variant: ?ProductVariant}>
     */
    public static function catalogEntries(int $merchantId): Collection
    {
        return Product::usableBy($merchantId)
            ->with(['variants' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('id'), 'category', 'animalType'])
            ->orderBy('name')
            ->get()
            ->flatMap(function (Product $product) {
                if ($product->variants->isEmpty()) {
                    return [['code' => 'P'.$product->id, 'label' => $product->displayName().' [P'.$product->id.']', 'product' => $product, 'variant' => null]];
                }

                return $product->variants->map(fn (ProductVariant $variant) => [
                    'code' => 'V'.$variant->id,
                    'label' => self::itemName($product, $variant).' [V'.$variant->id.']',
                    'product' => $product,
                    'variant' => $variant,
                ]);
            })
            ->values();
    }

    /** The stock item name for a catalog product and pack size. */
    public static function itemName(Product $product, ?ProductVariant $variant): string
    {
        if (! $variant || Str::contains(Str::lower($product->name), Str::lower($variant->name))) {
            return $variant ? $product->name : $product->displayName();
        }

        return $product->name.' '.$variant->name;
    }

    protected static function importSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET);
        $sheet->fromArray(self::HEADERS, null, 'A1', true);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getComment('A1')->getText()->createTextRun('Pick from the dropdown. The full list is on the "Catalog" sheet.');

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $sheet;
    }

    /** @param  Collection<int, array{label: string, product: Product, variant: ?ProductVariant}>  $catalog */
    protected static function addCatalogSheet(Spreadsheet $spreadsheet, Collection $catalog): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::CATALOG_SHEET);
        $sheet->fromArray(['Catalog Product', 'Animal', 'Category', 'Unit', 'Suggested Price'], null, 'A1', true);
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $row = 2;
        foreach ($catalog as $entry) {
            $sheet->fromArray([
                $entry['label'],
                $entry['product']->animalType?->name,
                $entry['product']->category?->name,
                $entry['variant']?->selling_unit ?? $entry['product']->unit,
                $entry['variant']?->reference_price,
            ], null, "A{$row}", true);
            $row++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ($catalog->isNotEmpty()) {
            $validation = $spreadsheet->getSheet(0)->getDataValidation('A2:A'.self::DROPDOWN_ROWS);
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Not in the catalog')
                ->setError('Pick a product from the list. Ask the admin to add it to the catalog if it is missing.')
                ->setFormula1("'".self::CATALOG_SHEET."'!\$A\$2:\$A\$".($row - 1));
        }

        $spreadsheet->setActiveSheetIndex(0);
    }
}
