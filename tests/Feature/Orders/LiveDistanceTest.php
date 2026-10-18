<?php

namespace Tests\Feature\Orders;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class LiveDistanceTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_distance_card_appears_once_the_seller_accepts(): void
    {
        $this->buildMarketplace();
        $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
        $customer->assignRole('customer');
        $offer = InventoryItem::where('branch_id', $this->stores['B']->id)->firstOrFail();
        $seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $offer->merchant_id]);
        $seller->assignRole('merchant_owner');

        $orders = app(OrderService::class);
        $orders->addToCart($customer, $offer->id, 2);
        $order = $orders->checkout($customer, null, '+255700000123')->first();

        $this->actingAs($customer)->get(route('market.order', $order))
            ->assertOk()->assertDontSee('Distance to the store');

        $orders->advance($order, Order::ACCEPTED, $seller);

        $this->actingAs($customer)->get(route('market.order', $order))
            ->assertOk()
            ->assertSee('Distance to the store')
            ->assertSee('liveDistance({ lat: -6.7445, lng: 39.215 })', false);
    }
}
