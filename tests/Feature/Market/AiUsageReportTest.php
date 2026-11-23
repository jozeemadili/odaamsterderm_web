<?php

namespace Tests\Feature\Market;

use App\Livewire\Admin\Settings\Ai;
use App\Models\AiRequest;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Ai\AiPricing;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiUsageReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $this->admin->assignRole('super_admin');
    }

    private function request(array $attrs): AiRequest
    {
        return AiRequest::create($attrs + [
            'feature' => AiRequest::FEATURE_SEARCH, 'query' => 'chakula cha kuku', 'model' => 'claude-opus-5-5',
            'status' => AiRequest::STATUS_OK, 'input_tokens' => 1000, 'output_tokens' => 200,
        ]);
    }

    public function test_cached_prompt_tokens_cost_a_fraction_of_fresh_input(): void
    {
        // 1M fresh input = $4; 1M cached read = $0.20; 1M output = $20.
        $this->assertEqualsWithDelta(4.0, AiPricing::usd('claude-opus-5-5', 1_000_000, 0), 0.0001);
        $this->assertEqualsWithDelta(0.20, AiPricing::usd('claude-opus-5-5', 0, 0, 1_000_000), 0.0001);
        $this->assertEqualsWithDelta(20.0, AiPricing::usd('claude-opus-5-5', 0, 1_000_000), 0.0001);
        $this->assertSame(0.0, AiPricing::usd('unknown-model', 1000, 1000));
    }

    public function test_costs_show_in_shillings_at_the_saved_rate(): void
    {
        // 10,000 input + 2,000 output + 40,000 cache reads = 0.04 + 0.04 + 0.008 = $0.088
        $this->request(['input_tokens' => 10_000, 'output_tokens' => 2_000, 'cache_read_tokens' => 40_000]);
        PlatformSetting::set(PlatformSetting::AI_USD_TZS, 2500, $this->admin);

        Livewire::actingAs($this->admin)->test(Ai::class)
            ->assertSee('TSh 220') // 0.088 × 2,500
            ->assertSee('Daily cost, last 30 days')
            ->set('usdRate', '3000')->call('saveRate')->assertHasNoErrors()
            ->assertSee('TSh 264');
        $this->assertEquals(3000, PlatformSetting::get(PlatformSetting::AI_USD_TZS));
    }

    public function test_requests_can_be_searched_by_phone_name_and_date(): void
    {
        $amina = User::factory()->create(['name' => 'Amina Juma', 'phone' => '+255712345678']);
        $baraka = User::factory()->create(['name' => 'Baraka Mollel', 'phone' => '+255765000111']);
        $this->request(['user_id' => $amina->id, 'query' => 'dawa ya minyoo kwa mbuzi']);
        $old = $this->request(['user_id' => $baraka->id, 'query' => 'chanjo ya kuku']);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        Livewire::actingAs($this->admin)->test(Ai::class)
            ->assertSee('+255712345678')
            ->set('search', '0712345678'.'')->assertDontSee('chanjo ya kuku')
            ->set('search', '712345678')->assertSee('dawa ya minyoo')->assertDontSee('chanjo ya kuku')
            ->set('search', 'Baraka')->assertSee('chanjo ya kuku')->assertDontSee('dawa ya minyoo')
            ->call('clearFilters')
            ->set('from', now()->subDays(2)->toDateString())->assertSee('dawa ya minyoo')->assertDontSee('chanjo ya kuku')
            ->set('from', '')->set('to', now()->subDays(5)->toDateString())->assertSee('chanjo ya kuku')->assertDontSee('dawa ya minyoo');
    }
}
