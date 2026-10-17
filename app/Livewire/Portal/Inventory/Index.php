<?php

namespace App\Livewire\Portal\Inventory;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\InventoryExcelImporter;
use App\Services\InventoryExcelTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Layout('layouts.portal', ['title' => 'Inventory'])]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $showItemForm = false;

    public string $name = '';

    /** @var array<int, array{id:int,name:string,quantity_on_hand:string,unit:?string}> */
    public array $nameMatches = [];

    public string $sku = '';

    public string $barcode = '';

    public string $category_id = '';

    public bool $addingNewCategory = false;

    public string $newCategoryName = '';

    public string $branch_id = '';

    public bool $addingNewBranch = false;

    public string $newBranchName = '';

    public string $expiry_date = '';

    public string $unit = '';

    public string $reorder_level = '0';

    public string $unit_cost = '';

    public string $unit_price = '';

    public $image;

    public ?int $movingItemId = null;

    public string $movementType = 'in';

    public string $movementQuantity = '';

    public string $categoryFilter = '';

    public bool $showImportForm = false;

    public $importFile;

    public function updatedName(string $value): void
    {
        $trimmed = trim($value);

        if (mb_strlen($trimmed) < 2) {
            $this->nameMatches = [];

            return;
        }

        $this->nameMatches = InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->where('name', 'like', "%{$trimmed}%")
            ->limit(5)
            ->get(['id', 'name', 'quantity_on_hand', 'unit'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'quantity_on_hand' => $i->quantity_on_hand,
                'unit' => $i->unit,
            ])
            ->all();
    }

    public function useExistingItem(int $itemId): void
    {
        $this->reset(['name', 'sku', 'barcode', 'branch_id', 'expiry_date', 'unit', 'reorder_level', 'unit_cost', 'unit_price', 'image', 'showItemForm', 'nameMatches']);
        $this->reorder_level = '0';
        $this->startMovement($itemId);
    }

    public function generateBarcode(): void
    {
        $this->barcode = InventoryItem::generateUniqueBarcode();
    }

    public function updatedCategoryId(string $value): void
    {
        $this->addingNewCategory = $value === '__new__';
    }

    public function saveNewCategory(): void
    {
        $this->validate(['newCategoryName' => ['required', 'string', 'max:255']]);

        $category = InventoryCategory::firstOrCreate([
            'merchant_id' => Auth::user()->merchant_id,
            'name' => $this->newCategoryName,
        ]);

        $this->category_id = (string) $category->id;
        $this->newCategoryName = '';
        $this->addingNewCategory = false;
    }

    public function updatedBranchId(string $value): void
    {
        $this->addingNewBranch = $value === '__new__';
    }

    public function saveNewBranch(): void
    {
        $this->validate(['newBranchName' => ['required', 'string', 'max:255']]);

        $branch = Branch::create([
            'merchant_id' => Auth::user()->merchant_id,
            'name' => $this->newBranchName,
        ]);

        $this->branch_id = (string) $branch->id;
        $this->newBranchName = '';
        $this->addingNewBranch = false;
    }

    public function filterByCategory(?int $categoryId): void
    {
        $this->categoryFilter = $categoryId ? (string) $categoryId : '';
        $this->resetPage();
    }

    public function addItem()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'expiry_date' => ['nullable', 'date'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:inventory_items,barcode'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $item = InventoryItem::create([
            'merchant_id' => Auth::user()->merchant_id,
            'category_id' => $this->category_id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'sku' => $this->sku ?: null,
            'barcode' => $this->barcode ?: null,
            'unit' => $this->unit ?: null,
            'reorder_level' => $this->reorder_level,
            'unit_cost' => $this->unit_cost ?: null,
            'unit_price' => $this->unit_price ?: null,
            'expiry_date' => $this->expiry_date ?: null,
        ]);

        if ($this->image) {
            $item->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        $this->reset(['name', 'sku', 'barcode', 'category_id', 'branch_id', 'addingNewBranch', 'newBranchName', 'addingNewCategory', 'newCategoryName', 'expiry_date', 'unit', 'reorder_level', 'unit_cost', 'unit_price', 'image', 'showItemForm', 'nameMatches']);
        $this->reorder_level = '0';
        session()->flash('status', 'Inventory item added.');
    }

    public function startMovement(int $itemId)
    {
        $this->movingItemId = $itemId;
        $this->movementType = 'in';
        $this->movementQuantity = '';
    }

    public function saveMovement()
    {
        $this->validate([
            'movementQuantity' => ['required', 'numeric', 'min:0.01'],
            'movementType' => ['required', 'in:in,out,adjustment'],
        ]);

        $item = InventoryItem::where('merchant_id', Auth::user()->merchant_id)->findOrFail($this->movingItemId);

        DB::transaction(function () use ($item) {
            StockMovement::create([
                'merchant_id' => Auth::user()->merchant_id,
                'inventory_item_id' => $item->id,
                'type' => $this->movementType,
                'quantity' => $this->movementQuantity,
                'movement_date' => now()->toDateString(),
                'recorded_by' => Auth::id(),
            ]);

            $delta = $this->movementType === 'out' ? -$this->movementQuantity : $this->movementQuantity;
            $item->increment('quantity_on_hand', $delta);
        });

        $this->reset(['movingItemId', 'movementQuantity']);
        session()->flash('status', 'Stock movement recorded.');
    }

    public function downloadEmptyTemplate()
    {
        return $this->streamSpreadsheet(InventoryExcelTemplate::empty(), 'inventory-import-template.xlsx');
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
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls']]);

        $merchant = Auth::user()->merchant;
        ['rows' => $rows, 'errors' => $errors] = InventoryExcelImporter::parse($this->importFile->getRealPath());

        $created = 0;
        $restocked = 0;

        DB::transaction(function () use ($merchant, $rows, &$created, &$restocked) {
            foreach ($rows as $row) {
                $result = InventoryExcelImporter::resolveItem($merchant, $row);
                $result['created'] ? $created++ : $restocked++;

                if ($row['quantity'] > 0) {
                    $result['item']->increment('quantity_on_hand', $row['quantity']);

                    StockMovement::create([
                        'merchant_id' => $merchant->id,
                        'inventory_item_id' => $result['item']->id,
                        'type' => StockMovement::TYPE_IN,
                        'quantity' => $row['quantity'],
                        'reference' => 'excel_import',
                        'movement_date' => now()->toDateString(),
                        'recorded_by' => Auth::id(),
                    ]);
                }
            }
        });

        $this->reset(['importFile', 'showImportForm']);

        $summary = $created + $restocked === 0
            ? 'No rows found in that file.'
            : "Import complete: {$created} new product".($created === 1 ? '' : 's').", {$restocked} existing product".($restocked === 1 ? '' : 's').' restocked.';

        if (! empty($errors)) {
            $summary .= ' '.count($errors).' row'.(count($errors) === 1 ? '' : 's').' skipped: '.implode(' ', array_slice($errors, 0, 5));
        }

        session()->flash('status', $summary);
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;

        $items = InventoryItem::where('merchant_id', $merchantId)
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->with(['category', 'branch'])
            ->orderBy('name')
            ->paginate(10);

        $expiringSoon = Auth::user()->merchant->expiringSoonItems()->orderBy('expiry_date')->get();

        return view('livewire.portal.inventory.index', [
            'items' => $items,
            'categories' => InventoryCategory::where('merchant_id', $merchantId)->withCount('items')->orderBy('name')->get(),
            'branches' => Branch::where('merchant_id', $merchantId)->orderBy('name')->get(),
            'expiringSoon' => $expiringSoon,
        ]);
    }
}
