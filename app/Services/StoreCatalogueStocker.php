<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Puts every pack size of the approved catalogue into one store, so the
 * store carries the whole range.
 *
 * Quantities, prices, batches and expiry dates come from the taxonomy
 * workbook's Store_Inventory rows for one of its stores (ST-AMS-TEG by
 * default); a pack without a row there gets the workbook's suggested
 * price and no stock. A pack is listed online when it has a price (and,
 * for workbook rows, when the row says so and it hasn't expired).
 *
 * Packs the store already has an item for are left untouched, so running
 * it again only adds what's new and never overwrites live stock.
 */
class StoreCatalogueStocker
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @return array{added: int, already_stocked: int, with_stock: int, listed_online: int, without_price: int}
     */
    public function stock(Branch $store, string $workbookStore = 'ST-AMS-TEG', ?string $workbookPath = null): array
    {
        $rows = $this->workbookRows($workbookPath ?? CatalogTaxonomyImporter::defaultPath(), $workbookStore);
        $counts = ['added' => 0, 'already_stocked' => 0, 'with_stock' => 0, 'listed_online' => 0, 'without_price' => 0];

        $variants = ProductVariant::query()
            ->whereHas('product', fn ($q) => $q->where('status', Product::STATUS_APPROVED))
            ->with('product.category.parent')
            ->orderBy('product_id')->orderBy('sort_order')
            ->get();

        DB::transaction(function () use ($store, $rows, $variants, &$counts) {
            foreach ($variants as $variant) {
                $exists = InventoryItem::where('branch_id', $store->id)
                    ->where(fn ($q) => $q->where('product_variant_id', $variant->id)
                        ->orWhere(fn ($q) => $q->where('product_id', $variant->product_id)->whereNull('product_variant_id')))
                    ->exists();

                if ($exists) {
                    $counts['already_stocked']++;

                    continue;
                }

                $this->addItem($store, $variant, $rows[$variant->sku] ?? null, $counts);
                $counts['added']++;
            }
        });

        return $counts;
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @param  array<string, int>  $counts
     */
    private function addItem(Branch $store, ProductVariant $variant, ?array $row, array &$counts): void
    {
        $product = $variant->product;
        $price = $row['selling_price_tzs'] ?? $variant->reference_price;
        $expiry = $this->date($row['expiry_date'] ?? null);

        $item = InventoryItem::create([
            'merchant_id' => $store->merchant_id,
            'branch_id' => $store->id,
            'category_id' => $this->categoryFor($store->merchant_id, $product),
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => $product->name.' - '.$variant->name,
            'sku' => $variant->sku,
            'barcode' => $variant->gtin,
            'unit' => $variant->selling_unit ?: $product->unit,
            'unit_price' => $price,
            'unit_cost' => $row['cost_price_tzs'] ?? null,
            'reorder_level' => (float) ($row['reorder_level'] ?? 0),
            'batch_no' => $row['batch_no'] ?? null,
            'expiry_date' => $expiry,
        ]);

        $quantity = (float) ($row['qty_on_hand'] ?? 0);
        if ($quantity > 0) {
            $this->inventory->stockIn($item, $quantity, ['reference' => 'catalogue_stock', 'notes' => 'Opening stock from the catalogue workbook']);
            $counts['with_stock']++;
        }

        if ($price === null) {
            $counts['without_price']++;

            return;
        }

        $listed = $row === null || ($this->bool($row['listed_online']) && ! $expiry?->isPast());
        if (! $listed || $item->fresh()->marketplaceBlockers()) {
            return;
        }

        $online = $row === null ? 0 : min((float) $row['online_qty'], $quantity);

        try {
            $this->inventory->updateMarketplace($item->fresh(), true, $online);
            $counts['listed_online']++;
        } catch (ValidationException) {
            // Left off the marketplace; the seller can list it by hand.
        }
    }

    /** The seller's own stock category, named after the product's top catalogue category. */
    private function categoryFor(int $merchantId, Product $product): ?int
    {
        $name = $product->category?->parent?->name ?? $product->category?->name;

        return $name ? InventoryCategory::firstOrCreate(['merchant_id' => $merchantId, 'name' => $name])->id : null;
    }

    /**
     * The workbook store's rows, keyed by SKU.
     *
     * @return array<string, array<string, mixed>>
     */
    private function workbookRows(string $path, string $storeCode): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Taxonomy workbook not found: {$path}");
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Store_Inventory']);
        $sheet = $reader->load($path)->getSheetByName('Store_Inventory')
            ?? throw new RuntimeException("Taxonomy workbook has no 'Store_Inventory' sheet.");

        $rows = $sheet->toArray(null, true, false, false);
        $header = array_map(fn ($h) => trim((string) $h), array_shift($rows) ?? []);

        $bySku = [];
        foreach ($rows as $row) {
            $row = array_combine($header, $row);
            if (($row['store_id'] ?? null) === $storeCode && $row['sku']) {
                $bySku[$row['sku']] = $row;
            }
        }

        if (! $bySku) {
            throw new RuntimeException("The workbook has no Store_Inventory rows for store {$storeCode}.");
        }

        return $bySku;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return match (true) {
            is_numeric($value) => CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay(),
            is_string($value) && $value !== '' => CarbonImmutable::parse($value)->startOfDay(),
            default => null,
        };
    }

    private function bool(mixed $value): bool
    {
        return $value === true || strtoupper((string) $value) === 'TRUE';
    }
}
