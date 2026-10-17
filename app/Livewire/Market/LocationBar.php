<?php

namespace App\Livewire\Market;

use App\Support\GeoPoint;
use App\Support\MarketLocation;
use Livewire\Component;

/**
 * "Near: …" chip in the marketplace header. Customers share their device
 * location or pick a point on the map; pages listening for
 * "market-location-updated" re-rank by distance.
 */
class LocationBar extends Component
{
    public bool $open = false;

    public $latitude = null;

    public $longitude = null;

    public string $label = '';

    public function openPicker(): void
    {
        $current = MarketLocation::current();
        $this->latitude = $current?->latitude;
        $this->longitude = $current?->longitude;
        $this->label = $current?->label ?? '';
        $this->open = true;
    }

    /** Called from the browser's geolocation API. */
    public function useDeviceLocation(float $latitude, float $longitude): void
    {
        $this->save($latitude, $longitude, 'Current location');
    }

    public function savePicked(): void
    {
        $this->save($this->latitude, $this->longitude, trim($this->label) ?: 'Selected location');
    }

    protected function save(mixed $latitude, mixed $longitude, string $label): void
    {
        $point = GeoPoint::tryFrom($latitude, $longitude, $label);

        if (! $point) {
            $this->addError('latitude', 'Pick a point on the map first.');

            return;
        }

        MarketLocation::set($point);
        $this->open = false;
        $this->dispatch('market-location-updated');
    }

    public function clearLocation(): void
    {
        MarketLocation::clear();
        $this->open = false;
        $this->dispatch('market-location-updated');
    }

    public function render()
    {
        return view('livewire.market.location-bar', ['current' => MarketLocation::current()]);
    }
}
