<?php

namespace App\Livewire\Portal\Notifications;

use App\Models\DamageReport;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal', ['title' => 'Notifications'])]
class Index extends Component
{
    use WithFileUploads;

    public bool $showDamageForm = false;

    public string $itemSearch = '';

    /** @var array<int, array{id:int,name:string,quantity_on_hand:string,unit:?string}> */
    public array $itemMatches = [];

    public ?int $inventory_item_id = null;

    public ?string $selectedItemLabel = null;

    public string $quantity = '';

    public string $description = '';

    public $photo;

    public function updatedItemSearch(string $value): void
    {
        $this->inventory_item_id = null;
        $trimmed = trim($value);

        if (mb_strlen($trimmed) < 2) {
            $this->itemMatches = [];

            return;
        }

        $this->itemMatches = InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->where('name', 'like', "%{$trimmed}%")
            ->limit(8)
            ->get(['id', 'name', 'quantity_on_hand', 'unit'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'quantity_on_hand' => $i->quantity_on_hand,
                'unit' => $i->unit,
            ])
            ->all();
    }

    public function selectItem(int $itemId, string $name): void
    {
        $this->inventory_item_id = $itemId;
        $this->selectedItemLabel = $name;
        $this->itemSearch = $name;
        $this->itemMatches = [];
    }

    public function reportDamage(): void
    {
        $data = $this->validate([
            'inventory_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $merchantId = Auth::user()->merchant_id;
        $item = InventoryItem::where('merchant_id', $merchantId)->findOrFail($data['inventory_item_id']);

        if ((float) $data['quantity'] > (float) $item->quantity_on_hand) {
            $this->addError('quantity', "Not enough stock for {$item->name} ({$item->quantity_on_hand} {$item->unit} left).");

            return;
        }

        DB::transaction(function () use ($merchantId, $data, $item) {
            $report = DamageReport::create([
                'merchant_id' => $merchantId,
                'inventory_item_id' => $item->id,
                'branch_id' => $item->branch_id,
                'quantity' => $data['quantity'],
                'description' => $data['description'] ?: null,
                'reported_by' => Auth::id(),
                'reported_at' => now()->toDateString(),
            ]);

            if ($this->photo) {
                $report->addMedia($this->photo->getRealPath())
                    ->usingFileName($this->photo->getClientOriginalName())
                    ->toMediaCollection('photo');
            }

            StockMovement::create([
                'merchant_id' => $merchantId,
                'inventory_item_id' => $item->id,
                'type' => StockMovement::TYPE_DAMAGE,
                'quantity' => $data['quantity'],
                'reference' => 'damage_report',
                'notes' => "Damage report #{$report->id}",
                'movement_date' => now()->toDateString(),
                'recorded_by' => Auth::id(),
            ]);

            $item->decrement('quantity_on_hand', $data['quantity']);
        });

        $this->reset(['itemSearch', 'itemMatches', 'inventory_item_id', 'selectedItemLabel', 'quantity', 'description', 'photo', 'showDamageForm']);
        session()->flash('status', 'Damage report recorded.');
    }

    public function render()
    {
        $merchant = Auth::user()->merchant;

        return view('livewire.portal.notifications.index', [
            'lowStockItems' => $merchant->lowStockItems()->with(['category', 'branch'])->limit(20)->get(),
            'expiringSoonItems' => $merchant->expiringSoonItems()->with(['category', 'branch'])->orderBy('expiry_date')->limit(20)->get(),
            'damagedItems' => $merchant->damageReports()->with(['inventoryItem', 'branch', 'reportedBy'])->latest('reported_at')->limit(20)->get(),
        ]);
    }
}
