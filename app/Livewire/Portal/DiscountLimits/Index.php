<?php

namespace App\Livewire\Portal\DiscountLimits;

use App\Models\DiscountLimit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Discount Limits'])]
class Index extends Component
{
    /** @var array<string, string> */
    public array $limits = [];

    public array $roleLabels = [
        'merchant_sales' => 'Sales',
        'merchant_supervisor' => 'Supervisor',
        'merchant_manager' => 'Manager',
        'merchant_accountant' => 'Accountant',
        'merchant_owner' => 'Owner',
    ];

    public function mount()
    {
        abort_unless(Auth::user()->can('manage-discount-limits'), 403);

        $merchantId = Auth::user()->merchant_id;

        foreach (array_keys($this->roleLabels) as $role) {
            $this->limits[$role] = (string) DiscountLimit::maxPercentFor($merchantId, $role);
        }
    }

    public function save()
    {
        $this->validate([
            'limits.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $merchantId = Auth::user()->merchant_id;

        foreach ($this->limits as $role => $percent) {
            DiscountLimit::updateOrCreate(
                ['merchant_id' => $merchantId, 'role' => $role],
                ['max_percent' => $percent]
            );
        }

        session()->flash('status', 'Discount limits updated.');
    }

    public function render()
    {
        return view('livewire.portal.discount-limits.index');
    }
}
