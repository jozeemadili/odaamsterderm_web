<?php

namespace App\Livewire\Portal\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal', ['title' => 'Invoices'])]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $customer_id = '';

    public string $issue_date;

    public string $due_date = '';

    public string $notes = '';

    // Filters
    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount()
    {
        $this->issue_date = now()->toDateString();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ]);

        $merchantId = Auth::user()->merchant_id;
        $number = 'INV-'.str_pad((string) (Invoice::where('merchant_id', $merchantId)->count() + 1), 5, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'merchant_id' => $merchantId,
            'customer_id' => $this->customer_id,
            'number' => $number,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date ?: null,
            'notes' => $this->notes ?: null,
            'created_by' => Auth::id(),
        ]);

        return $this->redirect(route('portal.invoices.show', $invoice), navigate: true);
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;

        $invoices = Invoice::where('merchant_id', $merchantId)
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('issue_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('issue_date', '<=', $this->dateTo))
            ->with('customer')
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15);

        return view('livewire.portal.invoices.index', [
            'invoices' => $invoices,
            'customers' => Customer::where('merchant_id', $merchantId)->orderBy('name')->get(),
        ]);
    }
}
