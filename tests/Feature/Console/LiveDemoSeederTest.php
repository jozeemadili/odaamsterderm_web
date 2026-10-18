<?php

namespace Tests\Feature\Console;

use App\Models\Merchant;
use App\Models\User;
use App\Services\MarketplaceSearch;
use App\Support\GeoPoint;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LiveDemoSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_demo_has_the_demo_marketplace_but_no_logins(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(LiveDemoSeeder::class);

        $this->assertSame(0, User::count());
        $this->assertSame(LiveDemoSeeder::BUSINESSES, Merchant::orderBy('id')->pluck('business_name')->all());

        // Same comparison scenario as development: three sellers offer Chicken Starter near Sinza.
        $products = app(MarketplaceSearch::class)->products(['search' => 'Chicken Starter'], new GeoPoint(-6.7790, 39.2350));
        $this->assertSame(3, (int) $products->first()->sellers_count);
        $this->assertSame(43500.0, (float) $products->first()->min_price);
        $this->assertSame('Silverlands', $products->first()->producer->name);

        // Running it again changes nothing.
        $this->seed(LiveDemoSeeder::class);
        $this->assertSame(3, Merchant::count());
    }

    public function test_a_demo_seller_can_be_given_a_login(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(LiveDemoSeeder::class);

        $this->artisan('app:create-account', ['type' => 'seller', 'email' => 'tegeta@example.com', '--existing-business' => 'Tegeta Feeds & Vet', '--phone' => '+255700000009'])
            ->expectsQuestion('Password (min 10 characters, letters and numbers, hidden)', 'Secret12345')
            ->expectsQuestion('Repeat password', 'Secret12345')
            ->assertSuccessful();

        $user = User::where('email', 'tegeta@example.com')->firstOrFail();
        $this->assertSame('Tegeta Feeds & Vet', $user->merchant->business_name);
        $this->assertSame('Baraka Mushi', $user->name);
        $this->assertTrue($user->hasRole('merchant_owner'));

        // A second login for the same business is refused.
        $this->artisan('app:create-account', ['type' => 'seller', 'email' => 'other@example.com', '--existing-business' => 'Tegeta Feeds & Vet'])->assertFailed();
        $this->artisan('app:create-account', ['type' => 'seller', 'email' => 'x@example.com', '--existing-business' => 'Nope Ltd'])->assertFailed();
    }

    public function test_development_seed_still_has_demo_logins(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['admin', 'owner', 'tegeta', 'mbezi', 'customer'] as $who) {
            $this->assertTrue(User::where('email', "{$who}@odaamsterderm.test")->exists(), "{$who} login missing");
        }
        $this->assertSame('owner@odaamsterderm.test', Merchant::where('business_name', 'Kilimo Bora Agrovet')->first()->users()->value('email'));
    }
}
