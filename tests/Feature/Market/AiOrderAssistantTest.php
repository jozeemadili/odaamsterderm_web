<?php

namespace Tests\Feature\Market;

use App\Livewire\Admin\Settings\Ai as AiSettings;
use App\Livewire\Market\AiAssistant;
use App\Models\AiRequest;
use App\Models\Cart;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiOrderAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsMarketplace;
use Tests\Fakes\FakeAiClient;
use Tests\TestCase;

class AiOrderAssistantTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
    }

    private function offer(string $store, string $product): InventoryItem
    {
        return InventoryItem::where('branch_id', $this->stores[$store]->id)->whereHas('product', fn ($q) => $q->where('name', $product))->firstOrFail();
    }

    private function fakeAi(?array $answer = []): FakeAiClient
    {
        $ai = new FakeAiClient($answer);
        $this->app->instance(AiClient::class, $ai);

        return $ai;
    }

    private function enable(): void
    {
        PlatformSetting::set(PlatformSetting::AI_ORDER_ENABLED, true);
    }

    public function test_the_button_follows_its_own_admin_switch(): void
    {
        PlatformSetting::set(PlatformSetting::AI_SEARCH_ENABLED, true);
        $this->get('/')->assertOk()->assertDontSee('Use AI to place order');

        $admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $admin->assignRole('super_admin');
        Livewire::actingAs($admin)->test(AiSettings::class)->set('orderEnabled', true);

        $this->assertTrue(AiOrderAssistant::enabled());
        $this->assertTrue(Activity::where('description', 'Changed setting ai.order_enabled')->exists());
        $this->get('/')->assertOk()->assertSee('Use AI to place order');

        Livewire::actingAs($admin)->test(AiSettings::class)->set('orderEnabled', false);
        $this->get('/')->assertDontSee('Use AI to place order');
    }

    public function test_the_assistant_asks_then_proposes_a_checked_basket(): void
    {
        $this->enable();
        $a = $this->offer('A', 'Chicken Starter');
        $b = $this->offer('B', 'Chicken Starter');
        $ai = $this->fakeAi(['reply' => 'How many chicks, and how old are they?', 'basket' => []])->then([
            'reply' => 'For 200 chicks for 2 weeks you need about 40 bags. Seller B is cheapest.',
            'basket' => [
                ['offer_id' => $b->id, 'quantity' => 40],         // only 25 online → capped
                ['offer_id' => 999999, 'quantity' => 1],          // invented → dropped
                ['offer_id' => $a->id, 'quantity' => 2],
            ],
        ]);

        $chat = Livewire::actingAs($this->customer)->test(AiAssistant::class)
            ->set('input', 'I need chick feed')
            ->call('ask')
            ->assertSee('I need chick feed')
            ->call('respond')
            ->assertSee('How many chicks')
            ->assertDontSee('Proposed basket')
            ->set('input', '200 broilers, 2 weeks')
            ->call('ask')
            ->call('respond')
            ->assertSee('Proposed basket')
            ->assertSee('Only 25 bag available here.')
            ->assertSee('Seller B Store')
            ->assertSet('qty', [$b->id => '25', $a->id => '2']);

        $this->assertSame([25.0, 2.0], array_column($chat->get('basket'), 'quantity'));

        // The whole conversation goes to the model, with the proposed basket remembered.
        $second = $ai->calls[1]['prompt'];
        $this->assertCount(3, $second);
        $this->assertSame('200 broilers, 2 weeks', $second[2]['content']);

        // Only offers the customer can order now are described (Seller C's starter is out of stock).
        $this->assertStringContainsString("{$b->id} | Chicken Starter 25KG", $ai->calls[0]['system']);
        $this->assertStringNotContainsString($this->offer('C', 'Chicken Starter')->id.' | Chicken Starter', $ai->calls[0]['system']);
        $this->assertSame(2, AiRequest::where('feature', 'order')->where('status', 'ok')->count());
    }

    public function test_adding_the_basket_fills_the_cart_without_ordering(): void
    {
        $this->enable();
        $b = $this->offer('B', 'Chicken Starter');
        $this->fakeAi(['reply' => 'Here you go.', 'basket' => [['offer_id' => $b->id, 'quantity' => 3]]]);

        Livewire::actingAs($this->customer)->test(AiAssistant::class)
            ->call('ask', 'chick feed please')
            ->call('respond')
            ->set("qty.{$b->id}", '5')
            ->call('addToCart')
            ->assertRedirect(route('market.cart'));

        $this->assertEquals(5, Cart::for($this->customer)->items()->where('inventory_item_id', $b->id)->value('quantity'));
        $this->assertSame(0, Order::count());
    }

    public function test_stock_is_rechecked_when_adding_to_cart(): void
    {
        $this->enable();
        $b = $this->offer('B', 'Chicken Starter');
        $this->fakeAi(['reply' => 'Here you go.', 'basket' => [['offer_id' => $b->id, 'quantity' => 3]]]);

        $chat = Livewire::actingAs($this->customer)->test(AiAssistant::class)->call('ask', 'chick feed')->call('respond');
        $b->update(['is_marketplace_visible' => false]);

        $chat->call('addToCart')->assertNoRedirect()->assertHasErrors('basket')->assertSee('Nothing could be added');
        $this->assertSame(0, Cart::for($this->customer)->items()->count());
    }

    public function test_guests_and_seller_accounts_cannot_chat(): void
    {
        $this->enable();
        $ai = $this->fakeAi(['reply' => 'x', 'basket' => []]);

        Livewire::test(AiAssistant::class)->assertSee('Sign in with your customer account')->call('ask', 'feed')->call('respond');

        $seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT]);
        Livewire::actingAs($seller)->test(AiAssistant::class)->assertSee('Only customer accounts')->call('ask', 'feed')->call('respond');

        $this->assertCount(0, $ai->calls);
    }

    public function test_switching_off_or_ai_unavailable_shows_a_notice(): void
    {
        $this->enable();
        $this->fakeAi(null);

        Livewire::actingAs($this->customer)->test(AiAssistant::class)
            ->call('ask', 'feed')->call('respond')
            ->assertSee('not available right now');
        $this->assertSame(1, AiRequest::where('feature', 'order')->where('status', 'failed')->count());

        PlatformSetting::set(PlatformSetting::AI_ORDER_ENABLED, false);
        Livewire::actingAs($this->customer)->test(AiAssistant::class)
            ->call('ask', 'feed')
            ->assertSet('messages', []);
    }
}
