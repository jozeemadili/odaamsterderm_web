<?php

namespace App\Services;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Merchant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InventoryExcelImporter
{
    /**
     * Read the import sheet. A row must either name a catalog product or
     * carry the barcode of a product the seller already stocks; anything
     * else is skipped with an error.
     *
     * @return array{rows: array<int, array>, errors: array<int, string>}
     */
    public static function parse(string $path, Merchant $merchant): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName(InventoryExcelTemplate::SHEET) ?? $book->getSheet(0);
        $catalog = self::catalogLookup($merchant);
        $rows = [];
        $errors = [];

        for ($rowNum = 2; $rowNum <= $sheet->getHighestDataRow(); $rowNum++) {
            $label = trim((string) $sheet->getCell("A{$rowNum}")->getValue());
            $barcode = trim((string) $sheet->getCell("B{$rowNum}")->getValue()) ?: null;

            if ($label === '' && ! $barcode) {
                continue;
            }

            $entry = self::findInCatalog($catalog, $label);
            $known = $barcode && InventoryItem::where('merchant_id', $merchant->id)->where('barcode', $barcode)->exists();

            if (! $entry && ! $known) {
                $errors[] = "Row {$rowNum}: \"{$label}\" is not in the catalog — pick it from the dropdown.";

                continue;
            }

            $quantity = $sheet->getCell("E{$rowNum}")->getValue();

            if ($quantity !== null && $quantity !== '' && ! is_numeric($quantity)) {
                $errors[] = "Row {$rowNum}: \"Quantity to Add\" must be a number.";

                continue;
            }

            $unitCost = $sheet->getCell("F{$rowNum}")->getValue();
            $unitPrice = $sheet->getCell("G{$rowNum}")->getValue();
            $reorderLevel = $sheet->getCell("H{$rowNum}")->getValue();
            $expiryRaw = $sheet->getCell("I{$rowNum}")->getValue();

            $expiryDate = null;

            if ($expiryRaw !== null && $expiryRaw !== '') {
                try {
                    $expiryDate = is_numeric($expiryRaw)
                        ? Carbon::instance(ExcelDate::excelToDateTimeObject($expiryRaw))->toDateString()
                        : Carbon::parse($expiryRaw)->toDateString();
                } catch (\Throwable) {
                    $errors[] = "Row {$rowNum}: couldn't read the expiry date — it was left blank.";
                }
            }

            $product = $entry['product'] ?? null;
            $variant = $entry['variant'] ?? null;

            $rows[] = [
                'row' => $rowNum,
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'name' => $product ? InventoryExcelTemplate::itemName($product, $variant) : $label,
                'unit' => $variant?->selling_unit ?? $product?->unit,
                'barcode' => $barcode,
                'sku' => trim((string) $sheet->getCell("C{$rowNum}")->getValue()) ?: ($variant?->sku ?: null),
                'category' => trim((string) $sheet->getCell("D{$rowNum}")->getValue()) ?: $product?->category?->name,
                'quantity' => is_numeric($quantity) ? (float) $quantity : 0.0,
                'unit_cost' => is_numeric($unitCost) ? (float) $unitCost : null,
                'unit_price' => is_numeric($unitPrice) ? (float) $unitPrice : ($variant?->reference_price !== null ? (float) $variant->reference_price : null),
                'reorder_level' => is_numeric($reorderLevel) ? (float) $reorderLevel : 0,
                'expiry_date' => $expiryDate,
                'batch_no' => trim((string) $sheet->getCell("J{$rowNum}")->getValue()) ?: null,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Match an uploaded row to a product the seller already stocks — by
     * barcode, or by the same catalog product and pack size in that store —
     * or create a new one linked to the catalog. Never touches
     * quantity_on_hand: the caller decides how the row's quantity is applied
     * (direct stock-in for a plain inventory import, or a draft stock receipt
     * line that only affects stock once the receipt is approved).
     *
     * @return array{item: InventoryItem, created: bool}
     */
    public static function resolveItem(Merchant $merchant, array $row, ?int $branchId = null): array
    {
        $item = $row['barcode']
            ? InventoryItem::where('merchant_id', $merchant->id)->where('barcode', $row['barcode'])->first()
            : null;

        if (! $item && $row['product_id']) {
            $item = InventoryItem::where('merchant_id', $merchant->id)
                ->where('product_id', $row['product_id'])
                ->where('product_variant_id', $row['product_variant_id'])
                ->where('branch_id', $branchId)
                ->first();
        }

        if ($item) {
            return ['item' => $item, 'created' => false];
        }

        $categoryId = null;

        if ($row['category']) {
            $categoryId = InventoryCategory::firstOrCreate([
                'merchant_id' => $merchant->id,
                'name' => $row['category'],
            ])->id;
        }

        $item = InventoryItem::create([
            'merchant_id' => $merchant->id,
            'branch_id' => $branchId,
            'category_id' => $categoryId,
            'product_id' => $row['product_id'],
            'product_variant_id' => $row['product_variant_id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'barcode' => $row['barcode'] ?: InventoryItem::generateUniqueBarcode(),
            'unit' => $row['unit'],
            'quantity_on_hand' => 0,
            'reorder_level' => $row['reorder_level'],
            'unit_cost' => $row['unit_cost'],
            'unit_price' => $row['unit_price'],
            'expiry_date' => $row['expiry_date'],
            'batch_no' => $row['batch_no'],
        ]);

        return ['item' => $item, 'created' => true];
    }

    /** Catalog entries by code ("V12") and by lower-cased label without the code. */
    protected static function catalogLookup(Merchant $merchant): array
    {
        $byCode = [];
        $byLabel = [];

        foreach (InventoryExcelTemplate::catalogEntries($merchant->id) as $entry) {
            $byCode[$entry['code']] = $entry;
            $byLabel[Str::lower(trim(preg_replace('/\s*\[[PV]\d+\]$/', '', $entry['label'])))] ??= $entry;
        }

        return ['code' => $byCode, 'label' => $byLabel];
    }

    protected static function findInCatalog(array $catalog, string $label): ?array
    {
        if ($label === '') {
            return null;
        }

        if (preg_match('/\[([PV]\d+)\]\s*$/', $label, $m) && isset($catalog['code'][$m[1]])) {
            return $catalog['code'][$m[1]];
        }

        return $catalog['label'][Str::lower($label)] ?? null;
    }
}
