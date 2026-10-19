<?php

namespace App\Livewire\Portal\Transfers;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal', ['title' => 'Stock Transfers'])]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $from_branch_id = '';

    public string $to_branch_id = '';

    public string $notes = '';

    public string $productSearch = '';

    /** @var array<int, array{inventory_item_id:int, name:string, unit:?string, available:float, quantity:string}> */
    public array $lines = [];

    public function updatedShowForm(bool $open): void
    {
        if ($open && $this->from_branch_id === '') {
            $this->from_branch_id = (string) (StoreAccess::currentId() ?? StoreAccess::defaultStoreId() ?? '');
        }
    }

    public function updatedFromBranchId(): void
    {
        $this->lines = [];
        $this->productSearch = '';
        if ($this->to_branch_id === $this->from_branch_id) {
            $this->to_branch_id = '';
        }
    }

    public function addLine(int $itemId): void
    {
        $item = InventoryItem::forUser(null, false)->where('branch_id', (int) $this->from_branch_id)->find($itemId);

        if (! $item || collect($this->lines)->contains('inventory_item_id', $item->id)) {
            $this->productSearch = '';

            return;
        }

        $this->lines[] = [
            'inventory_item_id' => $item->id,
            'name' => $item->name,
            'unit' => $item->unit,
            'available' => (float) $item->availableQuantity(),
            'quantity' => '1',
        ];
        $this->productSearch = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function send(StockTransferService $service)
    {
        abort_unless(StockTransfer::canSend(), 403);

        $this->validate([
            'from_branch_id' => ['required'],
            'to_branch_id' => ['required', 'different:from_branch_id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ], [
            'to_branch_id.different' => 'Send to a different store.',
            'lines.required' => 'Add at least one product.',
        ]);

        $transfer = $service->send(Auth::user(), (int) $this->from_branch_id, (int) $this->to_branch_id, $this->lines, $this->notes);

        session()->flash('status', "Transfer {$transfer->number} sent. The stock has left the store and will arrive when the other store receives it.");

        return $this->redirect(route('portal.transfers.show', $transfer), navigate: true);
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;
        $fromStores = Branch::whereIn('id', StoreAccess::branchIds())->orderBy('name')->get();

        $matches = collect();
        if ($this->from_branch_id !== '' && mb_strlen(trim($this->productSearch)) >= 1) {
            $matches = InventoryItem::forUser(null, false)
                ->where('branch_id', (int) $this->from_branch_id)
                ->where('name', 'like', '%'.trim($this->productSearch).'%')
                ->where('quantity_on_hand', '>', 0)
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        return view('livewire.portal.transfers.index', [
            'transfers' => StockTransfer::forUser()->with(['fromBranch', 'toBranch'])->withCount('items')->latest('id')->paginate(15),
            'fromStores' => $fromStores,
            'toStores' => Branch::where('merchant_id', $merchantId)->when($this->from_branch_id !== '', fn ($q) => $q->where('id', '!=', (int) $this->from_branch_id))->orderBy('name')->get(),
            'matches' => $matches,
            'canSend' => StockTransfer::canSend(),
            'storeIds' => StoreAccess::branchIds(),
        ]);
    }
}
