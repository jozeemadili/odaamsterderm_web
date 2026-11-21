<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Agents add stock by picking from the central catalogue, never by typing
 * product details: by producer, from the products most agents stock, or by
 * search (names, brands and the Kiswahili keywords the catalogue keeps).
 * Products missing from the catalogue go through a product request.
 */
class CatalogPicker
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Approved catalogue products to pick from, most-stocked first, with
     * their pack sizes and whether this agent already stocks each one.
     *
     * @param  list<int>  $producerIds
     * @return Collection<int, Product>
     */
    public function products(Merchant $merchant, ?string $search = null, array $producerIds = [], ?int $animalTypeId = null, int $limit = 60): Collection
    {
        $query = Product::query()
            ->approved()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'), 'producer', 'media', 'category'])
            ->withCount(['inventoryItems as agents_count' => fn ($q) => $q->select(DB::raw('count(distinct merchant_id)'))])
            ->when($producerIds, fn ($q) => $q->whereIn('producer_id', $producerIds))
            ->when($animalTypeId, fn ($q) => $q->where(fn ($a) => $a->where('animal_type_id', $animalTypeId)->orWhereHas('animalTypes', fn ($t) => $t->whereKey($animalTypeId)->orWhere('parent_id', $animalTypeId))));

        if ($search !== null && trim($search) !== '') {
            $this->search($query, $search);
        }

        $stocked = InventoryItem::where('merchant_id', $merchant->id)->whereNotNull('product_variant_id')->pluck('product_variant_id')->flip();

        return $query->orderByDesc('agents_count')->orderBy('name')->limit($limit)->get()
            ->each(fn (Product $p) => $p->variants->each(fn (ProductVariant $v) => $v->setAttribute('stocked', $stocked->has($v->id))));
    }

    /** Every word must appear in the name, brand, producer, pack sizes or keywords. */
    protected function search(Builder $query, string $search): void
    {
        foreach (array_filter(preg_split('/\s+/', Str::lower(trim($search)))) as $word) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
            $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(products.name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(products.brand) LIKE ?', [$like])
                ->orWhereRaw('LOWER(products.search_keywords) LIKE ?', [$like])
                ->orWhereRaw('LOWER(products.active_ingredients) LIKE ?', [$like])
                ->orWhereHas('variants', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', [$like])));
        }
    }

    /**
     * Catalogue products that look like what an agent is about to request,
     * best first: words of the name (and producer) found in the product's
     * name, brand and keywords.
     *
     * @return Collection<int, Product>
     */
    public function similar(string $name, ?int $producerId = null, int $limit = 5): Collection
    {
        $words = collect(preg_split('/[^\pL\pN]+/u', Str::lower($name)))
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, ['the', 'kwa', 'and', 'for', 'bag', 'pack', 'tin', 'bottle', 'sachet', 'kg', 'ml'], true))
            ->unique()->values();
        if ($words->isEmpty()) {
            return collect();
        }

        $candidates = Product::query()->approved()
            ->with(['variants', 'producer', 'media'])
            ->where(function (Builder $q) use ($words) {
                foreach ($words as $w) {
                    $like = "%{$w}%";
                    $q->orWhereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(brand) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(search_keywords) LIKE ?', [$like]);
                }
            })
            ->limit(200)
            ->get();

        return $candidates
            ->map(function (Product $p) use ($words, $producerId) {
                $hay = Str::lower($p->name.' '.$p->brand.' '.$p->search_keywords);
                $hits = $words->filter(fn ($w) => str_contains($hay, $w))->count();
                $score = $hits / $words->count() + ($producerId && $p->producer_id === $producerId ? 0.5 : 0);

                return [$p, $score];
            })
            ->filter(fn ($pair) => $pair[1] >= 0.5)
            ->sortByDesc(fn ($pair) => $pair[1])
            ->take($limit)
            ->map(fn ($pair) => $pair[0])
            ->values();
    }

    /**
     * Adds picked catalogue pack sizes to a store's stock: linked to the
     * catalogue, with the agent's price and quantity, and listed online
     * when asked and allowed. A pack size the store already has is skipped.
     *
     * @param  list<array{product_variant_id:int, unit_price:float|int|string, quantity:float|int|string, list_online?:bool}>  $lines
     * @return array{added: int, skipped: int, listed: int}
     */
    public function addToStock(Merchant $merchant, Branch $store, array $lines, User $by): array
    {
        if ($store->merchant_id !== $merchant->id) {
            throw ValidationException::withMessages(['branch_id' => 'Choose one of your own stores.']);
        }

        $added = $skipped = $listed = 0;

        DB::transaction(function () use ($merchant, $store, $lines, $by, &$added, &$skipped, &$listed) {
            foreach ($lines as $line) {
                $variant = ProductVariant::with('product.category.parent')->find($line['product_variant_id'] ?? 0);
                if (! $variant || ! $variant->product?->isApproved()) {
                    throw ValidationException::withMessages(['lines' => 'One of the products is not in the catalogue.']);
                }
                $exists = InventoryItem::where('branch_id', $store->id)->where('product_variant_id', $variant->id)->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $product = $variant->product;
                $item = InventoryItem::create([
                    'merchant_id' => $merchant->id,
                    'branch_id' => $store->id,
                    'category_id' => $this->shelfCategory($merchant, $product)->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'name' => $product->pack_size ? $product->displayName() : $product->name.' - '.$variant->name,
                    'unit' => $variant->selling_unit ?: $product->unit,
                    'unit_price' => (float) $line['unit_price'],
                    'unit_cost' => isset($line['unit_cost']) ? (float) $line['unit_cost'] : null,
                    'reorder_level' => 0,
                ]);

                $quantity = (float) ($line['quantity'] ?? 0);
                if ($quantity > 0) {
                    $this->inventory->stockIn($item, $quantity, ['reference' => 'catalogue_pick', 'recorded_by' => $by->id]);
                }
                $added++;

                if (($line['list_online'] ?? true) && $quantity > 0 && $item->fresh()->marketplaceBlockers() === []) {
                    $this->inventory->updateMarketplace($item, true, $quantity, true, ['recorded_by' => $by->id]);
                    $listed++;
                }
            }
        });

        return ['added' => $added, 'skipped' => $skipped, 'listed' => $listed];
    }

    /** The agent's own shelf category for a catalogue product: named after its top catalogue category. */
    protected function shelfCategory(Merchant $merchant, Product $product): InventoryCategory
    {
        $name = $product->category?->parent?->name ?? $product->category?->name ?? 'General';

        return InventoryCategory::firstOrCreate(['merchant_id' => $merchant->id, 'name' => $name]);
    }
}
