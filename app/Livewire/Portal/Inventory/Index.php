<?php

namespace App\Livewire\Portal\Inventory;

use App\Livewire\Concerns\PicksCatalogProduct;
use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryExcelImporter;
use App\Services\InventoryExcelTemplate;
use App\Services\InventoryService;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Layout('layouts.portal', ['title' => 'Inventory'])]
class Index extends Component
{
    use PicksCatalogProduct;
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

    public string $batch_no = '';

    public string $unit = '';

    public string $reorder_level = '0';

    public string $unit_cost = '';

    public string $unit_price = '';

    public $image;

    public $backImage;

    public ?int $movingItemId = null;

    public string $movementType = 'in';

    public string $movementQuantity = '';

    public string $movementReason = '';

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

        $this->nameMatches = InventoryItem::forUser()
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
        $this->reset(['name', 'sku', 'barcode', 'branch_id', 'expiry_date', 'unit', 'reorder_level', 'unit_cost', 'unit_price', 'image', 'backImage', 'showItemForm', 'nameMatches']);
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

    protected function catalogProductSelected(Product $product): void
    {
        if (trim($this->name) === '') {
            $this->name = $product->displayName();
            $this->nameMatches = [];
        }

        if (trim($this->unit) === '') {
            $this->unit = $product->unit;
        }
    }

    /** New stock goes into the store being worked in, unless another is picked. */
    public function updatedShowItemForm(bool $open): void
    {
        if ($open && $this->branch_id === '' && ($current = StoreAccess::currentId())) {
            $this->branch_id = (string) $current;
        }
    }

    public function addItem()
    {
        $this->validate($this->catalogProductRules() + [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'branch_id' => ['required', Rule::in(StoreAccess::branchIds())],
            'expiry_date' => ['nullable', 'date'],
            'batch_no' => ['nullable', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:inventory_items,barcode'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
            'backImage' => ['nullable', 'image', 'max:4096'],
        ]);

        $item = InventoryItem::create([
            'merchant_id' => Auth::user()->merchant_id,
            'category_id' => $this->category_id,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'name' => $this->name,
            'sku' => $this->sku ?: null,
            'barcode' => $this->barcode ?: null,
            'unit' => $this->unit ?: null,
            'reorder_level' => $this->reorder_level,
            'unit_cost' => $this->unit_cost ?: null,
            'unit_price' => $this->unit_price ?: null,
            'expiry_date' => $this->expiry_date ?: null,
            'batch_no' => trim($this->batch_no) ?: null,
        ]);

        if ($this->image) {
            $item->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        if ($this->backImage) {
            $item->addMedia($this->backImage->getRealPath())
                ->usingFileName($this->backImage->getClientOriginalName())
                ->toMediaCollection(InventoryItem::BACK_PHOTO);
        }

        $this->reset(['product_id', 'product_variant_id', 'variantOptions', 'batch_no', 'productLabel', 'catalogSearch', 'catalogMatches', 'name', 'sku', 'barcode', 'category_id', 'branch_id', 'addingNewBranch', 'newBranchName', 'addingNewCategory', 'newCategoryName', 'expiry_date', 'unit', 'reorder_level', 'unit_cost', 'unit_price', 'image', 'backImage', 'showItemForm', 'nameMatches']);
        $this->reorder_level = '0';
        session()->flash('status', 'Inventory item added.');
    }

    public function startMovement(int $itemId)
    {
        $this->movingItemId = $itemId;
        $this->movementType = 'in';
        $this->movementQuantity = '';
        $this->movementReason = '';
    }

    public function saveMovement(InventoryService $inventory)
    {
        $this->validate([
            'movementQuantity' => ['required', 'numeric', $this->movementType === 'adjustment' ? 'min:0' : 'min:0.01'],
            'movementType' => ['required', 'in:in,out,adjustment'],
            'movementReason' => [$this->movementType === 'adjustment' ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        $item = InventoryItem::forUser()->findOrFail($this->movingItemId);
        $quantity = (float) $this->movementQuantity;
        $meta = ['reference' => 'manual', 'reason' => $this->movementReason ?: null];

        try {
            match ($this->movementType) {
                'in' => $inventory->stockIn($item, $quantity, $meta),
                'out' => $inventory->stockOut($item, $quantity, StockMovement::TYPE_OUT, $meta),
                'adjustment' => $inventory->adjustTo($item, $quantity, $this->movementReason, $meta),
            };
        } catch (ValidationException $e) {
            $this->addError('movementQuantity', collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset(['movingItemId', 'movementQuantity', 'movementReason']);
        session()->flash('status', 'Stock movement recorded.');
    }

    public function downloadEmptyTemplate()
    {
        return $this->streamSpreadsheet(InventoryExcelTemplate::empty(Auth::user()->merchant), 'inventory-import-template.xlsx');
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
        ['rows' => $rows, 'errors' => $errors] = InventoryExcelImporter::parse($this->importFile->getRealPath(), $merchant);
        $storeId = StoreAccess::defaultStoreId();

        $created = 0;
        $restocked = 0;

        DB::transaction(function () use ($merchant, $rows, $storeId, &$created, &$restocked) {
            foreach ($rows as $row) {
                $result = InventoryExcelImporter::resolveItem($merchant, $row, $storeId);
                $result['created'] ? $created++ : $restocked++;

                if ($row['quantity'] > 0) {
                    app(InventoryService::class)->stockIn($result['item'], (float) $row['quantity'], ['reference' => 'excel_import']);
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

        $items = InventoryItem::forUser()
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->with(['category', 'branch'])
            ->orderBy('name')
            ->paginate(10);

        $expiringSoon = Auth::user()->merchant->expiringSoonItems()
            ->whereIn('branch_id', StoreAccess::scopeIds())->orderBy('expiry_date')->get();

        return view('livewire.portal.inventory.index', [
            'items' => $items,
            'categories' => InventoryCategory::where('merchant_id', $merchantId)->withCount('items')->orderBy('name')->get(),
            'branches' => Branch::whereIn('id', StoreAccess::branchIds())->orderBy('name')->get(),
            'expiringSoon' => $expiringSoon,
        ]);
    }
}
