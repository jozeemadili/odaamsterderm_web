<?php

namespace App\Livewire\Portal\StockReceipts;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\SupplierTransaction;
use App\Services\InventoryExcelImporter;
use App\Services\InventoryExcelTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Layout('layouts.portal', ['title' => 'Stock Receipt'])]
class Show extends Component
{
    use WithFileUploads;

    public StockReceipt $receipt;

    public string $inventory_item_id = '';

    public string $productSearch = '';

    public ?string $selectedProductLabel = null;

    /** @var array<int, array{id:int,name:string,quantity_on_hand:string,unit:?string,unit_price:?string}> */
    public array $productMatches = [];

    public string $quantity = '';

    public string $unit_cost = '';

    public bool $addingNewProduct = false;

    public string $new_name = '';

    public string $new_sku = '';

    public string $new_unit = '';

    public string $new_unit_price = '';

    public string $new_branch_id = '';

    public $document;

    public bool $showImportForm = false;

    public $importFile;

    public function mount(StockReceipt $receipt)
    {
        abort_unless($receipt->merchant_id === Auth::user()->merchant_id, 403);
        $this->receipt = $receipt;
    }

    public function updatedProductSearch(string $value): void
    {
        $trimmed = trim($value);
        $this->selectedProductLabel = null;
        $this->inventory_item_id = '';

        if (mb_strlen($trimmed) < 1) {
            $this->productMatches = [];

            return;
        }

        $this->productMatches = InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->where('name', 'like', "%{$trimmed}%")
            ->limit(8)
            ->get(['id', 'name', 'quantity_on_hand', 'unit', 'unit_price'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'quantity_on_hand' => $i->quantity_on_hand,
                'unit' => $i->unit,
                'unit_price' => $i->unit_price,
            ])
            ->all();
    }

    public function selectProduct(int $itemId): void
    {
        $match = collect($this->productMatches)->firstWhere('id', $itemId);

        if (! $match) {
            return;
        }

        $this->inventory_item_id = (string) $itemId;
        $this->selectedProductLabel = $match['name'];
        $this->productSearch = '';
        $this->productMatches = [];
    }

    public function clearSelectedProduct(): void
    {
        $this->inventory_item_id = '';
        $this->selectedProductLabel = null;
        $this->productSearch = '';
        $this->productMatches = [];
    }

    public function addExistingItem()
    {
        $this->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::where('merchant_id', Auth::user()->merchant_id)->findOrFail($this->inventory_item_id);

        $this->storeLine($item->id, (float) $this->quantity, (float) $this->unit_cost);

        $this->reset(['inventory_item_id', 'quantity', 'unit_cost', 'productSearch', 'selectedProductLabel', 'productMatches']);
    }

    public function uploadDocument()
    {
        $this->validate([
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $this->receipt->addMedia($this->document->getRealPath())
            ->usingFileName($this->document->getClientOriginalName())
            ->toMediaCollection('documents');

        $this->reset(['document']);
        session()->flash('status', 'Supplier document uploaded.');
    }

    public function removeDocument(int $mediaId)
    {
        $media = $this->receipt->media()->where('id', $mediaId)->first();
        $media?->delete();
    }

    public function addNewProduct()
    {
        $this->validate([
            'new_name' => ['required', 'string', 'max:255'],
            'new_unit' => ['nullable', 'string', 'max:255'],
            'new_unit_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::create([
            'merchant_id' => Auth::user()->merchant_id,
            'branch_id' => $this->new_branch_id ?: null,
            'name' => $this->new_name,
            'sku' => $this->new_sku ?: null,
            'unit' => $this->new_unit ?: null,
            'unit_cost' => $this->unit_cost,
            'unit_price' => $this->new_unit_price ?: null,
            'quantity_on_hand' => 0,
            'reorder_level' => 0,
        ]);

        $this->storeLine($item->id, (float) $this->quantity, (float) $this->unit_cost);

        $this->reset(['new_name', 'new_sku', 'new_unit', 'new_unit_price', 'new_branch_id', 'quantity', 'unit_cost', 'addingNewProduct']);
    }

    protected function storeLine(int $inventoryItemId, float $quantity, float $unitCost): void
    {
        abort_unless($this->receipt->isPending(), 403);

        StockReceiptItem::create([
            'stock_receipt_id' => $this->receipt->id,
            'inventory_item_id' => $inventoryItemId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $quantity * $unitCost,
        ]);

        $this->receipt->update(['total_amount' => $this->receipt->items()->sum('subtotal')]);
    }

    public function downloadEmptyTemplate()
    {
        return $this->streamSpreadsheet(InventoryExcelTemplate::empty(), 'stock-receipt-import-template.xlsx');
    }

    public function downloadCurrentProducts()
    {
        return $this->streamSpreadsheet(InventoryExcelTemplate::forMerchant(Auth::user()->merchant), 'inventory-current-products.xlsx');
    }

    protected function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel()
    {
        abort_unless($this->receipt->isPending(), 403);

        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls']]);

        $merchant = Auth::user()->merchant;
        ['rows' => $rows, 'errors' => $errors] = InventoryExcelImporter::parse($this->importFile->getRealPath());

        $count = 0;

        DB::transaction(function () use ($merchant, $rows, &$count) {
            foreach ($rows as $row) {
                if ($row['quantity'] <= 0) {
                    continue;
                }

                $result = InventoryExcelImporter::resolveItem($merchant, $row);
                $this->storeLine($result['item']->id, $row['quantity'], $row['unit_cost'] ?? 0);
                $count++;
            }
        });

        $this->reset(['importFile', 'showImportForm']);

        $summary = $count === 0
            ? 'No rows with a quantity greater than 0 were found in that file.'
            : "{$count} line".($count === 1 ? '' : 's').' added from the uploaded file.';

        if (! empty($errors)) {
            $summary .= ' '.count($errors).' row'.(count($errors) === 1 ? '' : 's').' skipped: '.implode(' ', array_slice($errors, 0, 5));
        }

        session()->flash('status', $summary);
    }

    public function removeItem(int $lineId)
    {
        abort_unless($this->receipt->isPending(), 403);

        $this->receipt->items()->where('id', $lineId)->delete();
        $this->receipt->update(['total_amount' => $this->receipt->items()->sum('subtotal')]);
    }

    public function approve()
    {
        abort_unless(Auth::user()->can('approve-stock-receipts'), 403);
        abort_unless($this->receipt->isPending(), 403);
        abort_if($this->receipt->items()->count() === 0, 422);

        DB::transaction(function () {
            foreach ($this->receipt->items as $line) {
                InventoryItem::where('id', $line->inventory_item_id)->increment('quantity_on_hand', $line->quantity);

                StockMovement::create([
                    'merchant_id' => $this->receipt->merchant_id,
                    'inventory_item_id' => $line->inventory_item_id,
                    'type' => StockMovement::TYPE_IN,
                    'quantity' => $line->quantity,
                    'reference' => 'purchase_receipt',
                    'notes' => "Stock receipt #{$this->receipt->id}",
                    'movement_date' => now()->toDateString(),
                    'recorded_by' => Auth::id(),
                ]);
            }

            if ($this->receipt->supplier_id) {
                SupplierTransaction::create([
                    'merchant_id' => $this->receipt->merchant_id,
                    'supplier_id' => $this->receipt->supplier_id,
                    'type' => 'purchase',
                    'amount' => $this->receipt->total_amount,
                    'description' => "Stock receipt #{$this->receipt->id}",
                    'transaction_date' => now()->toDateString(),
                    'recorded_by' => Auth::id(),
                ]);
            }

            $this->receipt->update([
                'status' => StockReceipt::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        session()->flash('status', 'Stock receipt approved and posted. Inventory has been updated.');
    }

    public function reject()
    {
        abort_unless(Auth::user()->can('approve-stock-receipts'), 403);
        abort_unless($this->receipt->isPending(), 403);

        $this->receipt->update([
            'status' => StockReceipt::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        session()->flash('status', 'Stock receipt rejected. No inventory changes were made.');
    }

    public function render()
    {
        $this->receipt->refresh();

        return view('livewire.portal.stock-receipts.show', [
            'lines' => $this->receipt->items()->with('inventoryItem')->get(),
            'documents' => $this->receipt->getMedia('documents'),
            'branches' => Branch::where('merchant_id', Auth::user()->merchant_id)->orderBy('name')->get(),
        ]);
    }
}
