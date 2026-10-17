<?php

namespace App\Livewire\Portal\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Suppliers'])]
class Index extends Component
{
    public bool $showSupplierForm = false;

    public string $name = '';

    public string $contact_person = '';

    public string $phone = '';

    public ?int $transactingSupplierId = null;

    public string $transactionType = 'purchase';

    public string $transactionAmount = '';

    public string $transactionDate;

    public string $transactionDescription = '';

    public function mount()
    {
        $this->transactionDate = now()->toDateString();
    }

    public function addSupplier()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Supplier::create([
            'merchant_id' => Auth::user()->merchant_id,
            'name' => $this->name,
            'contact_person' => $this->contact_person ?: null,
            'phone' => $this->phone ?: null,
        ]);

        $this->reset(['name', 'contact_person', 'phone', 'showSupplierForm']);
        session()->flash('status', 'Supplier added.');
    }

    public function startTransaction(int $supplierId)
    {
        $this->transactingSupplierId = $supplierId;
        $this->transactionType = 'purchase';
        $this->transactionAmount = '';
        $this->transactionDescription = '';
        $this->transactionDate = now()->toDateString();
    }

    public function saveTransaction()
    {
        $this->validate([
            'transactionAmount' => ['required', 'numeric', 'min:0.01'],
            'transactionType' => ['required', 'in:purchase,payment'],
            'transactionDate' => ['required', 'date'],
        ]);

        $supplier = Supplier::where('merchant_id', Auth::user()->merchant_id)->findOrFail($this->transactingSupplierId);

        SupplierTransaction::create([
            'merchant_id' => Auth::user()->merchant_id,
            'supplier_id' => $supplier->id,
            'type' => $this->transactionType,
            'amount' => $this->transactionAmount,
            'description' => $this->transactionDescription,
            'transaction_date' => $this->transactionDate,
            'recorded_by' => Auth::id(),
        ]);

        $this->reset(['transactingSupplierId', 'transactionAmount', 'transactionDescription']);
        session()->flash('status', 'Supplier transaction recorded.');
    }

    public function render()
    {
        $suppliers = Supplier::where('merchant_id', Auth::user()->merchant_id)->orderBy('name')->get();

        return view('livewire.portal.suppliers.index', compact('suppliers'));
    }
}
