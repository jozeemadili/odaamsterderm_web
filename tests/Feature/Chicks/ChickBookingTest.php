<?php

namespace Tests\Feature\Chicks;

use App\Livewire\Market\Chicks;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Producer;
use App\Models\User;
use App\Services\ChickService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->buildMarketplace();

        // Seller A sells every hatchery's chicks.
        $this->stores['A']->merchant->update(['code' => 'SEL-CHICKS']);
        config(['hub.chick_seller' => 'SEL-CHICKS']);

        $this->hatchery = Producer::create(['name' => 'Kuku Bora Hatchery', 'deals_in' => Producer::DEALS_CHICKS, 'is_active' => true]);
        $this->customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER, 'phone' => '+255700000001']);
    }

    private function schedule(int $capacity = 230, string $type = 'sasso', int $days = 10): HatchSchedule
    {
        return app(ChickService::class)->saveSchedule([
            'producer_id' => $this->hatchery->id,
            'chick_type_id' => ChickType::where('code', $type)->value('id'),
            'hatch_date' => today()->addDays($days)->toDateString(),
            'capacity' => $capacity,
            'price_per_chick' => 2500,
        ]);
    }

    public function test_the_four_chick_types_exist_with_descriptions(): void
    {
        $this->assertSame(['broiler', 'layers', 'sasso', 'tanbro'], ChickType::active()->pluck('code')->all());
        $this->assertNotEmpty(ChickType::where('code', 'tanbro')->value('description_sw'));
    }

    public function test_a_hatch_date_gets_a_cut_off_three_days_before_and_a_product(): void
    {
        $schedule = $this->schedule();

        $this->assertSame(today()->addDays(7)->toDateString(), $schedule->order_cutoff_date->toDateString());
        $this->assertSame('Kuku Bora Hatchery Sasso Day-old Chicks', $schedule->product->name);
        $this->assertTrue($this->hatchery->chickTypes()->where('code', 'sasso')->exists());
        $this->assertSame(4, $schedule->boxesAvailable()); // 230 chicks = 4 whole boxes of 50
    }

    public function test_customers_book_whole_boxes_for_pickup_at_the_chick_seller(): void
    {
        $schedule = $this->schedule();
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/customer/chick-orders', [
            'hatch_schedule_id' => $schedule->id, 'boxes' => 3, 'store_id' => $this->stores['A']->id,
        ])->assertCreated()
            ->assertJsonPath('data.store.id', $this->stores['A']->id)
            ->assertJsonPath('data.items.0.quantity', 150)
            ->assertJsonPath('data.items.0.boxes', 3)
            ->assertJsonPath('data.items.0.hatch_date', $schedule->hatch_date->toDateString());

        $this->assertSame(1, $schedule->fresh()->boxesAvailable());

        // Only one box left; asking for two is refused.
        $this->postJson('/api/v1/customer/chick-orders', [
            'hatch_schedule_id' => $schedule->id, 'boxes' => 2, 'store_id' => $this->stores['A']->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('boxes');

        // Another seller's store is not a chick pickup point.
        $this->postJson('/api/v1/customer/chick-orders', [
            'hatch_schedule_id' => $schedule->id, 'boxes' => 1, 'store_id' => $this->stores['B']->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('store');
    }

    public function test_a_cancelled_booking_gives_its_boxes_back_and_full_dates_say_so(): void
    {
        $schedule = $this->schedule(capacity: 100);
        $order = app(ChickService::class)->placeOrder($this->customer, $schedule->id, 2, $this->stores['A']->id);

        $this->assertSame(HatchSchedule::FULL, $schedule->fresh()->status());

        app(OrderService::class)->cancelByCustomer($order, $this->customer);

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(2, $schedule->fresh()->boxesAvailable());
        $this->assertSame(HatchSchedule::OPEN, $schedule->fresh()->status());
    }

    public function test_booking_closes_after_the_cut_off(): void
    {
        $schedule = $this->schedule(days: 2); // cut-off was yesterday

        $this->assertSame(HatchSchedule::CLOSED, $schedule->status());
        $this->expectException(ValidationException::class);
        app(ChickService::class)->placeOrder($this->customer, $schedule->id, 1, $this->stores['A']->id);
    }

    public function test_the_catalogue_lists_types_hatcheries_dates_and_pickup_stores(): void
    {
        $this->schedule();
        $this->schedule(type: 'broiler', days: 20);

        $response = $this->getJson('/api/v1/market/chicks')->assertOk()
            ->assertJsonPath('box_size', 50)
            ->assertJsonPath('show_prices', false)
            ->assertJsonPath('pickup_stores.0.id', $this->stores['A']->id);

        $sasso = collect($response->json('types'))->firstWhere('code', 'sasso');
        $this->assertSame('Kuku Bora Hatchery', $sasso['hatcheries'][0]['name']);
        $this->assertSame(4, $sasso['hatcheries'][0]['dates'][0]['boxes_available']);
        $this->assertNull($sasso['hatcheries'][0]['dates'][0]['price_per_chick']); // prices hidden

        PlatformSetting::set(PlatformSetting::MARKET_SHOW_PRICES, true);
        $sasso = collect($this->getJson('/api/v1/market/chicks')->json('types'))->firstWhere('code', 'sasso');
        $this->assertSame(125000, (int) $sasso['hatcheries'][0]['dates'][0]['price_per_box']);
    }

    public function test_the_seller_completes_a_chick_order_without_touching_shop_stock(): void
    {
        $schedule = $this->schedule();
        $order = app(ChickService::class)->placeOrder($this->customer, $schedule->id, 1, $this->stores['A']->id);
        $staff = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $order->merchant_id]);

        app(OrderService::class)->advance($order, Order::READY, $staff);
        app(OrderService::class)->advance($order->fresh(), Order::COMPLETED, $staff);

        $this->assertSame(Order::COMPLETED, $order->fresh()->status);
        $this->assertSame(3, $schedule->fresh()->boxesAvailable()); // collected chicks stay booked
    }

    public function test_customers_book_chicks_on_the_website_in_four_steps(): void
    {
        $schedule = $this->schedule();

        $this->get(route('market.chicks'))->assertOk()->assertSee('Sasso')->assertSee('Tanbro');

        Livewire::actingAs($this->customer)->test(Chicks::class)
            ->call('chooseType', 'sasso')
            ->assertSee('Kuku Bora Hatchery')
            ->call('chooseHatchery', $this->hatchery->slug)
            ->assertSee('4 boxes left')
            ->call('chooseDate', $schedule->id)
            ->set('boxes', 2)
            ->assertSee('100')
            ->call('book')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, $schedule->fresh()->boxesAvailable());
        $this->assertSame($this->stores['A']->id, Order::latest('id')->first()->branch_id);
    }

    public function test_sample_hatch_dates_are_added_once_and_removed_unless_booked(): void
    {
        $this->hatchery->chickTypes()->sync(ChickType::whereIn('code', ['broiler', 'tanbro'])->pluck('id'));

        $this->artisan('chicks:seed-samples', ['--weeks' => 2])->assertSuccessful();
        $this->artisan('chicks:seed-samples', ['--weeks' => 2])->assertSuccessful();
        $this->assertSame(4, HatchSchedule::where('is_sample', true)->count());

        $booked = HatchSchedule::where('is_sample', true)->first();
        app(ChickService::class)->placeOrder($this->customer, $booked->id, 1, $this->stores['A']->id);

        $this->artisan('chicks:seed-samples', ['--remove' => true])->assertSuccessful();
        $this->assertSame([$booked->id], HatchSchedule::pluck('id')->all());
    }

    public function test_the_chick_seller_is_found_by_name_when_it_has_no_code(): void
    {
        $this->stores['A']->merchant->update(['code' => null, 'business_name' => 'Amsterdam Group']);
        config(['hub.chick_seller' => 'SEL-NONE', 'hub.chick_seller_name' => 'Amsterdam Group']);

        $this->assertSame($this->stores['A']->merchant_id, app(ChickService::class)->seller()?->id);
    }
}
