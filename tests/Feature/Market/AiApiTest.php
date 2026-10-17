<?php

namespace Tests\Feature\Market;

use App\Models\InventoryItem;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\Fakes\FakeAiClient;
use Tests\TestCase;

class AiApiTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    private function fakeAi(?array $answer = []): FakeAiClient
    {
        $ai = new FakeAiClient($answer);
        $this->app->instance(AiClient::class, $ai);

        return $ai;
    }

    private function id(string $product): int
    {
        return Product::where('name', $product)->value('id');
    }

    public function test_config_reports_the_admin_switches(): void
    {
        $this->getJson('/api/v1/market/ai/config')
            ->assertOk()->assertExactJson(['search_enabled' => false, 'search_guests' => true, 'order_enabled' => false]);

        PlatformSetting::set(PlatformSetting::AI_SEARCH_ENABLED, true);
        PlatformSetting::set(PlatformSetting::AI_ORDER_ENABLED, true);
        $this->getJson('/api/v1/market/ai/config')->assertJson(['search_enabled' => true, 'order_enabled' => true]);
    }

    public function test_search_returns_ai_picks_with_live_offer_data(): void
    {
        $this->postJson('/api/v1/market/ai/search', ['query' => 'feed'])->assertForbidden();

        PlatformSetting::set(PlatformSetting::AI_SEARCH_ENABLED, true);
        $this->fakeAi([
            'product_ids' => [$this->id('Tilapia Floating Feed'), $this->id('Chicken Starter')],
            'summary' => 'Two feeds that fit.',
            'in_stock_only' => false,
            'sort' => 'relevance',
            'on_topic' => true,
        ]);

        $this->postJson('/api/v1/market/ai/search', ['query' => 'feed for fish and chicks', 'lat' => $this->customerLat, 'lng' => $this->customerLng])
            ->assertOk()
            ->assertJsonPath('ai.status', 'ok')
            ->assertJsonPath('ai.summary', 'Two feeds that fit.')
            ->assertJsonPath('ai.fallback', false)
            ->assertJsonPath('data.0.name', 'Tilapia Floating Feed')
            ->assertJsonPath('data.1.name', 'Chicken Starter')
            ->assertJsonCount(2, 'data');
    }

    public function test_search_falls_back_to_keywords_and_respects_guest_setting(): void
    {
        PlatformSetting::set(PlatformSetting::AI_SEARCH_ENABLED, true);
        $this->fakeAi(null);

        $this->postJson('/api/v1/market/ai/search', ['query' => 'any tilapia food?'])
            ->assertOk()
            ->assertJsonPath('ai.status', 'unavailable')
            ->assertJsonPath('ai.fallback', true)
            ->assertJsonPath('data.0.name', 'Tilapia Floating Feed');

        PlatformSetting::set(PlatformSetting::AI_SEARCH_GUESTS, false);
        $this->postJson('/api/v1/market/ai/search', ['query' => 'tilapia'])->assertUnauthorized()->assertJsonPath('status', 'sign_in');

        Sanctum::actingAs(User::factory()->create(['user_type' => User::TYPE_CUSTOMER]));
        $this->postJson('/api/v1/market/ai/search', ['query' => 'tilapia'])->assertOk();
    }

    public function test_order_assistant_turn_returns_a_checked_basket_and_memo(): void
    {
        $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
        $offer = InventoryItem::where('branch_id', $this->stores['B']->id)->firstOrFail();
        $ai = $this->fakeAi(['reply' => 'Here is your basket.', 'basket' => [['offer_id' => $offer->id, 'quantity' => 99], ['offer_id' => 424242, 'quantity' => 1]]]);
        $messages = [
            ['role' => 'user', 'content' => 'chick feed'],
            ['role' => 'assistant', 'content' => 'How many chicks?'],
            ['role' => 'user', 'content' => '200'],
        ];

        $this->postJson('/api/v1/customer/ai/order', ['messages' => $messages])->assertUnauthorized();

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/customer/ai/order', ['messages' => $messages])->assertForbidden();

        PlatformSetting::set(PlatformSetting::AI_ORDER_ENABLED, true);
        $this->postJson('/api/v1/customer/ai/order', ['messages' => [['role' => 'assistant', 'content' => 'hi']]])->assertStatus(422);

        $this->postJson('/api/v1/customer/ai/order', ['messages' => $messages, 'lat' => $this->customerLat, 'lng' => $this->customerLng])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('reply', 'Here is your basket.')
            ->assertJsonCount(1, 'basket')
            ->assertJsonPath('basket.0.offer_id', $offer->id)
            ->assertJsonPath('basket.0.quantity', 25)
            ->assertJsonPath('basket.0.store', 'Seller B Store');

        $this->assertCount(3, $ai->calls[0]['prompt']);
        $this->assertStringContainsString("offer {$offer->id}", $this->postJson('/api/v1/customer/ai/order', ['messages' => $messages])->json('memo'));
    }

    public function test_seller_accounts_cannot_use_the_order_assistant(): void
    {
        PlatformSetting::set(PlatformSetting::AI_ORDER_ENABLED, true);
        Sanctum::actingAs(User::factory()->create(['user_type' => User::TYPE_MERCHANT]));

        $this->postJson('/api/v1/customer/ai/order', ['messages' => [['role' => 'user', 'content' => 'feed']]])->assertForbidden();
    }
}
