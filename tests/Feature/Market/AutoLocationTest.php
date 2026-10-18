<?php

namespace Tests\Feature\Market;

use App\Livewire\Market\LocationBar;
use App\Models\User;
use App\Support\MarketLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class AutoLocationTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_guest_location_is_detected_for_the_visit_and_shows_distances(): void
    {
        $this->get('/')->assertSee('ohAutoLocation', false);

        Livewire::test(LocationBar::class)
            ->call('detected', $this->customerLat, $this->customerLng)
            ->assertDispatched('market-location-updated');

        $this->assertEqualsWithDelta($this->customerLat, MarketLocation::current()->latitude, 0.0001);
        $this->get('/products/chicken-starter-25kg')->assertSee('1.2 km')->assertSee('4.4 km');
        $this->get('/')->assertDontSee('ohAutoLocation', false);
    }

    public function test_detected_location_never_overwrites_a_chosen_one(): void
    {
        $customer = User::factory()->create([
            'user_type' => User::TYPE_CUSTOMER,
            'latitude' => -6.7000, 'longitude' => 39.2000, 'location_label' => 'My farm',
        ]);

        Livewire::actingAs($customer)->test(LocationBar::class)
            ->call('detected', $this->customerLat, $this->customerLng)
            ->assertNotDispatched('market-location-updated');

        $this->assertSame('My farm', $customer->fresh()->location_label);
        $this->assertEqualsWithDelta(-6.7, (float) $customer->fresh()->latitude, 0.0001);
    }
}
