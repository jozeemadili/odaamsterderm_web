<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Portal\Inventory\Index as InventoryIndex;
use App\Livewire\Portal\Pos\Index as Pos;
use App\Livewire\Portal\Staff\Index as Staff;
use App\Livewire\Portal\StoreSwitcher;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\StoreAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class StoreAccessTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    private Branch $second;

    private InventoryItem $mwengeItem;

    private InventoryItem $tegetaItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSeller();
        $this->second = Branch::create(['merchant_id' => $this->merchant->id, 'name' => 'Tegeta', 'latitude' => -6.74, 'longitude' => 39.21]);
        $this->mwengeItem = $this->createItem(['name' => 'Mwenge Starter']);
        $this->tegetaItem = $this->createItem(['name' => 'Tegeta Starter', 'branch_id' => $this->second->id]);
        app(InventoryService::class)->stockIn($this->mwengeItem, 10);
        app(InventoryService::class)->stockIn($this->tegetaItem, 10);
    }

    private function staff(array $stores = [], bool $all = false, string $role = 'merchant_sales'): User
    {
        $user = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->merchant->id, 'all_branches' => $all]);
        $user->assignRole($role);
        $user->branches()->sync(collect($stores)->pluck('id'));

        return $user;
    }

    public function test_owner_adds_staff_for_one_store_or_all_stores(): void
    {
        Livewire::actingAs($this->owner)->test(Staff::class)
            ->set('name', 'Asha')->set('email', 'asha@example.com')->set('role', 'merchant_sales')
            ->set('storeAccess', 'selected')->set('storeIds', [(string) $this->second->id])
            ->call('create')->assertHasNoErrors();

        $asha = User::where('email', 'asha@example.com')->first();
        $this->assertFalse($asha->all_branches);
        $this->assertSame([$this->second->id], StoreAccess::branchIds($asha));

        Livewire::actingAs($this->owner)->test(Staff::class)
            ->set('name', 'Juma')->set('email', 'juma@example.com')->set('role', 'merchant_manager')
            ->set('storeAccess', 'selected')->set('storeIds', [])
            ->call('create')->assertHasErrors('storeIds');

        Livewire::actingAs($this->owner)->test(Staff::class)
            ->call('editAccess', $asha->id)->set('storeAccess', 'all')->call('updateAccess');
        $this->assertTrue($asha->fresh()->all_branches);
        $this->assertCount(2, StoreAccess::branchIds($asha->fresh()));
    }

    public function test_staff_only_see_and_open_their_store(): void
    {
        $tegetaStaff = $this->staff([$this->second]);

        Livewire::actingAs($tegetaStaff)->test(InventoryIndex::class)
            ->assertSee('Tegeta Starter')->assertDontSee('Mwenge Starter');
        $this->actingAs($tegetaStaff)->get(route('portal.inventory.show', $this->mwengeItem))->assertForbidden();
        $this->actingAs($tegetaStaff)->get(route('portal.inventory.show', $this->tegetaItem))->assertOk();

        Sanctum::actingAs($tegetaStaff);
        $names = collect($this->getJson('/api/v1/inventory')->assertOk()->json('data'))->pluck('name');
        $this->assertEquals(['Tegeta Starter'], $names->all());
        $this->getJson("/api/v1/inventory/{$this->mwengeItem->id}")->assertForbidden();
    }

    public function test_owner_switches_store_and_sees_everything_in_all_stores(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(InventoryIndex::class)->assertSee('Tegeta Starter')->assertSee('Mwenge Starter');

        Livewire::test(StoreSwitcher::class)->set('selected', (string) $this->store->id);
        Livewire::test(InventoryIndex::class)->assertSee('Mwenge Starter')->assertDontSee('Tegeta Starter');

        Livewire::test(StoreSwitcher::class)->set('selected', '');
        Livewire::test(InventoryIndex::class)->assertSee('Tegeta Starter');
    }

    public function test_pos_sells_from_the_chosen_store_and_records_it(): void
    {
        $this->actingAs($this->owner);

        // Several stores and none chosen: the till asks first.
        Livewire::test(Pos::class)->assertSee('Which store are you selling from?')->call('addToCart', $this->tegetaItem->id)->assertSet('cart', []);

        Livewire::test(Pos::class)->call('chooseStore', $this->second->id);
        Livewire::test(Pos::class)
            ->assertSee('Selling from')
            ->call('addToCart', $this->tegetaItem->id)
            ->call('checkout')
            ->assertHasNoErrors();

        $this->assertSame($this->second->id, SalesRecord::latest('id')->first()->branch_id);
        $this->assertEquals(9, $this->tegetaItem->fresh()->quantity_on_hand);
    }

    public function test_app_sale_takes_the_items_store_and_refuses_mixed_stores(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/pos/checkout', ['items' => [
            ['inventory_item_id' => $this->mwengeItem->id, 'quantity' => 1],
            ['inventory_item_id' => $this->tegetaItem->id, 'quantity' => 1],
        ]])->assertStatus(422)->assertJsonPath('errors.items.0', 'All items in a sale must come from the same store.');

        $this->postJson('/api/v1/pos/checkout', ['items' => [['inventory_item_id' => $this->tegetaItem->id, 'quantity' => 2]]])->assertCreated();
        $this->assertSame($this->second->id, SalesRecord::latest('id')->first()->branch_id);

        // A Mwenge-only cashier cannot sell Tegeta stock.
        Sanctum::actingAs($this->staff([$this->store]));
        $this->postJson('/api/v1/pos/checkout', ['items' => [['inventory_item_id' => $this->tegetaItem->id, 'quantity' => 1]]])->assertStatus(422);
    }

    public function test_app_gets_the_persons_stores_and_owner_sets_staff_access(): void
    {
        $staff = $this->staff([$this->second]);
        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('user.all_stores', false)
            ->assertJsonPath('user.stores', [['id' => $this->second->id, 'name' => 'Tegeta']]);
        $this->assertCount(1, $this->getJson('/api/v1/branches')->json('data'));

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.all_stores', true)->assertJsonCount(2, 'user.stores');

        $created = $this->postJson('/api/v1/staff', [
            'name' => 'Neema', 'email' => 'neema@example.com', 'role' => 'merchant_sales',
            'all_stores' => false, 'store_ids' => [$this->store->id],
        ])->assertSuccessful()->json('data');
        $this->assertSame([$this->store->id], StoreAccess::branchIds(User::find($created['id'])));

        $this->patchJson("/api/v1/staff/{$created['id']}", ['all_stores' => true])->assertOk()->assertJsonPath('data.all_stores', true);
    }
}
