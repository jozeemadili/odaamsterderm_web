<?php

namespace Tests\Feature\Market;

use App\Livewire\Admin\Settings\Ai as AiSettings;
use App\Livewire\Market\Home;
use App\Models\AiRequest;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsMarketplace;
use Tests\Fakes\FakeAiClient;
use Tests\TestCase;

class AiSearchTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private FakeAiClient $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    private function fakeAi(?array $answer = [], bool $refuse = false): FakeAiClient
    {
        $this->ai = new FakeAiClient($answer, $refuse);
        $this->app->instance(AiClient::class, $this->ai);

        return $this->ai;
    }

    private function answer(array $names, array $extra = []): array
    {
        return $extra + [
            'product_ids' => collect($names)->map(fn ($n) => Product::where('name', $n)->value('id'))->all(),
            'summary' => 'Starter feed suits chicks for the first weeks; 200 chicks need about 2 bags.',
            'in_stock_only' => false,
            'sort' => 'relevance',
            'on_topic' => true,
        ];
    }

    private function enable(bool $guests = true): void
    {
        PlatformSetting::set(PlatformSetting::AI_SEARCH_ENABLED, true);
        PlatformSetting::set(PlatformSetting::AI_SEARCH_GUESTS, $guests);
    }

    public function test_the_ask_ai_button_follows_the_admin_switch(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Ask AI');

        $this->enable();
        $this->get('/')->assertOk()->assertSee('Ask AI');
    }

    public function test_only_settings_admins_can_change_the_switch_and_changes_are_audited(): void
    {
        $admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $admin->assignRole('super_admin');
        $support = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $support->assignRole('support_agent');

        $this->actingAs($support)->get(route('admin.settings.ai'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings.ai'))->assertOk()->assertSee('Ask AI search');

        Livewire::actingAs($admin)->test(AiSettings::class)
            ->set('searchEnabled', true)
            ->set('guestsAllowed', false)
            ->set('dailyLimit', 50)
            ->call('save');

        $this->assertTrue(AiProductSearch::enabled());
        $this->assertFalse(PlatformSetting::get(PlatformSetting::AI_SEARCH_GUESTS));
        $this->assertSame(50, PlatformSetting::get(PlatformSetting::AI_DAILY_LIMIT));
        $this->assertTrue(Activity::where('description', 'Changed setting ai.search_enabled')->where('causer_id', $admin->id)->exists());

        Livewire::actingAs($admin)->test(AiSettings::class)->set('searchEnabled', false);
        $this->assertFalse(AiProductSearch::enabled());
    }

    public function test_ai_picks_are_shown_in_ai_order_with_live_prices(): void
    {
        $this->enable();
        $this->fakeAi($this->answer(['Tilapia Floating Feed', 'Chicken Starter']));

        Livewire::withQueryParams(['ask' => 'feed for my chicks and fish'])
            ->test(Home::class)
            ->assertSee('Finding the right products')
            ->call('runAi')
            ->assertSee('200 chicks need about 2 bags')
            ->assertSeeInOrder(['Tilapia Floating Feed', 'Chicken Starter 25KG'])
            ->assertSee('43,500')
            ->assertDontSee('Layers Mash 50KG');

        // Only products on the marketplace are described to the model, with live prices.
        $system = $this->ai->calls[0]['system'];
        $this->assertStringContainsString('Chicken Starter', $system);
        $this->assertStringContainsString('from TZS 43,500', $system);
        $this->assertSame(1, AiRequest::where('status', 'ok')->where('input_tokens', 1200)->where('results_count', 2)->count());
    }

    public function test_ids_the_model_invents_are_dropped_and_answers_are_cached(): void
    {
        $this->enable();
        $this->fakeAi($this->answer(['Chicken Starter'], ['product_ids' => [999999, Product::where('name', 'Chicken Starter')->value('id')]]));

        $search = app(AiProductSearch::class);
        $first = $search->search('chick feed', null, '127.0.0.1');
        $second = $search->search('Chick   FEED', null, '127.0.0.1');

        $this->assertSame([Product::where('name', 'Chicken Starter')->value('id')], $first['product_ids']);
        $this->assertSame($first, $second);
        $this->assertCount(1, $this->ai->calls);
    }

    public function test_guests_must_sign_in_when_the_admin_requires_it(): void
    {
        $this->enable(guests: false);
        $this->fakeAi($this->answer(['Chicken Starter']));

        Livewire::withQueryParams(['ask' => 'chick feed'])->test(Home::class)
            ->call('runAi')
            ->assertSee('to use Ask AI');
        $this->assertCount(0, $this->ai->calls);

        Livewire::actingAs(User::factory()->create(['user_type' => User::TYPE_CUSTOMER]))
            ->withQueryParams(['ask' => 'chick feed'])->test(Home::class)
            ->call('runAi')
            ->assertSee('Chicken Starter 25KG');
    }

    public function test_falls_back_to_keyword_search_when_ai_is_unavailable(): void
    {
        $this->enable();
        $this->fakeAi(null);

        Livewire::withQueryParams(['ask' => 'Do you have tilapia food?'])->test(Home::class)
            ->call('runAi')
            ->assertSee('not available right now')
            ->assertSee('Tilapia Floating Feed')
            ->assertDontSee('Chicken Starter 25KG');

        $this->assertSame(1, AiRequest::where('status', 'failed')->count());
    }

    public function test_daily_limit_and_off_topic_requests(): void
    {
        $this->enable();
        $this->fakeAi($this->answer([], ['product_ids' => [], 'summary' => 'I can only help with animal products.', 'on_topic' => false]));

        $result = app(AiProductSearch::class)->search('write me a poem', null, '127.0.0.1');
        $this->assertSame('off_topic', $result['status']);
        $this->assertSame([], $result['product_ids']);

        PlatformSetting::set(PlatformSetting::AI_DAILY_LIMIT, 1);
        $this->assertSame('limited', app(AiProductSearch::class)->search('chick feed', null, '127.0.0.1')['status']);
        $this->assertCount(1, $this->ai->calls);
    }

    public function test_ask_is_ignored_while_the_switch_is_off(): void
    {
        $this->fakeAi($this->answer(['Chicken Starter']));

        Livewire::withQueryParams(['ask' => 'Tilapia'])->test(Home::class)
            ->call('runAi')
            ->assertDontSee('Finding the right products')
            ->assertSee('Tilapia Floating Feed');
        $this->assertCount(0, $this->ai->calls);
    }
}
