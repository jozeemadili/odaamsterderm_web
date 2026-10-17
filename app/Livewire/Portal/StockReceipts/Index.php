<?php

namespace App\Livewire\Portal\StockReceipts;

use App\Models\StockReceipt;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal', ['title' => 'Stock Receipts'])]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $supplier_id = '';

    public string $reference_no = '';

    public string $receipt_date;

    public string $notes = '';

    public function mount()
    {
        $this->receipt_date = now()->toDateString();
    }

    public function create()
    {
        $this->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'receipt_date' => ['required', 'date'],
        ]);

        $receipt = StockReceipt::create([
            'merchant_id' => Auth::user()->merchant_id,
            'supplier_id' => $this->supplier_id ?: null,
            'reference_no' => $this->reference_no ?: null,
            'receipt_date' => $this->receipt_date,
            'notes' => $this->notes ?: null,
            'status' => StockReceipt::STATUS_PENDING,
            'created_by' => Auth::id(),
        ]);

        return $this->redirect(route('portal.stock-receipts.show', $receipt), navigate: true);
    }

    public function render()
    {
        $receipts = StockReceipt::where('merchant_id', Auth::user()->merchant_id)
            ->with('supplier')
            ->withCount('items')
            ->latest()
            ->paginate(15);

        return view('livewire.portal.stock-receipts.index', [
            'receipts' => $receipts,
            'suppliers' => Supplier::where('merchant_id', Auth::user()->merchant_id)->orderBy('name')->get(),
        ]);
    }
}
