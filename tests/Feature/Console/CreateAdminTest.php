<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_has_no_demo_accounts_and_admin_can_be_created(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->assertSame(0, User::count());

        $this->artisan('app:create-admin', ['email' => 'boss@example.com', '--name' => 'Boss'])
            ->expectsQuestion('Password (min 10 characters, hidden)', 'Secret12345')
            ->expectsQuestion('Repeat password', 'Secret12345')
            ->assertSuccessful();

        $admin = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue(Hash::check('Secret12345', $admin->password));
        $this->assertNotNull($admin->email_verified_at);

        $this->artisan('app:create-admin', ['email' => 'weak@example.com', '--name' => 'Weak'])
            ->expectsQuestion('Password (min 10 characters, hidden)', 'short')
            ->expectsQuestion('Repeat password', 'short')
            ->assertFailed();
    }
}
