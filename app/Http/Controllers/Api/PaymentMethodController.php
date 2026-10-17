<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $methods = PaymentMethod::where('merchant_id', Auth::user()->merchant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return PaymentMethodResource::collection($methods);
    }
}
