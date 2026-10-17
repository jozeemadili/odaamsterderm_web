<?php

namespace App\Services;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Merchant;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InventoryExcelImporter
{
    /**
     * @return array{rows: array<int, array>, errors: array<int, string>}
     */
    public static function parse(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = [];
        $errors = [];

        for ($rowNum = 2; $rowNum <= $sheet->getHighestDataRow(); $rowNum++) {
            $name = trim((string) $sheet->getCell("A{$rowNum}")->getValue());

            if ($name === '') {
                continue;
            }

            $quantity = $sheet->getCell("F{$rowNum}")->getValue();

            if ($quantity !== null && $quantity !== '' && ! is_numeric($quantity)) {
                $errors[] = "Row {$rowNum}: \"Quantity to Add\" must be a number.";

                continue;
            }

            $unitCost = $sheet->getCell("G{$rowNum}")->getValue();
            $unitPrice = $sheet->getCell("H{$rowNum}")->getValue();
            $reorderLevel = $sheet->getCell("I{$rowNum}")->getValue();
            $expiryRaw = $sheet->getCell("J{$rowNum}")->getValue();

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

            $rows[] = [
                'row' => $rowNum,
                'name' => $name,
                'barcode' => trim((string) $sheet->getCell("B{$rowNum}")->getValue()) ?: null,
                'sku' => trim((string) $sheet->getCell("C{$rowNum}")->getValue()) ?: null,
                'category' => trim((string) $sheet->getCell("D{$rowNum}")->getValue()) ?: null,
                'unit' => trim((string) $sheet->getCell("E{$rowNum}")->getValue()) ?: null,
                'quantity' => is_numeric($quantity) ? (float) $quantity : 0.0,
                'unit_cost' => is_numeric($unitCost) ? (float) $unitCost : null,
                'unit_price' => is_numeric($unitPrice) ? (float) $unitPrice : null,
                'reorder_level' => is_numeric($reorderLevel) ? (float) $reorderLevel : 0,
                'expiry_date' => $expiryDate,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Match an uploaded row to an existing product by barcode, or create a new one.
     * Never touches quantity_on_hand — the caller decides how the row's quantity
     * is applied (direct stock-in for a plain inventory import, or a draft stock
     * receipt line that only affects stock once the receipt is approved).
     *
     * @return array{item: InventoryItem, created: bool}
     */
    public static function resolveItem(Merchant $merchant, array $row): array
    {
        $item = $row['barcode']
            ? InventoryItem::where('merchant_id', $merchant->id)->where('barcode', $row['barcode'])->first()
            : null;

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
            'category_id' => $categoryId,
            'name' => $row['name'],
            'sku' => $row['sku'],
            'barcode' => $row['barcode'] ?: InventoryItem::generateUniqueBarcode(),
            'unit' => $row['unit'],
            'quantity_on_hand' => 0,
            'reorder_level' => $row['reorder_level'],
            'unit_cost' => $row['unit_cost'],
            'unit_price' => $row['unit_price'],
            'expiry_date' => $row['expiry_date'],
        ]);

        return ['item' => $item, 'created' => true];
    }
}
