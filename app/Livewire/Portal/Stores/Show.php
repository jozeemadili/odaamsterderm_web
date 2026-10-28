<?php

namespace App\Livewire\Portal\Stores;

use App\Models\Branch;
use App\Models\Region;
use App\Support\StoreAccess;
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

    public string $whatsapp = '';

    public string $manager_name = '';

    public string $area = '';

    public string $landmark = '';

    public $delivery_radius_km = null;

    public $delivery_base_fee = null;

    public $delivery_fee_per_km = null;

    public bool $pickup_available = true;

    public bool $cold_chain_capable = false;

    public bool $vet_advice_on_site = false;

    public string $bio = '';

    public string $bio_sw = '';

    /** @var array<string, array{closed: bool, open: string, close: string}> */
    public array $hours = [];

    public bool $is_marketplace_enabled = true;

    public function mount(Branch $store)
    {
        abort_unless($store->merchant_id === Auth::user()->merchant_id && StoreAccess::allows($store->id), 403);
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
        $this->whatsapp = (string) $s->whatsapp;
        $this->manager_name = (string) $s->manager_name;
        $this->area = (string) $s->area;
        $this->landmark = (string) $s->landmark;
        $this->delivery_radius_km = $s->delivery_radius_km;
        $this->delivery_base_fee = $s->delivery_base_fee;
        $this->delivery_fee_per_km = $s->delivery_fee_per_km;
        $this->pickup_available = $s->pickup_available;
        $this->cold_chain_capable = $s->cold_chain_capable;
        $this->vet_advice_on_site = $s->vet_advice_on_site;
        $this->bio = (string) $s->bio;
        $this->bio_sw = (string) $s->bio_sw;

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
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'delivery_radius_km' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'delivery_base_fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee_per_km' => ['nullable', 'numeric', 'min:0'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'bio_sw' => ['nullable', 'string', 'max:2000'],
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
            'whatsapp' => $this->whatsapp ?: null,
            'manager_name' => $this->manager_name ?: null,
            'area' => $this->area ?: null,
            'landmark' => $this->landmark ?: null,
            'delivery_radius_km' => $this->numberOrNull($this->delivery_radius_km),
            'delivery_base_fee' => $this->numberOrNull($this->delivery_base_fee),
            'delivery_fee_per_km' => $this->numberOrNull($this->delivery_fee_per_km),
            'pickup_available' => $this->pickup_available,
            'cold_chain_capable' => $this->cold_chain_capable,
            'vet_advice_on_site' => $this->vet_advice_on_site,
            'bio' => $this->bio ?: null,
            'bio_sw' => $this->bio_sw ?: null,
        ]);

        $this->editing = false;
        session()->flash('status', $this->store->hasLocation()
            ? 'Store details saved.'
            : 'Store details saved. Set the map location so customers can find this store.');
    }

    private function numberOrNull(mixed $value): ?float
    {
        return $value !== null && $value !== '' ? (float) $value : null;
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
