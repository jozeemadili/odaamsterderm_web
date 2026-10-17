<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantResource;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function show(Request $request)
    {
        return new MerchantResource($request->user()->merchant);
    }

    public function update(Request $request)
    {
        $merchant = $request->user()->merchant;

        $data = $request->validate([
            'business_name' => ['sometimes', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'business_type_id' => ['nullable', 'integer', 'exists:business_types,id'],
            'physical_address' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $merchant->update($data);

        return new MerchantResource($merchant->fresh());
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => ['required', 'image', 'max:2048']]);

        $merchant = $request->user()->merchant;
        $merchant->addMedia($request->file('logo')->getRealPath())
            ->usingFileName($request->file('logo')->getClientOriginalName())
            ->toMediaCollection('logo');

        return new MerchantResource($merchant->fresh());
    }
}
