<?php

namespace Tests\Feature\Market;

use App\Livewire\Market\AiAssistant;
use App\Models\AiRequest;
use App\Models\InventoryItem;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiProductSearch;
use App\Services\Ai\ClaudeAiClient;
use App\Services\Ai\DemoAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class DemoAiTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        config(['services.anthropic.api_key' => null, 'services.anthropic.demo' => true]);
        PlatformSetting::set(PlatformSetting::AI_SEARCH_ENABLED, true);
        PlatformSetting::set(PlatformSetting::AI_ORDER_ENABLED, true);
    }

    public function test_demo_mode_is_used_only_without_an_api_key(): void
    {
        $this->assertInstanceOf(DemoAiClient::class, app(AiClient::class));

        config(['services.anthropic.api_key' => 'sk-ant-test']);
        $this->assertInstanceOf(ClaudeAiClient::class, app(AiClient::class));

        config(['services.anthropic.api_key' => null, 'services.anthropic.demo' => false]);
        $this->assertInstanceOf(ClaudeAiClient::class, app(AiClient::class));
        $this->assertFalse(app(AiClient::class)->isConfigured());
    }

    public function test_demo_search_understands_kiswahili_and_english(): void
    {
        $search = app(AiProductSearch::class);

        $sw = $search->search('chakula cha vifaranga', null, '127.0.0.1');
        $this->assertSame('ok', $sw['status']);
        $this->assertSame('Chicken Starter', Product::find($sw['product_ids'][0])->name);
        $this->assertStringContainsString('(Demo AI) Bidhaa', $sw['summary']);

        $en = $search->search('cheapest fish feed', null, '127.0.0.1');
        $this->assertSame('Tilapia Floating Feed', Product::find($en['product_ids'][0])->name);
        $this->assertSame('price', $en['sort']);

        $this->assertSame(0, (int) AiRequest::sum('input_tokens'));
        $this->assertSame(2, AiRequest::where('model', 'demo')->count());
    }

    public function test_demo_assistant_asks_then_builds_a_basket(): void
    {
        $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
        $cheapest = InventoryItem::where('branch_id', $this->stores['B']->id)->firstOrFail(); // Chicken Starter at 43,500

        $chat = Livewire::actingAs($customer)->test(AiAssistant::class)
            ->call('ask', 'I need chick feed')->call('respond')
            ->assertSee('How many animals')
            ->call('ask', '200 broiler chicks, the cheapest')->call('respond')
            ->assertSee('Proposed basket')
            ->assertSee('1 bag per 50 animals');

        $this->assertSame($cheapest->id, $chat->get('basket')[0]['offer_id']);
        $this->assertEquals(4, $chat->get('basket')[0]['quantity']);
    }

    public function test_demo_assistant_uses_stated_quantities_in_kiswahili(): void
    {
        $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);

        $chat = Livewire::actingAs($customer)->test(AiAssistant::class)
            ->call('ask', 'Naomba mifuko 3 ya chakula cha samaki')->call('respond')
            ->assertSee('Napendekeza 3');

        $this->assertStringContainsString('Tilapia', $chat->get('basket')[0]['name']);
        $this->assertEquals(3, $chat->get('basket')[0]['quantity']);
    }
}
