<?php

namespace App\Livewire\Portal\Inventory;

use App\Livewire\Concerns\PicksCatalogProduct;
use App\Models\InventoryItem;
use App\Models\StockReceiptItem;
use App\Services\InventoryService;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Picqer\Barcode\BarcodeGeneratorSVG;

#[Layout('layouts.portal', ['title' => 'Product Detail'])]
class Show extends Component
{
    use PicksCatalogProduct;
    use WithFileUploads;

    /** New photo of this stock item (shown to customers on the marketplace). */
    public $photo;

    /** Photo of the back of the pack (ingredients, dosage, expiry). */
    public $backPhoto;

    public InventoryItem $item;

    public bool $mpVisible = false;

    public string $mpQuantity = '0';

    public bool $mpActive = true;

    public function mount(InventoryItem $item)
    {
        abort_unless($item->merchant_id === Auth::user()->merchant_id && StoreAccess::allows($item->branch_id), 403);
        $this->item = $item->load('branch', 'category', 'product', 'merchant');
        $this->fillMarketplaceFields();
    }

    public function updatedPhoto(): void
    {
        $this->savePhoto('photo', 'front');
    }

    public function updatedBackPhoto(): void
    {
        $this->savePhoto('backPhoto', 'back');
    }

    protected function savePhoto(string $field, string $side): void
    {
        $this->validate([$field => ['image', 'max:4096']], [], [$field => $side.' photo']);

        $this->item->addMedia($this->{$field}->getRealPath())
            ->usingFileName($this->{$field}->getClientOriginalName())
            ->toMediaCollection(InventoryItem::photoCollection($side));

        $this->reset($field);
        $this->item->refresh();
        session()->flash('status', ucfirst($side).' photo saved. Customers see it next to your offer on the marketplace.');
    }

    public function removePhoto(string $side = 'front'): void
    {
        $this->item->clearMediaCollection(InventoryItem::photoCollection($side));
        $this->item->refresh();
        session()->flash('status', ucfirst($side).' photo removed.');
    }

    protected function fillMarketplaceFields(): void
    {
        $this->product_id = $this->item->product_id;
        $this->product_variant_id = $this->item->product_variant_id;
        $this->loadVariantOptions();
        $this->productLabel = $this->item->product
            ? $this->item->product->displayName().($this->item->product->isPending() ? ' (pending approval)' : '')
            : '';
        $this->mpVisible = $this->item->is_marketplace_visible;
        $this->mpQuantity = rtrim(rtrim((string) $this->item->marketplace_quantity, '0'), '.') ?: '0';
        $this->mpActive = $this->item->marketplace_status !== InventoryItem::MARKET_INACTIVE;
    }

    public function saveCatalogLink(InventoryService $inventory): void
    {
        $this->validate($this->catalogProductRules());

        $this->item->update(['product_id' => $this->product_id, 'product_variant_id' => $this->product_variant_id]);

        // An item without an approved catalog product can't stay listed.
        if ($this->item->is_marketplace_visible && $this->item->fresh()->marketplaceBlockers()) {
            $inventory->updateMarketplace($this->item, false, (float) $this->item->marketplace_quantity, $this->mpActive);
        }

        $this->item->refresh()->load('product');
        $this->fillMarketplaceFields();
        session()->flash('status', $this->product_id ? 'Linked to the catalog product.' : 'Catalog link removed.');
    }

    public function saveMarketplace(InventoryService $inventory): void
    {
        $this->validate(['mpQuantity' => ['required', 'numeric', 'min:0']], [], ['mpQuantity' => 'marketplace quantity']);

        try {
            $inventory->updateMarketplace($this->item, $this->mpVisible, (float) $this->mpQuantity, $this->mpActive);
        } catch (ValidationException $e) {
            $this->addError('mpQuantity', collect($e->errors())->flatten()->first());

            return;
        }

        $this->item->refresh();
        $this->fillMarketplaceFields();
        session()->flash('status', 'Marketplace settings saved.');
    }

    public function barcodeSvg(): ?string
    {
        if (! $this->item->barcode) {
            return null;
        }

        try {
            $generator = new BarcodeGeneratorSVG;

            return $generator->getBarcode($this->item->barcode, $generator::TYPE_CODE_128);
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        $item = $this->item;

        // Load every sale line for this product with its parent sale, so an
        // overall (whole-sale) discount can be apportioned back to this
        // product's share of that sale — an item-level SUM() alone would
        // overstate revenue whenever a basket discount was applied.
        $allSaleItems = $item->saleItems()->with('sale')->get();

        $unitsSold = 0.0;
        $netRevenue = 0.0;
        $itemDiscount = 0.0;
        $apportionedOverallDiscount = 0.0;

        foreach ($allSaleItems as $line) {
            $unitsSold += (float) $line->quantity;
            $itemDiscount += (float) $line->discount_amount;

            $sale = $line->sale;
            $saleShare = ($sale && (float) $sale->subtotal > 0)
                ? (float) $line->subtotal / (float) $sale->subtotal
                : 0.0;
            $lineOverallDiscount = $sale ? (float) $sale->discount_amount * $saleShare : 0.0;

            $apportionedOverallDiscount += $lineOverallDiscount;
            $netRevenue += (float) $line->subtotal - $lineOverallDiscount;
        }

        $totalDiscountGiven = $itemDiscount + $apportionedOverallDiscount;

        $unitsReceived = (float) StockReceiptItem::where('inventory_item_id', $item->id)
            ->whereHas('stockReceipt', fn ($q) => $q->where('status', 'approved'))
            ->sum('quantity');

        $totalPurchaseCost = (float) StockReceiptItem::where('inventory_item_id', $item->id)
            ->whereHas('stockReceipt', fn ($q) => $q->where('status', 'approved'))
            ->sum('subtotal');

        $estimatedCogs = $unitsSold * (float) ($item->unit_cost ?? 0);
        $estimatedProfit = $netRevenue - $estimatedCogs;
        $marginPercent = $netRevenue > 0 ? round(($estimatedProfit / $netRevenue) * 100, 1) : 0;

        return view('livewire.portal.inventory.show', [
            'item' => $item,
            'unitsSold' => $unitsSold,
            'revenue' => $netRevenue,
            'discountGiven' => $totalDiscountGiven,
            'unitsReceived' => $unitsReceived,
            'totalPurchaseCost' => $totalPurchaseCost,
            'estimatedCogs' => $estimatedCogs,
            'estimatedProfit' => $estimatedProfit,
            'marginPercent' => $marginPercent,
            'purchases' => StockReceiptItem::where('inventory_item_id', $item->id)
                ->whereHas('stockReceipt', fn ($q) => $q->where('status', 'approved'))
                ->with('stockReceipt.supplier')
                ->latest()
                ->limit(20)
                ->get(),
            'sales' => $allSaleItems->sortByDesc('id')->take(20)->load('sale.customer', 'sale.recordedBy'),
            'movements' => $item->stockMovements()
                ->with('recordedBy')
                ->latest('movement_date')
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
    }
}
