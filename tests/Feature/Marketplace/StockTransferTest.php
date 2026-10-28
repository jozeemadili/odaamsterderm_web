<?php

namespace Tests\Feature\Marketplace;

use App\Http\Controllers\TransferNoteController;
use App\Livewire\Portal\Transfers\Index as Transfers;
use App\Livewire\Portal\Transfers\Show as TransferShow;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    private Branch $second;

    private InventoryItem $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSeller();
        $this->second = Branch::create(['merchant_id' => $this->merchant->id, 'name' => 'Tegeta']);
        $this->feed = $this->createItem(['name' => 'Layers Mash', 'unit' => 'bag', 'unit_price' => 52000]);
        app(InventoryService::class)->stockIn($this->feed, 20);
        Storage::fake('public');
        $this->feed->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection('image');
        $this->feed->addMedia(UploadedFile::fake()->image('back.jpg'))->toMediaCollection(InventoryItem::BACK_PHOTO);
    }

    private function staff(array $stores, string $role = 'merchant_sales'): User
    {
        $user = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->merchant->id, 'all_branches' => false]);
        $user->assignRole($role);
        $user->branches()->sync(collect($stores)->pluck('id'));

        return $user;
    }

    public function test_owner_sends_stock_and_destination_staff_receive_it(): void
    {
        Livewire::actingAs($this->owner)->test(Transfers::class)
            ->set('showForm', true)
            ->set('from_branch_id', (string) $this->store->id)
            ->set('to_branch_id', (string) $this->second->id)
            ->call('addLine', $this->feed->id)
            ->set('lines.0.quantity', '5')
            ->call('send')
            ->assertHasNoErrors();

        $transfer = StockTransfer::first();
        $this->assertSame('TRF-00001', $transfer->number);
        $this->assertEquals(15, $this->feed->fresh()->quantity_on_hand);
        $this->assertSame(0, InventoryItem::where('branch_id', $this->second->id)->count());

        // A cashier at the sending store cannot receive it.
        $this->actingAs($this->staff([$this->store]));
        Livewire::test(TransferShow::class, ['transfer' => $transfer])->call('receive')->assertHasErrors('transfer');

        $tegeta = $this->staff([$this->second]);
        Livewire::actingAs($tegeta)->test(Transfers::class)->assertSee('TRF-00001')->assertSee('Waiting for you');
        Livewire::actingAs($tegeta)->test(TransferShow::class, ['transfer' => $transfer])->assertDontSee('Cancel transfer')->call('receive')->assertHasNoErrors();

        $arrived = InventoryItem::where('branch_id', $this->second->id)->sole();
        $this->assertSame('Layers Mash', $arrived->name);
        $this->assertEquals(5, $arrived->quantity_on_hand);
        $this->assertEquals(52000, $arrived->unit_price);
        $this->assertNotNull($arrived->image());
        $this->assertNotNull($arrived->backImage());
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $transfer->fresh()->status);

        // A second transfer adds to the same product in the receiving store.
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/transfers')->assertJsonPath('can_send', true)->assertJsonCount(2, 'to_stores');
        $this->getJson("/api/v1/transfers/stock?store_id={$this->store->id}&search=layers")->assertJsonPath('data.0.available', 15);
        $id = $this->postJson('/api/v1/transfers', [
            'from_store_id' => $this->store->id, 'to_store_id' => $this->second->id,
            'items' => [['inventory_item_id' => $this->feed->id, 'quantity' => 3]],
        ])->assertCreated()->assertJsonPath('data.number', 'TRF-00002')->json('data.id');

        Sanctum::actingAs($tegeta);
        $this->postJson("/api/v1/transfers/{$id}/receive")->assertOk()->assertJsonPath('data.status', 'received');
        $this->assertEquals(8, $arrived->fresh()->quantity_on_hand);
        $this->assertSame(1, InventoryItem::where('branch_id', $this->second->id)->count());
    }

    public function test_cancel_returns_stock_and_rules_are_enforced(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/transfers', [
            'from_store_id' => $this->store->id, 'to_store_id' => $this->second->id,
            'items' => [['inventory_item_id' => $this->feed->id, 'quantity' => 50]],
        ])->assertStatus(422);
        $this->postJson('/api/v1/transfers', [
            'from_store_id' => $this->store->id, 'to_store_id' => $this->store->id,
            'items' => [['inventory_item_id' => $this->feed->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $transfer = $this->postJson('/api/v1/transfers', [
            'from_store_id' => $this->store->id, 'to_store_id' => $this->second->id,
            'items' => [['inventory_item_id' => $this->feed->id, 'quantity' => 4]],
        ])->assertCreated()->json('data');
        $this->assertTrue($transfer['can_cancel']);
        $this->get($transfer['note_url'])->assertOk()->assertSee($transfer['number'])->assertSee('Tegeta');
        $this->assertEquals(16, $this->feed->fresh()->quantity_on_hand);

        // Cashiers cannot send.
        Sanctum::actingAs($this->staff([$this->store]));
        $this->postJson('/api/v1/transfers', [
            'from_store_id' => $this->store->id, 'to_store_id' => $this->second->id,
            'items' => [['inventory_item_id' => $this->feed->id, 'quantity' => 1]],
        ])->assertForbidden();

        // Staff of another store cannot see it at all.
        $other = Branch::create(['merchant_id' => $this->merchant->id, 'name' => 'Kariakoo']);
        Sanctum::actingAs($this->staff([$other]));
        $this->getJson("/api/v1/transfers/{$transfer['id']}")->assertForbidden();
        $this->assertCount(0, $this->getJson('/api/v1/transfers')->json('data'));
        $this->getJson("/api/v1/transfers/stock?store_id={$this->store->id}")->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/transfers/{$transfer['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertEquals(20, $this->feed->fresh()->quantity_on_hand);
        $this->postJson("/api/v1/transfers/{$transfer['id']}/receive")->assertStatus(422);
    }

    public function test_transfers_page_and_note_render(): void
    {
        $this->actingAs($this->owner)->get(route('portal.transfers.index'))->assertOk()->assertSee('New transfer');
        $transfer = app(StockTransferService::class)->send($this->owner, $this->store->id, $this->second->id, [['inventory_item_id' => $this->feed->id, 'quantity' => 2]]);
        $this->actingAs($this->owner)->get(route('portal.transfers.show', $transfer))->assertOk()->assertSee('Receive stock')->assertSee('Cancel transfer');
        $this->get(route('transfers.note', $transfer))->assertForbidden();
        $this->get(TransferNoteController::url($transfer))->assertOk();
    }
}
