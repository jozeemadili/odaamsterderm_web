<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Market\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\StoreAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A seller's incoming marketplace orders and fulfilment actions. */
class SellerOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'open');

        $orders = Order::forUser($request->user())
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'closed', fn ($q) => $q->whereIn('status', [Order::REJECTED, Order::CANCELLED]))
            ->when(! in_array($status, ['open', 'closed', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->with(['branch', 'merchant'])
            ->latest()
            ->paginate(25);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        return new OrderResource($order->load(['items', 'branch', 'merchant', 'events']));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'status' => ['required', Rule::in([Order::ACCEPTED, Order::PREPARING, Order::READY, Order::COMPLETED, Order::REJECTED])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->orders->advance($order, $data['status'], $request->user(), $data['reason'] ?? null);

        return new OrderResource($order->load(['items', 'branch', 'merchant', 'events']));
    }

    protected function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->merchant_id === $request->user()->merchant_id
            && StoreAccess::allows($order->branch_id, $request->user()), 404);
    }
}
