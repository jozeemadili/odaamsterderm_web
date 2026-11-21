<?php

namespace Tests\Feature\Chicks;

use App\Livewire\Market\Chicks;
use App\Models\Branch;
use App\Models\ChickContract;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\InventoryItem;
use App\Models\LifeStage;
use App\Models\Order;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\CareSuggestions;
use App\Services\ChickService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CareSuggestionsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private HatchSchedule $day;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 09:00');
        $this->buildMarketplace();

        $hatchery = Producer::create(['name' => 'Kuku Bora Hatchery', 'deals_in' => Producer::DEALS_CHICKS, 'is_active' => true]);
        $contract = app(ChickService::class)->saveContract($this->stores['A']->merchant, [
            'producer_id' => $hatchery->id, 'chick_type_id' => ChickType::where('code', 'broiler')->value('id'),
        ]);
        $this->day = $contract->orderDays()->orderBy('hatch_date')->first();
        $this->customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER, 'phone' => '+255700000001']);

        // Store A stocks starter feed in two bag sizes, a chick vitamin and a Newcastle vaccine.
        $this->stock('Broiler Starter Crumble', 'C1010', ['LS-POU-STR'], ['25 kg bag' => [25, 'kg', 10], '50 kg bag' => [50, 'kg', 10]], ['use_cases' => ['early_growth']]);
        $this->stock('Vitamix Chick', 'C2010', ['LS-POU-STR'], ['30 g sachet' => [30, 'g', 20], '100 g sachet' => [100, 'g', 20]], ['use_cases' => ['early_growth', 'stress_vaccination_transport'], 'inclusion_or_dosage' => '5 g per 20 L of drinking water']);
        $this->stock('Newcastle Vaccine (LaSota)', 'C40', ['LS-ALL'], ['100 doses vial' => [100, 'doses', 10], '500 doses vial' => [500, 'doses', 10]], ['disease_target' => ['newcastle']]);
        // A concentrate for mixing is never suggested as chick feed.
        $this->stock('Broiler Concentrate', 'C1010', ['LS-POU-STR'], ['50 kg bag' => [50, 'kg', 10]], ['use_cases' => ['feed_mixing']]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, array{0: float, 1: string, 2: int}>  $packs  name => [size, unit, units online] */
    private function stock(string $name, string $category, array $stages, array $packs, array $attrs = [], ?Branch $store = null): void
    {
        $store ??= $this->stores['A'];
        $product = Product::create($attrs + [
            'name' => $name, 'slug' => Product::uniqueSlug($name), 'product_category_id' => ProductCategory::where('code', $category)->value('id'),
            'animal_type_id' => 1, 'unit' => 'pack', 'status' => Product::STATUS_APPROVED,
        ]);
        $product->lifeStages()->sync(LifeStage::whereIn('code', $stages)->pluck('id'));

        foreach ($packs as $packName => [$size, $unit, $online]) {
            $variant = $product->variants()->create(['name' => $packName, 'pack_size_value' => $size, 'pack_size_unit' => $unit, 'selling_unit' => 'pack']);
            $item = InventoryItem::create([
                'merchant_id' => $store->merchant_id, 'branch_id' => $store->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
                'name' => "{$name} {$packName}", 'unit' => 'pack', 'unit_price' => 10000, 'unit_cost' => 9000,
            ]);
            app(InventoryService::class)->stockIn($item, $online);
            app(InventoryService::class)->updateMarketplace($item, true, $online);
        }
    }

    private function suggestions(int $boxes, ?int $store = null): array
    {
        return app(CareSuggestions::class)->forBooking($this->day->load('chickType'), $boxes, $store ?? $this->stores['A']->id);
    }

    public function test_suggestions_are_sized_to_the_chicks_in_whole_packs(): void
    {
        $s = collect($this->suggestions(3))->keyBy('step'); // 300 chicks

        $this->assertSame(['starter_feed', 'vitamins', 'newcastle'], $s->keys()->all()); // at most three, most important first
        // 300 × 0.3 kg = 90 kg: two 50 kg bags waste less than four 25 kg bags... both leave 10 kg, so fewer bags wins.
        $this->assertSame(2, $s['starter_feed']['quantity']);
        $this->assertStringContainsString('50 kg bag', $s['starter_feed']['name']);
        $this->assertSame('90 kg', $s['starter_feed']['amount']);
        // 300 chicks × 0.05 L × 3 days = 45 L at 5 g per 20 L = 11.25 g: one 30 g sachet.
        $this->assertSame(1, $s['vitamins']['quantity']);
        $this->assertStringContainsString('30 g sachet', $s['vitamins']['name']);
        // 300 doses: one 500-dose vial wastes less than three 100-dose vials... no: 3 × 100 wastes nothing.
        $this->assertSame(3, $s['newcastle']['quantity']);
        $this->assertStringContainsString('100 doses', $s['newcastle']['name']);
    }

    public function test_only_what_the_pickup_store_has_is_suggested(): void
    {
        $this->assertSame([], $this->suggestions(1, $this->stores['B']->id));
    }

    public function test_added_suggestions_join_the_booking_and_reserve_stock(): void
    {
        $feed = collect($this->suggestions(1))->firstWhere('step', 'starter_feed'); // 100 chicks: 30 kg → one 50 kg bag
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/customer/chick-orders', [
            'hatch_schedule_id' => $this->day->id, 'boxes' => 1, 'store_id' => $this->stores['A']->id,
            'extras' => [['offer_id' => $feed['offer_id'], 'quantity' => $feed['quantity']]],
        ])->assertCreated()->assertJsonPath('data.items_count', 2);

        $order = Order::latest('id')->first();
        $this->assertSame(['care_plan'], $order->items()->whereNotNull('source')->pluck('source')->all());
        $this->assertSame(9.0, (float) InventoryItem::find($feed['offer_id'])->marketplace_quantity);
    }

    public function test_nothing_is_added_unless_ticked_and_the_order_page_offers_them_later(): void
    {
        Livewire::actingAs($this->customer)->test(Chicks::class)
            ->set('type', 'broiler')->set('agent', (string) $this->stores['A']->merchant_id)
            ->call('chooseDay', $this->day->id)
            ->assertSee('Ready for their first days')
            ->assertSee('Broiler Starter Crumble')
            ->assertDontSee('Broiler Concentrate')
            ->call('book')
            ->assertHasNoErrors();

        $order = Order::latest('id')->first();
        $this->assertSame(1, $order->items()->count());

        $this->actingAs($this->customer)->get(route('market.order', $order))->assertOk()->assertSee('Want these ready when you collect?');
        $this->getJson("/api/v1/customer/orders/{$order->id}/suggestions")->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_ticked_suggestion_on_the_website_joins_the_booking(): void
    {
        $vit = collect($this->suggestions(1))->firstWhere('step', 'vitamins');

        Livewire::actingAs($this->customer)->test(Chicks::class)
            ->set('type', 'broiler')->set('agent', (string) $this->stores['A']->merchant_id)
            ->call('chooseDay', $this->day->id)
            ->set("extras.{$vit['offer_id']}", true)
            ->call('book')
            ->assertHasNoErrors();

        $this->assertSame(2, Order::latest('id')->first()->items()->count());
    }

    public function test_use_rates_are_read_as_grams_per_litre(): void
    {
        $care = app(CareSuggestions::class);
        $this->assertSame(0.25, $care->dosePerLitre('5 g per 20 L of drinking water'));
        $this->assertSame(2.0, $care->dosePerLitre('2 ml/litre for 5 days'));
        $this->assertSame(1.0, $care->dosePerLitre('1g kwa lita 1'));
        $this->assertNull($care->dosePerLitre('Follow the label'));
        $this->assertInstanceOf(ChickContract::class, $this->day->contract);
    }
}
