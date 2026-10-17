<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::where('merchant_id', Auth::user()->merchant_id)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return SupplierResource::collection($suppliers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplier = Supplier::create([...$data, 'merchant_id' => Auth::user()->merchant_id]);

        return new SupplierResource($supplier);
    }

    public function show(Request $request, Supplier $supplier)
    {
        abort_unless($supplier->merchant_id === $request->user()->merchant_id, 403);

        return new SupplierResource($supplier);
    }

    public function update(Request $request, Supplier $supplier)
    {
        abort_unless($supplier->merchant_id === $request->user()->merchant_id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplier->update($data);

        return new SupplierResource($supplier->fresh());
    }
}
