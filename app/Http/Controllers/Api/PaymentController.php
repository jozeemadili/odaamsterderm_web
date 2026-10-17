<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentRecordResource;
use App\Models\PaymentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = PaymentRecord::where('merchant_id', Auth::user()->merchant_id)
            ->latest('payment_date')
            ->paginate($request->integer('per_page', 20));

        return PaymentRecordResource::collection($payments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'proof_of_payment' => ['nullable', 'image', 'max:4096'],
        ]);

        $payment = PaymentRecord::create([
            'merchant_id' => Auth::user()->merchant_id,
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'],
            'payment_date' => $data['payment_date'],
            'notes' => $data['notes'] ?? null,
            'status' => PaymentRecord::STATUS_RECORDED,
            'recorded_by' => Auth::id(),
        ]);

        if ($request->hasFile('proof_of_payment')) {
            $payment->addMedia($request->file('proof_of_payment')->getRealPath())
                ->usingFileName($request->file('proof_of_payment')->getClientOriginalName())
                ->toMediaCollection('proof_of_payment');
        }

        return new PaymentRecordResource($payment->fresh());
    }
}
