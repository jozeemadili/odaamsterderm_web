<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockReceiptResource;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\SupplierTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockReceiptController extends Controller
{
    public function index(Request $request)
    {
        $receipts = StockReceipt::where('merchant_id', Auth::user()->merchant_id)
            ->with('supplier')
            ->withCount('items')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return StockReceiptResource::collection($receipts);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'receipt_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $receipt = StockReceipt::create([
            ...$data,
            'merchant_id' => Auth::user()->merchant_id,
            'status' => StockReceipt::STATUS_PENDING,
            'created_by' => Auth::id(),
        ]);

        return new StockReceiptResource($receipt);
    }

    public function show(Request $request, StockReceipt $receipt)
    {
        $this->authorizeReceipt($request, $receipt);

        return new StockReceiptResource($receipt->load(['supplier', 'items.inventoryItem']));
    }

    public function addItem(Request $request, StockReceipt $receipt)
    {
        $this->authorizeReceipt($request, $receipt);
        abort_unless($receipt->isPending(), 403, 'This receipt has already been finalized.');

        $data = $request->validate([
            'inventory_item_id' => ['required_without:new_product_name', 'nullable', 'exists:inventory_items,id'],
            'new_product_name' => ['required_without:inventory_item_id', 'nullable', 'string', 'max:255'],
            'new_product_unit' => ['nullable', 'string', 'max:255'],
            'new_product_unit_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $merchantId = $receipt->merchant_id;

        if (! empty($data['inventory_item_id'])) {
            $item = InventoryItem::where('merchant_id', $merchantId)->findOrFail($data['inventory_item_id']);
        } else {
            $item = InventoryItem::create([
                'merchant_id' => $merchantId,
                'name' => $data['new_product_name'],
                'unit' => $data['new_product_unit'] ?? null,
                'unit_cost' => $data['unit_cost'],
                'unit_price' => $data['new_product_unit_price'] ?? null,
                'quantity_on_hand' => 0,
                'reorder_level' => 0,
            ]);
        }

        StockReceiptItem::create([
            'stock_receipt_id' => $receipt->id,
            'inventory_item_id' => $item->id,
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'],
            'subtotal' => $data['quantity'] * $data['unit_cost'],
        ]);

        $receipt->update(['total_amount' => $receipt->items()->sum('subtotal')]);

        return new StockReceiptResource($receipt->fresh()->load(['supplier', 'items.inventoryItem']));
    }

    public function removeItem(Request $request, StockReceipt $receipt, StockReceiptItem $item)
    {
        $this->authorizeReceipt($request, $receipt);
        abort_unless($receipt->isPending(), 403, 'This receipt has already been finalized.');
        abort_unless($item->stock_receipt_id === $receipt->id, 404);

        $item->delete();
        $receipt->update(['total_amount' => $receipt->items()->sum('subtotal')]);

        return new StockReceiptResource($receipt->fresh()->load(['supplier', 'items.inventoryItem']));
    }

    public function approve(Request $request, StockReceipt $receipt)
    {
        $this->authorizeReceipt($request, $receipt);
        abort_unless($request->user()->can('approve-stock-receipts'), 403);
        abort_unless($receipt->isPending(), 403);
        abort_if($receipt->items()->count() === 0, 422, 'Add at least one product before approving.');

        DB::transaction(function () use ($receipt, $request) {
            foreach ($receipt->items as $line) {
                InventoryItem::where('id', $line->inventory_item_id)->increment('quantity_on_hand', $line->quantity);

                StockMovement::create([
                    'merchant_id' => $receipt->merchant_id,
                    'inventory_item_id' => $line->inventory_item_id,
                    'type' => StockMovement::TYPE_IN,
                    'quantity' => $line->quantity,
                    'reference' => 'purchase_receipt',
                    'notes' => "Stock receipt #{$receipt->id}",
                    'movement_date' => now()->toDateString(),
                    'recorded_by' => $request->user()->id,
                ]);
            }

            if ($receipt->supplier_id) {
                SupplierTransaction::create([
                    'merchant_id' => $receipt->merchant_id,
                    'supplier_id' => $receipt->supplier_id,
                    'type' => 'purchase',
                    'amount' => $receipt->total_amount,
                    'description' => "Stock receipt #{$receipt->id}",
                    'transaction_date' => now()->toDateString(),
                    'recorded_by' => $request->user()->id,
                ]);
            }

            $receipt->update([
                'status' => StockReceipt::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return new StockReceiptResource($receipt->fresh()->load(['supplier', 'items.inventoryItem']));
    }

    public function reject(Request $request, StockReceipt $receipt)
    {
        $this->authorizeReceipt($request, $receipt);
        abort_unless($request->user()->can('approve-stock-receipts'), 403);
        abort_unless($receipt->isPending(), 403);

        $receipt->update([
            'status' => StockReceipt::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return new StockReceiptResource($receipt->fresh());
    }

    protected function authorizeReceipt(Request $request, StockReceipt $receipt): void
    {
        abort_unless($receipt->merchant_id === $request->user()->merchant_id, 403);
    }
}
