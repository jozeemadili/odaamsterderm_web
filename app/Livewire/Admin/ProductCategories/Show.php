<?php

namespace App\Livewire\Admin\ProductCategories;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ProductRequirements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Everything about one category: its photo and details, subcategories,
 * the details its products must carry, and the products in it.
 */
#[Layout('layouts.admin', ['title' => 'Category'])]
class Show extends Component
{
    use WithFileUploads;

    public ProductCategory $category;

    public $photo;

    public bool $editing = false;

    public array $details = [];

    public bool $subForm = false;

    public array $sub = ['name' => '', 'name_sw' => '', 'description' => ''];

    /** Photo picked straight from a subcategory card, keyed by its id. */
    public array $subPhotos = [];

    public function mount(ProductCategory $productCategory): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $this->category = $productCategory;
        $this->details = [
            'name' => $productCategory->name,
            'name_sw' => (string) $productCategory->name_sw,
            'description' => (string) $productCategory->description,
            'sort_order' => (string) $productCategory->sort_order,
        ];
    }

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => ['image', 'max:4096']]);
        $this->category->addMedia($this->photo->getRealPath())->usingFileName($this->photo->getClientOriginalName())->toMediaCollection('image');
        $this->reset('photo');
        $this->category->refresh();
        session()->flash('status', 'Photo saved.');
    }

    public function removePhoto(): void
    {
        $this->category->clearMediaCollection('image');
        $this->category->refresh();
    }

    public function saveDetails(): void
    {
        $this->validate([
            'details.name' => ['required', 'string', 'max:255'],
            'details.name_sw' => ['nullable', 'string', 'max:255'],
            'details.description' => ['nullable', 'string', 'max:2000'],
            'details.sort_order' => ['required', 'integer', 'min:0'],
        ], [], ['details.name' => 'name', 'details.sort_order' => 'sort order']);

        $this->category->update([
            'name' => trim($this->details['name']),
            'name_sw' => trim((string) $this->details['name_sw']) ?: null,
            'description' => trim((string) $this->details['description']) ?: null,
            'sort_order' => (int) $this->details['sort_order'],
        ]);
        $this->editing = false;
        session()->flash('status', 'Details saved.');
    }

    public function toggleActive(): void
    {
        $this->category->update(['is_active' => ! $this->category->is_active]);
    }

    public function saveSub(): void
    {
        abort_if($this->category->parent_id, 422);
        $this->validate(['sub.name' => ['required', 'string', 'max:255'], 'sub.name_sw' => ['nullable', 'string', 'max:255'], 'sub.description' => ['nullable', 'string', 'max:2000']], [], ['sub.name' => 'name']);

        $parent = $this->category;
        $n = ProductCategory::where('parent_id', $parent->id)->pluck('code')->map(fn ($c) => (int) Str::after((string) $c, $parent->code))->max() ?? 0;
        do {
            $n += 10;
            $code = $parent->code.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        } while (ProductCategory::where('code', $code)->exists());
        $slug = Str::slug($parent->name.' '.$this->sub['name']) ?: 'category';
        for ($i = 2, $base = $slug; ProductCategory::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $parent->children()->create([
            'code' => $code,
            'slug' => $slug,
            'name' => trim($this->sub['name']),
            'name_sw' => trim((string) $this->sub['name_sw']) ?: null,
            'description' => trim((string) $this->sub['description']) ?: null,
            'is_active' => true,
            'sort_order' => ($parent->children()->max('sort_order') ?? 0) + 10,
        ]);
        $this->reset(['sub', 'subForm']);
        session()->flash('status', 'Subcategory added.');
    }

    public function updatedSubPhotos($file, $id): void
    {
        $child = $this->category->children()->findOrFail((int) $id);
        $this->validate(["subPhotos.{$id}" => ['image', 'max:4096']], [], ["subPhotos.{$id}" => 'photo']);
        $child->addMedia($file->getRealPath())->usingFileName($file->getClientOriginalName())->toMediaCollection('image');
        unset($this->subPhotos[$id]);
        session()->flash('status', "Photo saved for {$child->name}.");
    }

    public function render()
    {
        $category = $this->category->load(['media', 'parent', 'children' => fn ($q) => $q->with('media')->withCount(['products', 'products as missing_count' => fn ($p) => $p->where('missing_required', '>', 0)])]);
        $ids = $category->selfAndChildIds();
        $products = Product::whereIn('products.product_category_id', $ids);

        return view('livewire.admin.product-categories.show', [
            'category' => $category,
            'stats' => [
                'products' => (clone $products)->count(),
                'approved' => (clone $products)->where('status', Product::STATUS_APPROVED)->count(),
                'verified' => (clone $products)->whereNotNull('verified_at')->count(),
                'missing' => (clone $products)->where('missing_required', '>', 0)->count(),
                'sellers' => InventoryItem::whereIn('product_id', (clone $products)->select('products.id'))->distinct()->count('merchant_id'),
            ],
            // A main category lists what each subcategory adds; a subcategory shows its full list.
            'requirements' => $category->parent_id
                ? ProductRequirements::forCategory($category)
                : null,
            'subRequirements' => $category->parent_id ? collect() : $category->children->mapWithKeys(fn ($c) => [$c->id => ProductRequirements::forCategory($c)]),
            'animals' => DB::table('animal_type_product')
                ->join('products', 'products.id', '=', 'animal_type_product.product_id')
                ->join('animal_types', 'animal_types.id', '=', 'animal_type_product.animal_type_id')
                ->whereIn('products.product_category_id', $ids)
                ->selectRaw('animal_types.name, count(distinct products.id) as total')->groupBy('animal_types.name')->orderByDesc('total')->limit(8)->pluck('total', 'name'),
            'producers' => (clone $products)->join('producers', 'producers.id', '=', 'products.producer_id')
                ->selectRaw('producers.name, count(*) as total')->groupBy('producers.name')->orderByDesc('total')->limit(8)->pluck('total', 'name'),
            'recent' => (clone $products)->with(['media', 'producer', 'category'])->latest('products.id')->limit(8)->get(),
        ])->title($category->name.' · Categories');
    }
}
