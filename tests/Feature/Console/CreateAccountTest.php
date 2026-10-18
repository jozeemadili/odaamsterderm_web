<?php

namespace Tests\Feature\Console;

use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class CreateAccountTest extends TestCase
{
    use RefreshDatabase;

    private function create(string $type, string $email, array $options = []): PendingCommand
    {
        return $this->artisan('app:create-account', ['type' => $type, 'email' => $email, '--name' => 'Test Person', '--phone' => '+255700000123'] + $options)
            ->expectsQuestion('Password (min 10 characters, letters and numbers, hidden)', 'Secret12345')
            ->expectsQuestion('Repeat password', 'Secret12345');
    }

    public function test_creates_admin_seller_and_customer_logins(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->create('admin', 'admin@example.com')->assertSuccessful();
        $this->create('seller', 'seller@example.com', ['--business' => 'Test Agrovet'])->assertSuccessful();
        $this->create('customer', 'customer@example.com')->assertSuccessful();

        $this->assertTrue(User::where('email', 'admin@example.com')->first()->hasRole('super_admin'));

        $seller = User::where('email', 'seller@example.com')->first();
        $this->assertTrue($seller->hasRole('merchant_owner'));
        $this->assertSame(Merchant::KYC_APPROVED, $seller->merchant->kyc_status);
        $this->assertSame('Test Agrovet', $seller->merchant->branches()->first()->name);

        $customer = User::where('email', 'customer@example.com')->first();
        $this->assertTrue($customer->isCustomer());
        $this->assertNotNull($customer->email_verified_at);

        // The typed password works for signing in.
        foreach (['admin', 'seller', 'customer'] as $who) {
            $this->assertTrue(Auth::validate(['email' => "{$who}@example.com", 'password' => 'Secret12345']));
        }
    }

    public function test_rejects_duplicates_and_weak_passwords(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->create('customer', 'dup@example.com')->assertSuccessful();

        $this->artisan('app:create-account', ['type' => 'customer', 'email' => 'dup@example.com'])->assertFailed();
        $this->artisan('app:create-account', ['type' => 'boss', 'email' => 'x@example.com'])->assertFailed();
    }
}
