<?php

namespace App\Livewire\Admin\Payments;

use App\Models\PaymentRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'Payment Verification'])]
class Index extends Component
{
    use WithPagination;

    public string $statusFilter = 'recorded';

    public function verify(int $paymentId)
    {
        PaymentRecord::findOrFail($paymentId)->update([
            'status' => PaymentRecord::STATUS_VERIFIED,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        session()->flash('status', 'Payment verified.');
    }

    public function flag(int $paymentId)
    {
        PaymentRecord::findOrFail($paymentId)->update([
            'status' => PaymentRecord::STATUS_FLAGGED,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        session()->flash('status', 'Payment flagged for follow-up.');
    }

    public function render()
    {
        $payments = PaymentRecord::with('merchant')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.payments.index', compact('payments'));
    }
}
