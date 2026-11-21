<?php

namespace Tests\Feature\Chicks;

use App\Livewire\Market\Chicks;
use App\Models\ChickContract;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Producer;
use App\Models\User;
use App\Services\ChickService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ChickBookingTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Producer $hatchery;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 09:00'); // a Wednesday
        $this->buildMarketplace();

        $this->hatchery = Producer::create(['name' => 'Kuku Bora Hatchery', 'deals_in' => Producer::DEALS_CHICKS, 'is_active' => true]);
        $this->customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER, 'phone' => '+255700000001']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Seller A agrees to sell Kuku Bora's chicks of a type. */
    private function contract(string $type = 'sasso', array $extra = [], string $store = 'A'): ChickContract
    {
        return app(ChickService::class)->saveContract($this->stores[$store]->merchant, $extra + [
            'producer_id' => $this->hatchery->id,
            'chick_type_id' => ChickType::where('code', $type)->value('id'),
            'price_per_chick' => '2500',
        ]);
    }

    private function firstDay(ChickContract $contract): HatchSchedule
    {
        return $contract->orderDays()->orderBy('hatch_date')->firstOrFail();
    }

    public function test_the_four_chick_types_exist_and_boxes_hold_100(): void
    {
        $this->assertSame(['broiler', 'layers', 'sasso', 'tanbro'], ChickType::active()->pluck('code')->all());
        $this->assertSame(100, HatchSchedule::boxSize());
    }

    public function test_a_contract_creates_monday_and_thursday_order_days_with_a_cap_of_100_boxes(): void
    {
        $contract = $this->contract();
        $days = $contract->orderDays()->orderBy('hatch_date')->get();

        // Thu 26 Nov to Wed 23 Dec: four weeks of Mondays and Thursdays.
        $this->assertSame(['2026-11-26', '2026-11-30', '2026-12-03', '2026-12-07', '2026-12-10', '2026-12-14', '2026-12-17', '2026-12-21'],
            $days->map(fn ($d) => $d->hatch_date->toDateString())->all());
        $this->assertSame(100, $days->first()->capBoxes());
        $this->assertSame('Kuku Bora Hatchery Sasso Day-old Chicks', $days->first()->product->name);
        $this->assertTrue($this->hatchery->chickTypes()->where('code', 'sasso')->exists());
    }

    public function test_a_deleted_day_stays_gone_and_a_new_week_is_added_later(): void
    {
        $contract = $this->contract();
        $this->firstDay($contract)->delete();

        Carbon::setTestNow('2026-11-30 08:00');
        app(ChickService::class)->generateDays($contract->fresh());

        $dates = $contract->orderDays()->pluck('hatch_date')->map->toDateString();
        $this->assertNotContains('2026-11-26', $dates->all());
        $this->assertContains('2026-12-24', $dates->all()); // a Thursday four weeks after the 30th
    }

    public function test_changing_the_contract_moves_future_days_but_never_below_bookings(): void
    {
        $contract = $this->contract(extra: ['cap_boxes' => 5]);
        $day = $this->firstDay($contract);
        app(ChickService::class)->placeOrder($this->customer, $day->id, 4, $this->stores['A']->id);

        app(ChickService::class)->saveContract($this->stores['A']->merchant, [
            'producer_id' => $this->hatchery->id, 'chick_type_id' => $contract->chick_type_id, 'weekdays' => [2], 'cap_boxes' => 2, 'price_per_chick' => '2600',
        ], $contract);

        $this->assertSame(4, $day->fresh()->capBoxes()); // 4 boxes booked, so not cut to 2
        $this->assertSame(2600, (int) $day->fresh()->price_per_chick);
        $weekdays = $contract->orderDays()->whereKeyNot($day->id)->pluck('hatch_date')->map->isoWeekday()->unique()->values()->all();
        $this->assertSame([2], $weekdays); // only Tuesdays left besides the booked Thursday
    }

    public function test_customers_book_whole_boxes_at_the_agents_contract_store(): void
    {
        $day = $this->firstDay($this->contract(extra: ['cap_boxes' => 4]));
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/customer/chick-orders', [
            'hatch_schedule_id' => $day->id, 'boxes' => 3, 'store_id' => $this->stores['A']->id,
        ])->assertCreated()
            ->assertJsonPath('data.store.id', $this->stores['A']->id)
            ->assertJsonPath('data.items.0.quantity', 300)
            ->assertJsonPath('data.items.0.boxes', 3)
            ->assertJsonPath('data.items.0.hatch_date', '2026-11-26');

        $this->assertSame(1, $day->fresh()->boxesAvailable());
        $this->assertSame($this->stores['A']->merchant_id, Order::latest('id')->first()->merchant_id);

        $this->postJson('/api/v1/customer/chick-orders', ['hatch_schedule_id' => $day->id, 'boxes' => 2, 'store_id' => $this->stores['A']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('boxes');

        // Another agent's store is not where these chicks arrive.
        $this->postJson('/api/v1/customer/chick-orders', ['hatch_schedule_id' => $day->id, 'boxes' => 1, 'store_id' => $this->stores['B']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('store');
    }

    public function test_booking_stays_open_on_the_day_and_closes_after_it(): void
    {
        $day = $this->firstDay($this->contract());

        Carbon::setTestNow('2026-11-26 17:00'); // the arrival day itself
        $this->assertSame(HatchSchedule::OPEN, $day->fresh()->status());
        app(ChickService::class)->placeOrder($this->customer, $day->id, 1, $this->stores['A']->id);

        Carbon::setTestNow('2026-11-27 08:00');
        $this->assertSame(HatchSchedule::PAST, $day->fresh()->status());
        $this->expectException(ValidationException::class);
        app(ChickService::class)->placeOrder($this->customer, $day->id, 1, $this->stores['A']->id);
    }

    public function test_a_cancelled_booking_gives_its_boxes_back(): void
    {
        $day = $this->firstDay($this->contract(extra: ['cap_boxes' => 2]));
        $order = app(ChickService::class)->placeOrder($this->customer, $day->id, 2, $this->stores['A']->id);
        $this->assertSame(HatchSchedule::FULL, $day->fresh()->status());

        app(OrderService::class)->cancelByCustomer($order, $this->customer);

        $this->assertSame(2, $day->fresh()->boxesAvailable());
    }

    public function test_the_catalogue_lists_agents_nearest_first_with_their_hatcheries_and_days(): void
    {
        $this->contract();
        $this->contract(store: 'C'); // Seller C also sells Kuku Bora Sasso, further from the customer

        $response = $this->getJson("/api/v1/market/chicks?lat={$this->customerLat}&lng={$this->customerLng}")->assertOk()
            ->assertJsonPath('box_size', 100)
            ->assertJsonPath('show_prices', false);

        $sasso = collect($response->json('types'))->firstWhere('code', 'sasso');
        $this->assertSame(['Seller A', 'Seller C'], array_column($sasso['agents'], 'name'));
        $this->assertSame('2026-11-26', $sasso['agents'][0]['next_date']);
        $this->assertSame('Kuku Bora Hatchery', $sasso['agents'][0]['hatcheries'][0]['name']);
        $this->assertSame($this->stores['A']->id, $sasso['agents'][0]['hatcheries'][0]['pickup_stores'][0]['id']);
        $this->assertNull($sasso['agents'][0]['hatcheries'][0]['dates'][0]['price_per_box']);

        PlatformSetting::set(PlatformSetting::MARKET_SHOW_PRICES, true);
        $sasso = collect($this->getJson('/api/v1/market/chicks')->json('types'))->firstWhere('code', 'sasso');
        $this->assertSame(250000, (int) $sasso['agents'][0]['hatcheries'][0]['dates'][0]['price_per_box']);
    }

    public function test_the_agent_completes_a_chick_order_without_touching_shop_stock(): void
    {
        $day = $this->firstDay($this->contract());
        $order = app(ChickService::class)->placeOrder($this->customer, $day->id, 1, $this->stores['A']->id);
        $staff = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $order->merchant_id]);

        app(OrderService::class)->advance($order, Order::READY, $staff);
        app(OrderService::class)->advance($order->fresh(), Order::COMPLETED, $staff);

        $this->assertSame(Order::COMPLETED, $order->fresh()->status);
        $this->assertSame(99, $day->fresh()->boxesAvailable());
    }

    public function test_customers_book_chicks_on_the_website_in_four_steps(): void
    {
        $day = $this->firstDay($this->contract());

        $this->get(route('market.chicks'))->assertOk()->assertSee('Sasso')->assertSee('Tanbro');

        Livewire::actingAs($this->customer)->test(Chicks::class)
            ->call('chooseType', 'sasso')
            ->assertSee('Seller A')
            ->call('chooseAgent', $this->stores['A']->merchant_id)
            ->assertSee('Kuku Bora Hatchery')
            ->assertSee('100 boxes left')
            ->call('chooseDay', $day->id)
            ->set('boxes', 2)
            ->assertSee('200')
            ->call('book')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(98, $day->fresh()->boxesAvailable());
    }

    public function test_sample_contracts_are_added_once_and_removed_unless_booked(): void
    {
        $agent = $this->stores['A']->merchant;
        $agent->update(['business_name' => 'Amsterdam Group']);
        config(['hub.chick_seller' => 'SEL-NONE', 'hub.chick_seller_name' => 'Amsterdam Group']);
        Producer::where('code', 'BR-HARS')->exists() || Producer::create(['code' => 'BR-HARS', 'name' => 'Harsho Chicks', 'is_active' => true]);

        $this->artisan('chicks:seed-samples')->assertSuccessful();
        $this->artisan('chicks:seed-samples')->assertSuccessful();
        $contract = ChickContract::where('merchant_id', $agent->id)->sole();
        $this->assertTrue($contract->is_sample);
        $this->assertSame(8, $contract->orderDays()->where('is_sample', true)->count());

        $booked = $contract->orderDays()->first();
        app(ChickService::class)->placeOrder($this->customer, $booked->id, 1, $this->stores['A']->id);

        $this->artisan('chicks:seed-samples', ['--remove' => true])->assertSuccessful();
        $this->assertSame([$booked->id], HatchSchedule::pluck('id')->all());
        $this->assertFalse($contract->fresh()->is_active);
    }

    public function test_the_platform_agent_is_found_by_name_when_it_has_no_code(): void
    {
        $this->stores['A']->merchant->update(['code' => null, 'business_name' => 'Amsterdam Group']);
        config(['hub.chick_seller' => 'SEL-NONE', 'hub.chick_seller_name' => 'Amsterdam Group']);

        $this->assertSame($this->stores['A']->merchant_id, app(ChickService::class)->platformAgent()?->id);
    }

    public function test_hatch_dates_from_the_single_seller_setup_move_to_the_platform_agent(): void
    {
        $this->stores['A']->merchant->update(['code' => 'SEL-AMS']);
        config(['hub.chick_seller' => 'SEL-AMS']);
        $type = ChickType::where('code', 'broiler')->value('id');
        $old = HatchSchedule::create(['producer_id' => $this->hatchery->id, 'chick_type_id' => $type, 'hatch_date' => '2026-12-04', 'order_cutoff_date' => '2026-12-01', 'capacity' => 3000]);
        HatchSchedule::create(['producer_id' => $this->hatchery->id, 'chick_type_id' => $type, 'hatch_date' => '2026-12-11', 'order_cutoff_date' => '2026-12-08', 'capacity' => 3000, 'is_sample' => true]);

        $this->artisan('chicks:move-to-agents')->assertSuccessful();

        $this->assertSame([$old->id], HatchSchedule::pluck('id')->all());
        $this->assertSame($this->stores['A']->merchant_id, $old->fresh()->merchant_id);
        $this->assertTrue(ChickContract::where('merchant_id', $this->stores['A']->merchant_id)->where('producer_id', $this->hatchery->id)->exists());
        $this->assertInstanceOf(Merchant::class, $old->fresh()->merchant);
    }
}
