<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(private StockTransferService $service) {}

    public function index(Request $request)
    {
        $transfers = StockTransfer::forUser($request->user())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['fromBranch', 'toBranch', 'sender', 'receiver'])
            ->withCount('items')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return StockTransferResource::collection($transfers)->additional(['can_send' => StockTransfer::canSend($request->user())]);
    }

    public function store(Request $request)
    {
        abort_unless(StockTransfer::canSend($request->user()), 403, 'Only owners and managers can send stock to another store.');

        $data = $request->validate([
            'from_store_id' => ['required', 'integer'],
            'to_store_id' => ['required', 'integer', 'different:from_store_id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $transfer = $this->service->send($request->user(), $data['from_store_id'], $data['to_store_id'], $data['items'], $data['notes'] ?? null);

        return (new StockTransferResource($this->full($transfer)))->response()->setStatusCode(201);
    }

    public function show(Request $request, StockTransfer $transfer)
    {
        abort_unless($transfer->canBeSeenBy($request->user()), 403);

        return new StockTransferResource($this->full($transfer));
    }

    public function receive(Request $request, StockTransfer $transfer)
    {
        abort_unless($transfer->canBeSeenBy($request->user()), 403);

        return new StockTransferResource($this->full($this->service->receive($transfer, $request->user())));
    }

    public function cancel(Request $request, StockTransfer $transfer)
    {
        abort_unless($transfer->canBeSeenBy($request->user()), 403);

        return new StockTransferResource($this->full($this->service->cancel($transfer, $request->user())));
    }

    private function full(StockTransfer $transfer): StockTransfer
    {
        return $transfer->load(['items', 'fromBranch', 'toBranch', 'sender', 'receiver']);
    }
}
