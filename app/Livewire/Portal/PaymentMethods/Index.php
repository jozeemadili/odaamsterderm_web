<?php

namespace App\Livewire\Portal\PaymentMethods;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Payment Methods'])]
class Index extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $type = PaymentMethod::TYPE_OTHER;

    public function addMethod(): void
    {
        $merchantId = Auth::user()->merchant_id;

        $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:payment_methods,name,NULL,id,merchant_id,'.$merchantId],
            'type' => ['required', 'in:'.implode(',', [
                PaymentMethod::TYPE_CASH,
                PaymentMethod::TYPE_MOBILE_MONEY,
                PaymentMethod::TYPE_BANK,
                PaymentMethod::TYPE_CARD,
                PaymentMethod::TYPE_OTHER,
            ])],
        ]);

        PaymentMethod::create([
            'merchant_id' => $merchantId,
            'name' => $this->name,
            'slug' => Str::slug($this->name, '_'),
            'type' => $this->type,
            'sort_order' => PaymentMethod::where('merchant_id', $merchantId)->max('sort_order') + 1,
        ]);

        $this->reset(['name', 'type', 'showForm']);
        session()->flash('status', 'Payment method added.');
    }

    public function toggleActive(int $id): void
    {
        $method = PaymentMethod::where('merchant_id', Auth::user()->merchant_id)->findOrFail($id);
        $method->update(['is_active' => ! $method->is_active]);
    }

    public function render()
    {
        $methods = PaymentMethod::where('merchant_id', Auth::user()->merchant_id)
            ->orderBy('sort_order')
            ->get();

        return view('livewire.portal.payment-methods.index', [
            'methods' => $methods,
        ]);
    }
}
