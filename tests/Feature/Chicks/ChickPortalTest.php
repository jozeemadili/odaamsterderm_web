<?php

namespace Tests\Feature\Chicks;

use App\Livewire\Portal\Chicks\Index;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ChickPortalTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->stores['A']->merchant->update(['code' => 'SEL-CHICKS']);
        config(['hub.chick_seller' => 'SEL-CHICKS']);
        $this->staff = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
    }

    public function test_only_the_chick_seller_manages_chicks(): void
    {
        $other = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['B']->merchant_id]);

        $this->actingAs($this->staff)->get(route('portal.chicks.index'))->assertOk()->assertSee('Hatch dates');
        $this->actingAs($other)->get(route('portal.chicks.index'))->assertForbidden();
    }

    public function test_the_seller_adds_a_hatchery_and_its_hatch_dates(): void
    {
        $sasso = ChickType::where('code', 'sasso')->value('id');

        Livewire::actingAs($this->staff)->test(Index::class)
            ->set('tab', 'hatcheries')
            ->call('newHatchery')
            ->set('name', 'Kuku Bora Hatchery')
            ->set('location', 'Moshi')
            ->set('types', [(string) $sasso])
            ->call('saveHatchery')
            ->assertHasNoErrors();

        $hatchery = Producer::where('name', 'Kuku Bora Hatchery')->sole();
        $this->assertSame(Producer::DEALS_CHICKS, $hatchery->deals_in);

        Livewire::actingAs($this->staff)->test(Index::class)
            ->call('newDate')
            ->set('producer_id', (string) $hatchery->id)
            ->set('chick_type_id', (string) $sasso)
            ->set('hatch_date', today()->addDays(14)->toDateString())
            ->assertSet('order_cutoff_date', today()->addDays(11)->toDateString())
            ->set('capacity', '1000')
            ->call('saveDate')
            ->assertHasNoErrors()
            ->assertSee('Kuku Bora Hatchery');

        $this->assertSame(1000, HatchSchedule::sole()->capacity);
    }

    public function test_hatch_dates_import_from_csv(): void
    {
        $hatchery = Producer::create(['name' => 'Kuku Bora Hatchery', 'deals_in' => Producer::DEALS_CHICKS, 'is_active' => true]);
        $d1 = today()->addDays(10)->toDateString();
        $d2 = today()->addDays(17)->toDateString();
        $csv = UploadedFile::fake()->createWithContent('dates.csv', "hatchery,type,hatch_date,chicks,price\nKuku Bora Hatchery,broiler,{$d1},5000,2200\nKuku Bora Hatchery,layers,{$d2},3000,\nNobody,broiler,{$d1},100,\n");

        Livewire::actingAs($this->staff)->test(Index::class)
            ->set('csv', $csv)
            ->call('importCsv')
            ->assertSet('importErrors', ["Line 4: unknown hatchery 'Nobody'"]);

        $this->assertSame(2, $hatchery->hatchSchedules()->count());
        $this->assertEqualsCanonicalizing(['broiler', 'layers'], $hatchery->chickTypes()->pluck('code')->all());
    }
}
