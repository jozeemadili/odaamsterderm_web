<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Market\OrderResource;
use App\Http\Resources\Market\PublicStoreResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

/** Customer cart, checkout, orders and notifications. */
class CustomerOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function cart(Request $request)
    {
        $groups = $this->orders->cartByStore($request->user());

        return response()->json([
            'stores' => collect($groups)->map(fn ($g) => [
                'store' => (new PublicStoreResource($g['store']->load('merchant')))->resolve($request),
                'total' => $g['total'],
                'lines' => collect($g['lines'])->map(fn ($l) => [
                    'offer_id' => $l['offer']->id,
                    'product_id' => $l['offer']->product_id,
                    'name' => $l['offer']->product?->displayName() ?? $l['offer']->name,
                    'unit' => $l['offer']->product?->unit ?? $l['offer']->unit,
                    'unit_price' => (float) $l['offer']->unit_price,
                    'quantity' => (float) $l['cart_item']->quantity,
                    'available_quantity' => (float) $l['offer']->marketplace_quantity,
                    'line_total' => $l['line_total'],
                    'problem' => $l['problem'],
                ])->values(),
            ])->values(),
            'total' => collect($groups)->sum('total'),
            'count' => collect($groups)->sum(fn ($g) => count($g['lines'])),
        ]);
    }

    public function addToCart(Request $request)
    {
        $data = $request->validate([
            'offer_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $this->orders->addToCart($request->user(), $data['offer_id'], (float) ($data['quantity'] ?? 1));

        return $this->cart($request);
    }

    public function setQuantity(Request $request, int $offer)
    {
        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0']]);

        $this->orders->setCartQuantity($request->user(), $offer, (float) $data['quantity']);

        return $this->cart($request);
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $orders = $this->orders->checkout($request->user(), $data['note'] ?? null, $data['phone'] ?? null);

        return OrderResource::collection($orders->load(['items', 'branch.merchant', 'merchant', 'events']))
            ->response()->setStatusCode(201);
    }

    public function index(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->with(['branch.merchant', 'merchant'])->latest()->paginate(20)
        );
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return new OrderResource($order->load(['items', 'branch.merchant', 'merchant', 'events']));
    }

    public function cancel(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $order = $this->orders->cancelByCustomer($order, $request->user(), $data['reason'] ?? null);

        return new OrderResource($order->load(['items', 'branch.merchant', 'merchant', 'events']));
    }
}
