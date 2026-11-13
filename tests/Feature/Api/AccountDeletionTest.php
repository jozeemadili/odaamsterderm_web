<?php

namespace Tests\Feature\Api;

use App\Livewire\Market\Account;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private function customer(array $attributes = []): User
    {
        $user = User::factory()->create(['user_type' => User::TYPE_CUSTOMER, 'password' => 'secret-pass', 'phone' => '+255700000001'] + $attributes);
        $user->assignRole('customer');

        return $user;
    }

    public function test_a_customer_deletes_their_account_from_the_app(): void
    {
        $this->buildMarketplace();
        $user = $this->customer();
        $offer = InventoryItem::where('branch_id', $this->stores['B']->id)->firstOrFail();
        $orders = app(OrderService::class);
        $orders->addToCart($user, $offer->id, 2);
        $order = $orders->checkout($user, 'Leave at gate', '+255700000001')->first();
        $reserved = (float) $offer->fresh()->reserved_quantity;
        $orders->addToCart($user, $offer->id, 1);
        $user->createToken('phone');

        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/me', ['password' => 'wrong'])->assertJsonValidationErrors('password');
        $this->deleteJson('/api/v1/me', ['password' => 'secret-pass'])->assertNoContent();

        $user->refresh();
        $this->assertSame('Deleted customer', $user->name);
        $this->assertNull($user->phone);
        $this->assertStringEndsWith('@deleted.invalid', $user->email);
        $this->assertSame(0, $user->tokens()->count());

        // The unstarted order is cancelled, its stock freed and the details removed.
        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertSame('Deleted customer', $order->customer_name);
        $this->assertNull($order->customer_phone);
        $this->assertNull($order->customer_note);
        $this->assertLessThan($reserved, (float) $offer->fresh()->reserved_quantity);
    }

    public function test_google_accounts_confirm_by_typing_delete(): void
    {
        $user = $this->customer(['google_id' => 'g-1']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/me', ['confirm' => 'no'])->assertJsonValidationErrors('confirm');
        $this->deleteJson('/api/v1/me', ['confirm' => 'futa'])->assertNoContent();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_sellers_must_contact_support(): void
    {
        $seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'password' => 'secret-pass']);
        Sanctum::actingAs($seller);

        $this->deleteJson('/api/v1/me', ['password' => 'secret-pass'])->assertJsonValidationErrors('account');
        $this->assertNotSame('deleted', $seller->fresh()->status);
    }

    public function test_customers_can_delete_on_the_website_too(): void
    {
        $user = $this->customer();

        Livewire::actingAs($user)->test(Account::class)
            ->set('deletePassword', 'secret-pass')
            ->call('deleteAccount')
            ->assertRedirect(route('market.home'));

        $this->assertSame('deleted', $user->fresh()->status);
        $this->assertGuest();
    }

    public function test_the_store_pages_are_public(): void
    {
        $this->get(route('market.privacy'))->assertOk()->assertSee('Privacy policy')->assertSee('Anthropic');
        $this->get(route('market.delete-account'))->assertOk()->assertSee('Delete account')->assertSee('What is deleted');
        $this->get('/')->assertSee(route('market.privacy'), false)->assertSee(route('market.delete-account'), false);
    }
}
