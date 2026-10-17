<?php

namespace App\Livewire\Admin\Merchants;

use App\Models\Merchant;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'Merchants'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $kycFilter = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $merchants = Merchant::query()
            ->when($this->search, fn ($q) => $q->where('business_name', 'like', "%{$this->search}%")
                ->orWhere('owner_name', 'like', "%{$this->search}%"))
            ->when($this->kycFilter, fn ($q) => $q->where('kyc_status', $this->kycFilter))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.merchants.index', compact('merchants'));
    }
}
