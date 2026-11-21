<?php

namespace App\Services;

use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\LifeStage;
use App\Models\Merchant;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRequest;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\ProductRequestReviewed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Products missing from the catalogue: agents send a name, the producer,
 * the pack size and photos of the label; the data team approves a new
 * catalogue product built from the label, links an existing one, or
 * declines. The agent's stock (sold in-shop meanwhile) links on approval.
 */
class ProductRequests
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array{name:string, producer_id?:?int, producer_name?:?string, pack_size?:?string, notes?:?string}  $data
     * @param  array{branch_id:int, unit_price:float|int|string, quantity:float|int|string}|null  $stock  also add it to a store now (sold in-shop until linked)
     */
    public function create(Merchant $merchant, User $by, array $data, UploadedFile $front, UploadedFile $back, ?array $stock = null): ProductRequest
    {
        return DB::transaction(function () use ($merchant, $by, $data, $front, $back, $stock) {
            $item = null;
            if ($stock) {
                $store = Branch::where('merchant_id', $merchant->id)->find($stock['branch_id'])
                    ?? throw ValidationException::withMessages(['branch_id' => 'Choose one of your own stores.']);
                $item = InventoryItem::create([
                    'merchant_id' => $merchant->id,
                    'branch_id' => $store->id,
                    'category_id' => InventoryCategory::firstOrCreate(['merchant_id' => $merchant->id, 'name' => 'Awaiting catalogue'])->id,
                    'name' => trim($data['name'].' '.($data['pack_size'] ?? '')),
                    'unit_price' => (float) $stock['unit_price'],
                    'reorder_level' => 0,
                ]);
                if ((float) $stock['quantity'] > 0) {
                    $this->inventory->stockIn($item, (float) $stock['quantity'], ['reference' => 'product_request', 'recorded_by' => $by->id]);
                }
            }

            $request = ProductRequest::create([
                'merchant_id' => $merchant->id,
                'requested_by' => $by->id,
                'name' => trim($data['name']),
                'producer_id' => $data['producer_id'] ?? null,
                'producer_name' => empty($data['producer_id']) ? (trim((string) ($data['producer_name'] ?? '')) ?: null) : null,
                'pack_size' => ($data['pack_size'] ?? '') ?: null,
                'notes' => ($data['notes'] ?? '') ?: null,
                'inventory_item_id' => $item?->id,
                'status' => ProductRequest::PENDING,
            ]);
            $request->addMedia($front->getRealPath())->usingFileName('front.'.($front->extension() ?: 'jpg'))->toMediaCollection(ProductRequest::FRONT);
            $request->addMedia($back->getRealPath())->usingFileName('back.'.($back->extension() ?: 'jpg'))->toMediaCollection(ProductRequest::BACK);

            return $request;
        });
    }

    /**
     * Approves the request as a new catalogue product built from the label
     * (the reviewer's checked version of the draft) and links the agent's
     * stock to its first pack size, or the one chosen.
     *
     * @param  array<string, mixed>  $data  name, producer_id|producer_name, category_code, animal_codes, life_stage_codes, form, pack_sizes[], attributes…
     */
    public function approveNew(ProductRequest $request, array $data, User $reviewer, int $packIndex = 0): Product
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $data, $reviewer, $packIndex) {
            $producer = ! empty($data['producer_id'])
                ? Producer::findOrFail($data['producer_id'])
                : $this->producerNamed((string) ($data['producer_name'] ?? $request->producerLabel() ?? ''));
            $category = ProductCategory::where('code', $data['category_code'] ?? '')->first()
                ?? throw ValidationException::withMessages(['category_code' => 'Choose the catalogue category.']);
            $animals = AnimalType::whereIn('code', $data['animal_codes'] ?? [])->pluck('id');
            if ($animals->isEmpty()) {
                throw ValidationException::withMessages(['animal_codes' => 'Choose at least one animal.']);
            }
            $packs = array_values(array_filter($data['pack_sizes'] ?? [], fn ($p) => trim((string) ($p['name'] ?? '')) !== ''));
            if ($packs === []) {
                throw ValidationException::withMessages(['pack_sizes' => 'Add at least one pack size.']);
            }

            $name = trim((string) $data['name']);
            $product = Product::create([
                'name' => $name,
                'slug' => Product::uniqueSlug($name.' '.($producer?->name ?? '')),
                'producer_id' => $producer?->id,
                'brand' => $producer?->name,
                'product_category_id' => $category->id,
                'animal_type_id' => $animals->first(),
                'unit' => $packs[0]['selling_unit'] ?? 'pack',
                'form' => ($data['form'] ?? '') ?: null,
                'description' => ($data['description'] ?? '') ?: null,
                'active_ingredients' => ($data['active_ingredients'] ?? '') ?: null,
                'inclusion_or_dosage' => ($data['dosage'] ?? '') ?: null,
                'administration_route' => $data['administration_route'] ?? null ?: null,
                'use_cases' => $data['use_cases'] ?? null ?: null,
                'disease_target' => $data['disease_target'] ?? null ?: null,
                'withdrawal_meat_days' => $this->int($data['withdrawal_meat_days'] ?? null),
                'withdrawal_eggs_days' => $this->int($data['withdrawal_eggs_days'] ?? null),
                'withdrawal_milk_days' => $this->int($data['withdrawal_milk_days'] ?? null),
                'registration_no' => ($data['registration_no'] ?? '') ?: null,
                'storage' => ($data['storage'] ?? '') ?: null,
                'prescription_required' => (bool) ($data['prescription_required'] ?? false),
                'cold_chain_required' => (bool) ($data['cold_chain_required'] ?? false),
                'crude_protein_pct' => is_numeric($data['crude_protein_pct'] ?? null) ? (float) $data['crude_protein_pct'] : null,
                'capacity' => ($data['capacity'] ?? '') ?: null,
                'power_source' => ($data['power_source'] ?? '') ?: null,
                'breed_strain' => ($data['breed_strain'] ?? '') ?: null,
                'search_keywords' => Str::lower(trim(($data['keywords'] ?? '').'|'.$request->name, '|')),
                'status' => Product::STATUS_APPROVED,
                'proposed_by_merchant_id' => $request->merchant_id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $product->animalTypes()->sync($animals);
            $product->lifeStages()->sync(LifeStage::whereIn('code', $data['life_stage_codes'] ?? [])->pluck('id'));

            $variants = [];
            foreach ($packs as $i => $pack) {
                $variants[] = $product->variants()->create([
                    'sku' => 'R'.$request->id.'-'.($i + 1),
                    'name' => trim((string) $pack['name']),
                    'pack_size_value' => is_numeric($pack['value'] ?? null) ? (float) $pack['value'] : null,
                    'pack_size_unit' => ($pack['unit'] ?? '') ?: null,
                    'selling_unit' => ($pack['selling_unit'] ?? '') ?: 'pack',
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
            $variant = $variants[$packIndex] ?? $variants[0];

            // The label photos become the product's photos.
            if ($front = $request->getFirstMedia(ProductRequest::FRONT)) {
                $front->copy($product, 'image');
            }
            if ($back = $request->getFirstMedia(ProductRequest::BACK)) {
                $back->copy($product, Product::BACK_PHOTO);
            }

            // Nothing a buyer relies on may be missing; built from the label, it counts as verified.
            $missing = $product->refreshQuality()['missing'];
            if ($missing) {
                throw ValidationException::withMessages(['product' => 'Still needed for this category: '.implode(', ', $missing).'.']);
            }
            $product->markVerified($reviewer, 'label');

            $this->finish($request, ProductRequest::APPROVED, $product, $variant, $reviewer, $data['review_notes'] ?? null);

            return $product;
        });
    }

    /** It was already in the catalogue: link the request (and the agent's stock) to that product. */
    public function linkExisting(ProductRequest $request, ProductVariant $variant, User $reviewer, ?string $note = null): void
    {
        $this->assertPending($request);
        if (! $variant->product?->isApproved()) {
            throw ValidationException::withMessages(['product_id' => 'Choose an approved catalogue product.']);
        }

        DB::transaction(fn () => $this->finish($request, ProductRequest::LINKED, $variant->product, $variant, $reviewer, $note));
    }

    public function reject(ProductRequest $request, User $reviewer, string $reason): void
    {
        $this->assertPending($request);
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['review_notes' => 'Tell the agent why it was not added.']);
        }

        $request->update(['status' => ProductRequest::REJECTED, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_notes' => $reason]);
        $this->notify($request);
    }

    protected function finish(ProductRequest $request, string $status, Product $product, ProductVariant $variant, User $reviewer, ?string $note): void
    {
        $request->update([
            'status' => $status, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_notes' => $note ?: null,
        ]);

        // The agent's stock now points at the catalogue product.
        if ($item = $request->inventoryItem) {
            $item->update([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'name' => $product->pack_size ? $product->displayName() : $product->name.' - '.$variant->name,
                'unit' => $variant->selling_unit ?: $product->unit,
            ]);
        }

        $this->notify($request->fresh());
    }

    protected function notify(ProductRequest $request): void
    {
        Notification::send(
            User::where('merchant_id', $request->merchant_id)->where('user_type', User::TYPE_MERCHANT)->get(),
            new ProductRequestReviewed($request),
        );
    }

    protected function producerNamed(string $name): ?Producer
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = Producer::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        if ($existing) {
            return $existing;
        }

        $base = Str::slug($name) ?: 'producer';
        $slug = $base;
        for ($i = 2; Producer::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return Producer::create(['name' => $name, 'slug' => $slug, 'country' => 'Tanzania', 'is_active' => true, 'on_marketplace' => true]);
    }

    protected function assertPending(ProductRequest $request): void
    {
        if ($request->status !== ProductRequest::PENDING) {
            throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
        }
    }

    private function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
