<?php

namespace App\Livewire\Portal\Inventory;

use App\Models\InventoryItem;
use App\Models\StockReceiptItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Picqer\Barcode\BarcodeGeneratorSVG;

#[Layout('layouts.portal', ['title' => 'Product Detail'])]
class Show extends Component
{
    public InventoryItem $item;

    public function mount(InventoryItem $item)
    {
        abort_unless($item->merchant_id === Auth::user()->merchant_id, 403);
        $this->item = $item->load('branch', 'category');
    }

    public function barcodeSvg(): ?string
    {
        if (! $this->item->barcode) {
            return null;
        }

        try {
            $generator = new BarcodeGeneratorSVG();

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
