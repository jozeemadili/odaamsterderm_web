<?php

namespace App\Livewire\Portal\Payments;

use App\Models\PaymentRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.portal', ['title' => 'Payments'])]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $amount = '';

    public string $payer_name = '';

    public string $payment_date;

    public string $notes = '';

    public $proof;

    public function mount()
    {
        $this->payment_date = now()->toDateString();
    }

    public function save()
    {
        $this->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $payment = PaymentRecord::create([
            'merchant_id' => Auth::user()->merchant_id,
            'amount' => $this->amount,
            'payer_name' => $this->payer_name ?: null,
            'payment_date' => $this->payment_date,
            'notes' => $this->notes ?: null,
            'status' => PaymentRecord::STATUS_RECORDED,
            'recorded_by' => Auth::id(),
        ]);

        $payment->addMedia($this->proof->getRealPath())
            ->usingFileName($this->proof->getClientOriginalName())
            ->toMediaCollection('proof_of_payment');

        $this->reset(['amount', 'payer_name', 'notes', 'proof']);
        $this->payment_date = now()->toDateString();
        session()->flash('status', 'Payment recorded and sent for verification.');
    }

    public function render()
    {
        $payments = PaymentRecord::where('merchant_id', Auth::user()->merchant_id)
            ->latest('payment_date')
            ->paginate(15);

        return view('livewire.portal.payments.index', compact('payments'));
    }
}
