<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SalesRecordResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::where('merchant_id', Auth::user()->merchant_id)
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('search').'%')
                    ->orWhere('phone', 'like', '%'.$request->string('search').'%');
            }))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return CustomerResource::collection($customers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $merchantId = Auth::user()->merchant_id;
        $count = Customer::where('merchant_id', $merchantId)->count() + 1;

        $customer = Customer::create([
            ...$data,
            'merchant_id' => $merchantId,
            'customer_code' => 'CUST-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT),
        ]);

        return new CustomerResource($customer);
    }

    public function show(Request $request, Customer $customer)
    {
        abort_unless($customer->merchant_id === $request->user()->merchant_id, 403);

        return (new CustomerResource($customer))->additional([
            'sales' => SalesRecordResource::collection($customer->sales()->latest('sale_date')->limit(20)->get()),
            'invoices' => InvoiceResource::collection($customer->invoices()->latest('issue_date')->limit(20)->get()),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless($customer->merchant_id === $request->user()->merchant_id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update($data);

        return new CustomerResource($customer->fresh());
    }
}
