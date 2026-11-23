<?php

namespace App\Livewire\Admin\AnimalTypes;

use App\Models\AnimalType;
use App\Models\InventoryItem;
use App\Models\LifeStage;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Everything about one animal: its photo and details, the types under it,
 * its growth stages (each with a photo), and the catalogue products for it.
 */
#[Layout('layouts.admin', ['title' => 'Animal'])]
class Show extends Component
{
    use WithFileUploads;

    public AnimalType $animal;

    public $photo;

    public bool $editing = false;

    public array $details = [];

    /** Growth stage being added or edited. */
    public ?int $stageId = null;

    public bool $stageForm = false;

    public array $stage = ['name' => '', 'name_sw' => '', 'description' => '', 'sort_order' => '0'];

    public $stagePhoto;

    /** Photo picked straight from a stage card, keyed by stage id. */
    public array $stagePhotos = [];

    public bool $typeForm = false;

    public array $type = ['name' => '', 'name_sw' => ''];

    public function mount(AnimalType $animalType): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $this->animal = $animalType;
        $this->fillDetails();
    }

    /* ---------- the animal ---------- */

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => ['image', 'max:4096']]);
        $this->animal->addMedia($this->photo->getRealPath())->usingFileName($this->photo->getClientOriginalName())->toMediaCollection('image');
        $this->reset('photo');
        $this->animal->refresh();
        session()->flash('status', 'Photo saved.');
    }

    public function removePhoto(): void
    {
        $this->animal->clearMediaCollection('image');
        $this->animal->refresh();
    }

    public function saveDetails(): void
    {
        $this->validate([
            'details.name' => ['required', 'string', 'max:255'],
            'details.name_sw' => ['nullable', 'string', 'max:255'],
            'details.description' => ['nullable', 'string', 'max:2000'],
            'details.sort_order' => ['required', 'integer', 'min:0'],
        ], [], ['details.name' => 'name', 'details.sort_order' => 'sort order']);

        $this->animal->update([
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
        $this->animal->update(['is_active' => ! $this->animal->is_active]);
    }

    /* ---------- types under a group ---------- */

    public function saveType(): void
    {
        abort_if($this->animal->parent_id, 422);
        $this->validate(['type.name' => ['required', 'string', 'max:255'], 'type.name_sw' => ['nullable', 'string', 'max:255']], [], ['type.name' => 'name']);

        $base = Str::slug($this->type['name']) ?: 'animal';
        $slug = $base;
        for ($i = 2; AnimalType::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }
        $this->animal->children()->create([
            'name' => trim($this->type['name']),
            'name_sw' => trim((string) $this->type['name_sw']) ?: null,
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => ($this->animal->children()->max('sort_order') ?? 0) + 10,
        ]);
        $this->reset(['type', 'typeForm']);
        session()->flash('status', 'Type added.');
    }

    /* ---------- growth stages ---------- */

    public function newStage(): void
    {
        $this->reset(['stageId', 'stage', 'stagePhoto']);
        $this->stage['sort_order'] = (string) (($this->stageOwner()->lifeStages()->max('sort_order') ?? 0) + 10);
        $this->resetErrorBag();
        $this->stageForm = true;
    }

    public function editStage(int $id): void
    {
        $stage = $this->stageOwner()->lifeStages()->findOrFail($id);
        $this->stageId = $stage->id;
        $this->stage = ['name' => $stage->name, 'name_sw' => (string) $stage->name_sw, 'description' => (string) $stage->description, 'sort_order' => (string) $stage->sort_order];
        $this->stagePhoto = null;
        $this->resetErrorBag();
        $this->stageForm = true;
    }

    public function saveStage(): void
    {
        $owner = $this->stageOwner();
        $this->validate([
            'stage.name' => ['required', 'string', 'max:255', Rule::unique('life_stages', 'name')->where('animal_type_id', $owner->id)->ignore($this->stageId)],
            'stage.name_sw' => ['nullable', 'string', 'max:255'],
            'stage.description' => ['nullable', 'string', 'max:2000'],
            'stage.sort_order' => ['required', 'integer', 'min:0'],
            'stagePhoto' => ['nullable', 'image', 'max:4096'],
        ], ['stage.name.unique' => "{$owner->name} already has a stage with this name."], ['stage.name' => 'name', 'stage.sort_order' => 'sort order']);

        $data = [
            'name' => trim($this->stage['name']),
            'name_sw' => trim((string) $this->stage['name_sw']) ?: null,
            'description' => trim((string) $this->stage['description']) ?: null,
            'sort_order' => (int) $this->stage['sort_order'],
        ];

        if ($this->stageId) {
            $stage = $owner->lifeStages()->findOrFail($this->stageId);
            $stage->update($data);
        } else {
            $prefix = 'LS-'.Str::upper(Str::substr(Str::after((string) $owner->code, 'AN-'), 0, 3) ?: 'ALL').'-';
            $code = $prefix.Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($data['name'])) ?: 'NEW', 0, 3));
            for ($i = 2, $base = $code; LifeStage::where('code', $code)->exists(); $i++) {
                $code = $base.$i;
            }
            $slug = Str::slug($owner->name.' '.$data['name']);
            for ($i = 2, $base = $slug; LifeStage::where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $stage = $owner->lifeStages()->create($data + ['code' => $code, 'slug' => $slug, 'is_active' => true]);
        }

        if ($this->stagePhoto) {
            $stage->addMedia($this->stagePhoto->getRealPath())->usingFileName($this->stagePhoto->getClientOriginalName())->toMediaCollection('image');
        }

        $this->reset(['stageId', 'stage', 'stagePhoto', 'stageForm']);
        session()->flash('status', 'Growth stage saved.');
    }

    public function updatedStagePhotos($file, $id): void
    {
        $stage = $this->stageOwner()->lifeStages()->findOrFail((int) $id);
        $this->validate(["stagePhotos.{$id}" => ['image', 'max:4096']], [], ["stagePhotos.{$id}" => 'photo']);
        $stage->addMedia($file->getRealPath())->usingFileName($file->getClientOriginalName())->toMediaCollection('image');
        unset($this->stagePhotos[$id]);
        session()->flash('status', "Photo saved for {$stage->name}.");
    }

    public function removeStagePhoto(int $id): void
    {
        $this->stageOwner()->lifeStages()->findOrFail($id)->clearMediaCollection('image');
    }

    public function toggleStage(int $id): void
    {
        $stage = $this->stageOwner()->lifeStages()->findOrFail($id);
        $stage->update(['is_active' => ! $stage->is_active]);
    }

    /** Stages belong to the main group; a type shows its group's stages. */
    protected function stageOwner(): AnimalType
    {
        return $this->animal->parent ?? $this->animal;
    }

    protected function fillDetails(): void
    {
        $this->details = [
            'name' => $this->animal->name,
            'name_sw' => (string) $this->animal->name_sw,
            'description' => (string) $this->animal->description,
            'sort_order' => (string) $this->animal->sort_order,
        ];
    }

    public function render()
    {
        $animal = $this->animal->load(['media', 'parent', 'children' => fn ($q) => $q->with('media')->withCount('taggedProducts')]);
        $owner = $this->stageOwner();
        $ids = $animal->selfAndChildIds();
        $productIds = DB::table('animal_type_product')->whereIn('animal_type_id', $ids)->pluck('product_id')
            ->merge(Product::whereIn('animal_type_id', $ids)->pluck('id'))->unique()->values();
        $products = Product::whereIn('products.id', $productIds);

        return view('livewire.admin.animal-types.show', [
            'animal' => $animal,
            'owner' => $owner,
            'stages' => $owner->lifeStages()->with('media')->withCount('products')->get(),
            'stats' => [
                'products' => $productIds->count(),
                'approved' => (clone $products)->where('status', Product::STATUS_APPROVED)->count(),
                'verified' => (clone $products)->whereNotNull('verified_at')->count(),
                'sellers' => InventoryItem::whereIn('product_id', $productIds)->distinct()->count('merchant_id'),
            ],
            'categories' => (clone $products)->join('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->selectRaw('product_categories.name, count(*) as total')->groupBy('product_categories.name')->orderByDesc('total')->limit(8)->pluck('total', 'name'),
            'recent' => (clone $products)->with(['media', 'producer', 'category'])->latest('id')->limit(8)->get(),
        ])->title($animal->name.' · Animals');
    }
}
