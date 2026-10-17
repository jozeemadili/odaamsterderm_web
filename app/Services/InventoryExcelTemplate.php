<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Merchant;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class InventoryExcelTemplate
{
    public const HEADERS = [
        'Product Name', 'Barcode', 'SKU', 'Category', 'Unit',
        'Quantity to Add', 'Unit Cost', 'Selling Price', 'Reorder Level', 'Expiry Date (YYYY-MM-DD)',
    ];

    public static function empty(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import inventory');
        $sheet->fromArray(self::HEADERS, null, 'A1', true);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        $sheet->fromArray(['Rice 2kg', '', '', 'Groceries', 'pcs', 10, 4500, 5500, 5, ''], null, 'A2', true);
        $sheet->getStyle('A2:J2')->getFont()->setItalic(true);
        $sheet->getStyle('A2:J2')->getFont()->getColor()->setRGB('9CA3AF');
        $sheet->getComment('A2')->getText()->createTextRun(
            'Example row — edit or delete before uploading. Leave Barcode blank to have one generated automatically.'
        );

        self::autoSize($sheet);

        return $spreadsheet;
    }

    public static function forMerchant(Merchant $merchant): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import inventory');
        $sheet->fromArray(self::HEADERS, null, 'A1', true);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        $row = 2;

        foreach ($merchant->inventoryItems()->with('category')->orderBy('name')->get() as $item) {
            if (! $item->barcode) {
                $item->update(['barcode' => InventoryItem::generateUniqueBarcode()]);
            }

            $sheet->fromArray([
                $item->name,
                $item->barcode,
                $item->sku,
                $item->category?->name,
                $item->unit,
                0,
                $item->unit_cost,
                $item->unit_price,
                $item->reorder_level,
                $item->expiry_date?->toDateString(),
            ], null, "A{$row}", true);
            $row++;
        }

        self::autoSize($sheet);

        return $spreadsheet;
    }

    protected static function autoSize($sheet): void
    {
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
