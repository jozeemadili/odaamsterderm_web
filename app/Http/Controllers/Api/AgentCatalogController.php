<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductVariant;
use App\Services\CatalogPicker;
use App\Services\ProductRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Agents add stock by picking from the catalogue, and request products that are missing. */
class AgentCatalogController extends Controller
{
    public function __construct(private readonly CatalogPicker $picker, private readonly ProductRequests $requests) {}

    /** Products to pick: by search, producers or animal; most-stocked first. */
    public function pick(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'producer_ids' => ['nullable', 'array'],
            'producer_ids.*' => ['integer'],
            'animal_type_id' => ['nullable', 'integer'],
        ]);

        $products = $this->picker->products($request->user()->merchant, $data['q'] ?? null, array_map('intval', $data['producer_ids'] ?? []), isset($data['animal_type_id']) ? (int) $data['animal_type_id'] : null);

        return response()->json(['data' => $products->map(fn (Product $p) => $this->productArray($p))->values()]);
    }

    /** Producers with how many catalogue products each has, for "which companies do you buy from?". */
    public function producers()
    {
        return response()->json(['data' => Producer::active()
            ->whereHas('products', fn ($q) => $q->approved())
            ->withCount(['products' => fn ($q) => $q->approved()])
            ->orderByDesc('products_count')->orderBy('name')
            ->get()
            ->map(fn (Producer $p) => ['id' => $p->id, 'name' => $p->name, 'logo_url' => $p->logoUrl(), 'products_count' => $p->products_count])]);
    }

    /** Adds picked pack sizes to a store with the agent's price and quantity. */
    public function addToStock(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_variant_id' => ['required', 'integer'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.list_online' => ['nullable', 'boolean'],
        ]);
        $merchant = $request->user()->merchant;
        $store = Branch::where('merchant_id', $merchant->id)->findOrFail($data['branch_id']);

        return response()->json($this->picker->addToStock($merchant, $store, $data['lines'], $request->user()), 201);
    }

    /** "Is it one of these?" before a product request. */
    public function similar(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'producer_id' => ['nullable', 'integer']]);

        return response()->json(['data' => $this->picker->similar($data['name'], isset($data['producer_id']) ? (int) $data['producer_id'] : null)
            ->map(fn (Product $p) => $this->productArray($p))->values()]);
    }

    public function requests(Request $request)
    {
        return response()->json(['data' => ProductRequest::where('merchant_id', $request->user()->merchant_id)
            ->with(['producer', 'product', 'media'])->latest()->limit(100)->get()
            ->map(fn (ProductRequest $r) => $this->requestArray($r))->values()]);
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'producer_id' => ['nullable', 'integer', Rule::exists('producers', 'id')],
            'producer_name' => ['nullable', 'string', 'max:255', 'required_without:producer_id'],
            'pack_size' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'front' => ['required', 'image', 'max:8192'],
            'back' => ['required', 'image', 'max:8192'],
            'branch_id' => ['nullable', 'integer'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'required_with:branch_id'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
        ], ['front.required' => 'Take a photo of the front of the pack.', 'back.required' => 'Take a photo of the back of the pack (the label with directions).']);

        $stock = isset($data['branch_id']) ? ['branch_id' => (int) $data['branch_id'], 'unit_price' => $data['unit_price'], 'quantity' => $data['quantity'] ?? 0] : null;
        $productRequest = $this->requests->create($request->user()->merchant, $request->user(), $data, $request->file('front'), $request->file('back'), $stock);

        return response()->json($this->requestArray($productRequest->fresh(['producer', 'media'])), 201);
    }

    private function productArray(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'producer' => $p->producer?->name ?? $p->brand,
            'category' => $p->category?->name,
            'image_url' => $p->imageUrl(),
            'agents_count' => (int) ($p->agents_count ?? 0),
            'variants' => $p->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id, 'name' => $v->name, 'selling_unit' => $v->selling_unit, 'stocked' => (bool) $v->getAttribute('stocked'),
            ])->values(),
        ];
    }

    private function requestArray(ProductRequest $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'producer' => $r->producerLabel(),
            'pack_size' => $r->pack_size,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'review_notes' => $r->review_notes,
            'product' => $r->product ? ['id' => $r->product->id, 'name' => $r->product->name] : null,
            'front_url' => $r->photoUrl(ProductRequest::FRONT),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
