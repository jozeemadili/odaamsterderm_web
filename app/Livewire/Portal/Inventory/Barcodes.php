<?php

namespace App\Livewire\Portal\Inventory;

use App\Models\InventoryItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Picqer\Barcode\BarcodeGeneratorPNG;

#[Layout('layouts.portal', ['title' => 'Barcode Labels'])]
class Barcodes extends Component
{
    /** @var array<int, bool> */
    public array $selected = [];

    /** @var array<int, string> */
    public array $copies = [];

    public bool $showProductName = true;

    public bool $showPrice = true;

    public function mount(): void
    {
        $items = InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->whereNotNull('barcode')
            ->pluck('id');

        foreach ($items as $id) {
            $this->selected[$id] = true;
            $this->copies[$id] = '1';
        }
    }

    public function selectAll(): void
    {
        $this->selected = array_map(fn () => true, $this->selected);
    }

    public function selectNone(): void
    {
        $this->selected = array_map(fn () => false, $this->selected);
    }

    public function downloadPdf()
    {
        $merchant = Auth::user()->merchant;

        $selectedIds = collect($this->selected)->filter()->keys()->all();

        $items = InventoryItem::where('merchant_id', $merchant->id)
            ->whereIn('id', $selectedIds)
            ->whereNotNull('barcode')
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            session()->flash('status', 'Select at least one product with a barcode first.');

            return;
        }

        $generator = new BarcodeGeneratorPNG;
        $labels = [];

        foreach ($items as $item) {
            $dataUri = 'data:image/png;base64,'.base64_encode(
                $generator->getBarcode($item->barcode, $generator::TYPE_CODE_128, 2, 40)
            );

            $copies = max(1, (int) ($this->copies[$item->id] ?? 1));

            for ($i = 0; $i < $copies; $i++) {
                $labels[] = [
                    'name' => $item->name,
                    'price' => $item->unit_price,
                    'barcode' => $item->barcode,
                    'image' => $dataUri,
                ];
            }
        }

        $pdf = Pdf::loadView('exports.barcode-labels', [
            'merchant' => $merchant,
            'shopLogoDataUri' => $merchant->logoDataUri(),
            'labels' => $labels,
            'showProductName' => $this->showProductName,
            'showPrice' => $this->showPrice,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print ($pdf->output()), 'barcode-labels.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;

        $items = InventoryItem::where('merchant_id', $merchantId)
            ->whereNotNull('barcode')
            ->orderBy('name')
            ->get();

        $withoutBarcodeCount = InventoryItem::where('merchant_id', $merchantId)
            ->whereNull('barcode')
            ->count();

        return view('livewire.portal.inventory.barcodes', [
            'items' => $items,
            'withoutBarcodeCount' => $withoutBarcodeCount,
        ]);
    }
}
