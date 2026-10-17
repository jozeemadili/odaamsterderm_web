<?php

namespace App\Livewire\Portal\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Customer Detail'])]
class Show extends Component
{
    public Customer $customer;

    public function mount(Customer $customer)
    {
        abort_unless($customer->merchant_id === Auth::user()->merchant_id, 403);
        $this->customer = $customer;
    }

    public function render()
    {
        $sales = $this->customer->sales()
            ->with(['items.inventoryItem', 'recordedBy'])
            ->latest('sale_date')
            ->latest('id')
            ->get();

        $totalPaid = (float) $sales->sum('amount');
        $totalDiscount = (float) $sales->sum('discount_amount');

        // Estimated profit: revenue minus each line's cost at current unit cost.
        $estimatedCost = 0.0;
        $itemsPurchased = collect();

        foreach ($sales as $sale) {
            foreach ($sale->items as $line) {
                $estimatedCost += (float) $line->quantity * (float) ($line->inventoryItem?->unit_cost ?? 0);

                $key = $line->item_name;
                $existing = $itemsPurchased->get($key, ['name' => $key, 'quantity' => 0, 'amount' => 0]);
                $itemsPurchased->put($key, [
                    'name' => $key,
                    'quantity' => $existing['quantity'] + (float) $line->quantity,
                    'amount' => $existing['amount'] + (float) $line->subtotal,
                ]);
            }
        }

        $estimatedProfit = $totalPaid - $estimatedCost;

        $invoices = $this->customer->invoices()
            ->latest('issue_date')
            ->latest('id')
            ->get();

        return view('livewire.portal.customers.show', [
            'customer' => $this->customer,
            'sales' => $sales,
            'totalPaid' => $totalPaid,
            'totalDiscount' => $totalDiscount,
            'estimatedProfit' => $estimatedProfit,
            'itemsPurchased' => $itemsPurchased->sortByDesc('amount')->values(),
            'invoices' => $invoices,
        ]);
    }
}
