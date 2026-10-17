<?php

namespace App\Livewire\Portal\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal', ['title' => 'Customers'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $tin_number = '';

    public bool $credit_allowed = false;

    public string $credit_limit = '0';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        Customer::create([
            'merchant_id' => Auth::user()->merchant_id,
            'customer_code' => $this->nextCustomerCode(),
            'name' => $this->name,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'tin_number' => $this->tin_number ?: null,
            'credit_allowed' => $this->credit_allowed,
            'credit_limit' => $this->credit_allowed ? $this->credit_limit : 0,
        ]);

        $this->reset(['name', 'phone', 'email', 'address', 'tin_number', 'credit_allowed', 'credit_limit', 'showForm']);
        $this->credit_limit = '0';
        session()->flash('status', 'Customer added.');
    }

    protected function nextCustomerCode(): string
    {
        $merchantId = Auth::user()->merchant_id;
        $count = Customer::where('merchant_id', $merchantId)->count() + 1;

        return 'CUST-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        $customers = Customer::where('merchant_id', Auth::user()->merchant_id)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('customer_code', 'like', "%{$this->search}%");
            }))
            ->latest()
            ->paginate(15);

        return view('livewire.portal.customers.index', compact('customers'));
    }
}
