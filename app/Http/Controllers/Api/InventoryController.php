<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Picqer\Barcode\BarcodeGeneratorPNG;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $items = InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('search').'%')
                    ->orWhere('sku', 'like', '%'.$request->string('search').'%')
                    ->orWhere('barcode', 'like', '%'.$request->string('search').'%');
            }))
            ->with(['category', 'branch'])
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return InventoryItemResource::collection($items);
    }

    public function show(Request $request, InventoryItem $item)
    {
        $this->authorizeItem($request, $item);

        return new InventoryItemResource($item->load(['category', 'branch']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:inventory_items,barcode'],
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'unit' => ['nullable', 'string', 'max:255'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'quantity_on_hand' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $item = InventoryItem::create([
            ...$data,
            'merchant_id' => Auth::user()->merchant_id,
            'barcode' => $data['barcode'] ?? InventoryItem::generateUniqueBarcode(),
            'reorder_level' => $data['reorder_level'] ?? 0,
            'quantity_on_hand' => $data['quantity_on_hand'] ?? 0,
        ]);

        return new InventoryItemResource($item);
    }

    public function update(Request $request, InventoryItem $item)
    {
        $this->authorizeItem($request, $item);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:inventory_items,barcode,'.$item->id],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'unit' => ['nullable', 'string', 'max:255'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $item->update($data);

        return new InventoryItemResource($item->fresh());
    }

    public function destroy(Request $request, InventoryItem $item)
    {
        $this->authorizeItem($request, $item);
        $item->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    /**
     * Barcode scanning: the Flutter app scans a code and hits this to find the
     * matching product. A 404 tells the app to offer "add new product" instead.
     */
    public function lookupBarcode(Request $request, string $code)
    {
        $item = InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->where('barcode', $code)
            ->with(['category', 'branch'])
            ->first();

        if (! $item) {
            return response()->json(['message' => 'No product found for this barcode.'], 404);
        }

        return new InventoryItemResource($item);
    }

    public function generateBarcode(Request $request, InventoryItem $item)
    {
        $this->authorizeItem($request, $item);
        $item->update(['barcode' => InventoryItem::generateUniqueBarcode()]);

        return new InventoryItemResource($item->fresh());
    }

    /**
     * Product photo upload — the same endpoint whether the Flutter app sourced
     * the image from the camera or the gallery; both arrive here as a normal
     * multipart file.
     */
    public function uploadImage(Request $request, InventoryItem $item)
    {
        $this->authorizeItem($request, $item);
        $request->validate(['image' => ['required', 'image', 'max:4096']]);

        $item->addMedia($request->file('image')->getRealPath())
            ->usingFileName($request->file('image')->getClientOriginalName())
            ->toMediaCollection('image');

        return new InventoryItemResource($item->fresh());
    }

    public function stockMovement(Request $request, InventoryItem $item)
    {
        $this->authorizeItem($request, $item);

        $data = $request->validate([
            'type' => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        StockMovement::create([
            'merchant_id' => $item->merchant_id,
            'inventory_item_id' => $item->id,
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'reference' => 'mobile_app',
            'movement_date' => now()->toDateString(),
            'recorded_by' => Auth::id(),
        ]);

        $delta = $data['type'] === 'out' ? -$data['quantity'] : $data['quantity'];
        $item->increment('quantity_on_hand', $delta);

        return new InventoryItemResource($item->fresh());
    }

    /**
     * Printable barcode label sheet (3-up, name + price optional) for the
     * given products — same PDF layout the web portal's "Barcode labels"
     * page generates, so labels printed from either place look identical.
     */
    public function barcodeLabelsPdf(Request $request)
    {
        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'show_product_name' => ['sometimes', 'boolean'],
            'show_price' => ['sometimes', 'boolean'],
            'copies' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $merchant = $request->user()->merchant;

        $items = InventoryItem::where('merchant_id', $merchant->id)
            ->whereIn('id', $data['item_ids'])
            ->whereNotNull('barcode')
            ->orderBy('name')
            ->get();

        abort_if($items->isEmpty(), 422, 'Select at least one product with a barcode.');

        $generator = new BarcodeGeneratorPNG;
        $copies = max(1, (int) ($data['copies'] ?? 1));
        $labels = [];

        foreach ($items as $item) {
            $dataUri = 'data:image/png;base64,'.base64_encode(
                $generator->getBarcode($item->barcode, $generator::TYPE_CODE_128, 2, 40)
            );

            for ($i = 0; $i < $copies; $i++) {
                $labels[] = [
                    'name' => $item->name,
                    'price' => $item->unit_price,
                    'barcode' => $item->barcode,
                    'image' => $dataUri,
                ];
            }
        }

        $pdf = Pdf::loadView('exports.barcode-labels', [
            'merchant' => $merchant,
            'shopLogoDataUri' => $merchant->logoDataUri(),
            'labels' => $labels,
            'showProductName' => $data['show_product_name'] ?? true,
            'showPrice' => $data['show_price'] ?? true,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="barcode-labels.pdf"',
        ]);
    }

    public function categories(Request $request)
    {
        return InventoryCategory::where('merchant_id', Auth::user()->merchant_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category = InventoryCategory::create([
            ...$data,
            'merchant_id' => Auth::user()->merchant_id,
        ]);

        return response()->json(['id' => $category->id, 'name' => $category->name]);
    }

    protected function authorizeItem(Request $request, InventoryItem $item): void
    {
        abort_unless($item->merchant_id === $request->user()->merchant_id, 403);
    }
}
