<?php

namespace Tests\Feature\Chicks;

use App\Livewire\Portal\Chicks\Index;
use App\Models\ChickContract;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\Producer;
use App\Models\User;
use App\Services\ChickService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ChickPortalTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $staffA;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 09:00'); // a Wednesday
        $this->buildMarketplace();
        $this->staffA = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
        $this->staffB = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['B']->merchant_id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_every_approved_agent_gets_the_chick_page(): void
    {
        $this->actingAs($this->staffA)->get(route('portal.chicks.index'))->assertOk()->assertSee('Order days')->assertSee('Hatchery contracts');
        $this->actingAs($this->staffB)->get(route('portal.chicks.index'))->assertOk();
    }

    public function test_an_agent_declares_a_contract_with_a_new_hatchery_and_gets_order_days(): void
    {
        $sasso = ChickType::where('code', 'sasso')->value('id');

        Livewire::actingAs($this->staffA)->test(Index::class)
            ->set('tab', 'contracts')
            ->call('newContract')
            ->assertSet('weekdays', ['1', '4'])
            ->assertSet('cap_boxes', '100')
            ->set('producer_id', 'new')
            ->set('new_hatchery', 'Kuku Bora Hatchery')
            ->set('chick_type_id', (string) $sasso)
            ->set('cap_boxes', '60')
            ->call('saveContract')
            ->assertHasNoErrors()
            ->assertSee('Kuku Bora Hatchery');

        $contract = ChickContract::sole();
        $this->assertSame($this->stores['A']->merchant_id, $contract->merchant_id);
        $this->assertSame(Producer::DEALS_CHICKS, $contract->producer->deals_in);
        $this->assertSame(8, $contract->orderDays()->count());
        $this->assertSame(60, $contract->orderDays()->first()->capBoxes());

        // Another agent sees none of it.
        Livewire::actingAs($this->staffB)->test(Index::class)->assertDontSee('Kuku Bora Hatchery');
    }

    public function test_an_agent_adds_an_extra_day_and_changes_a_days_cap(): void
    {
        $hatchery = Producer::create(['name' => 'Kuku Bora Hatchery', 'deals_in' => Producer::DEALS_CHICKS, 'is_active' => true]);
        $contract = app(ChickService::class)->saveContract($this->stores['A']->merchant, [
            'producer_id' => $hatchery->id, 'chick_type_id' => ChickType::where('code', 'broiler')->value('id'),
        ]);

        Livewire::actingAs($this->staffA)->test(Index::class)
            ->call('newDay')
            ->set('day_contract_id', (string) $contract->id)
            ->assertSet('day_cap', '100')
            ->set('day_date', '2026-11-28') // a Saturday
            ->set('day_cap', '40')
            ->call('saveDay')
            ->assertHasNoErrors();

        $extra = HatchSchedule::whereDate('hatch_date', '2026-11-28')->sole();
        $this->assertSame(40, $extra->capBoxes());

        $other = $contract->orderDays()->whereDate('hatch_date', '2026-11-26')->sole();
        // Another agent can't open it.
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->staffB)->test(Index::class)->call('editDay', $other->id);
    }

    public function test_agents_manage_contracts_and_days_from_the_app(): void
    {
        $hatchery = Producer::create(['name' => 'Kuku Bora Hatchery', 'deals_in' => Producer::DEALS_CHICKS, 'is_active' => true]);
        Sanctum::actingAs($this->staffA);

        $contract = $this->postJson('/api/v1/chicks/contracts', [
            'producer_id' => $hatchery->id, 'chick_type_id' => ChickType::where('code', 'layers')->value('id'), 'cap_boxes' => 50, 'branch_ids' => [$this->stores['A']->id],
        ])->assertCreated()->assertJsonPath('weekdays', [1, 4])->json();

        $days = $this->getJson('/api/v1/chicks')->assertOk()->assertJsonPath('box_size', 100)->json('days');
        $this->assertCount(8, $days);
        $this->assertSame(50, $days[0]['cap_boxes']);

        $this->patchJson("/api/v1/chicks/days/{$days[0]['id']}", ['is_closed' => true])->assertOk()->assertJsonPath('status', 'closed');
        $this->patchJson("/api/v1/chicks/days/{$days[1]['id']}", ['cap_boxes' => 20])->assertOk()->assertJsonPath('cap_boxes', 20);
        $this->deleteJson("/api/v1/chicks/days/{$days[2]['id']}")->assertNoContent();

        // Another agent can't touch them.
        Sanctum::actingAs($this->staffB);
        $this->patchJson("/api/v1/chicks/days/{$days[1]['id']}", ['is_closed' => true])->assertNotFound();
        $this->putJson("/api/v1/chicks/contracts/{$contract['id']}", ['producer_id' => $hatchery->id, 'chick_type_id' => 1])->assertNotFound();
    }
}
