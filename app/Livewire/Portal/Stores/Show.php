<?php

namespace App\Livewire\Portal\Stores;

use App\Models\Branch;
use App\Models\Region;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Store Detail'])]
class Show extends Component
{
    public Branch $store;

    public bool $editing = false;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $region_id = '';

    public $latitude = null;

    public $longitude = null;

    public string $pickup_instructions = '';

    /** @var array<string, array{closed: bool, open: string, close: string}> */
    public array $hours = [];

    public bool $is_marketplace_enabled = true;

    public function mount(Branch $store)
    {
        abort_unless($store->merchant_id === Auth::user()->merchant_id, 403);
        $this->store = $store;

        // A new store lands here straight after creation to set its location.
        if (request()->boolean('setup') || ! $store->hasLocation()) {
            $this->startEditing();
        }
    }

    public function startEditing(): void
    {
        $s = $this->store;
        $this->name = $s->name;
        $this->phone = (string) $s->phone;
        $this->email = (string) $s->email;
        $this->address = (string) $s->address;
        $this->city = (string) $s->city;
        $this->region_id = (string) ($s->region_id ?? '');
        $this->latitude = $s->latitude;
        $this->longitude = $s->longitude;
        $this->pickup_instructions = (string) $s->pickup_instructions;
        $this->is_marketplace_enabled = $s->is_marketplace_enabled;

        $this->hours = [];
        foreach (array_keys(Branch::DAYS) as $day) {
            $d = $s->opening_hours[$day] ?? null;
            $this->hours[$day] = [
                'closed' => $s->opening_hours !== null && $d === null,
                'open' => $d['open'] ?? '08:00',
                'close' => $d['close'] ?? '18:00',
            ];
        }

        $this->editing = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'pickup_instructions' => ['nullable', 'string', 'max:1000'],
            'hours.*.open' => ['required_if:hours.*.closed,false', 'nullable', 'date_format:H:i'],
            'hours.*.close' => ['required_if:hours.*.closed,false', 'nullable', 'date_format:H:i'],
        ], [], ['latitude' => 'map location', 'longitude' => 'map location']);

        $openingHours = [];
        foreach ($this->hours as $day => $h) {
            $openingHours[$day] = $h['closed'] ? null : ['open' => $h['open'], 'close' => $h['close']];
        }

        $this->store->update([
            'name' => $this->name,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'region_id' => $this->region_id ?: null,
            'latitude' => $this->latitude !== null && $this->latitude !== '' ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null && $this->longitude !== '' ? (float) $this->longitude : null,
            'pickup_instructions' => $this->pickup_instructions ?: null,
            'opening_hours' => $openingHours,
            'is_marketplace_enabled' => $this->is_marketplace_enabled,
        ]);

        $this->editing = false;
        session()->flash('status', $this->store->hasLocation()
            ? 'Store details saved.'
            : 'Store details saved. Set the map location so customers can find this store.');
    }

    public function render()
    {
        return view('livewire.portal.stores.show', [
            'store' => $this->store,
            'summary' => $this->store->stockSummary(),
            'items' => $this->store->inventoryItems()->orderBy('name')->get(),
            'regions' => Region::active()->orderBy('name')->get(),
        ]);
    }
}
