<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAudienceTest extends TestCase
{
    use RefreshDatabase;

    private function claims(string $aud): array
    {
        return ['aud' => $aud, 'sub' => 'google-123', 'email' => 'buyer@example.com', 'name' => 'Buyer', 'email_verified' => 'true'];
    }

    public function test_app_tokens_from_the_app_client_id_are_accepted_alongside_the_web_client(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.google.client_id' => 'web-live.apps.googleusercontent.com', 'services.google.app_client_ids' => ['web-old.apps.googleusercontent.com']]);
        $user = User::factory()->create(['email' => 'buyer@example.com', 'user_type' => User::TYPE_CUSTOMER, 'google_id' => 'google-123']);
        $user->assignRole('customer');

        Http::fake(['oauth2.googleapis.com/*' => Http::sequence()
            ->push($this->claims('web-old.apps.googleusercontent.com'))
            ->push($this->claims('web-live.apps.googleusercontent.com'))
            ->push($this->claims('someone-else.apps.googleusercontent.com'))]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'x'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x'])->assertOk();
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x'])->assertStatus(422);
    }

    public function test_app_google_sign_in_is_for_customers_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.google.client_id' => 'web-live.apps.googleusercontent.com']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->claims('web-live.apps.googleusercontent.com'))]);

        // A new Google user becomes a customer in one step.
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'account_type' => 'customer'])
            ->assertOk()->assertJsonPath('user.user_type', User::TYPE_CUSTOMER);

        // A seller's Google account is turned away on the customer path.
        User::where('email', 'buyer@example.com')->update(['user_type' => User::TYPE_MERCHANT]);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'account_type' => 'customer'])
            ->assertStatus(422)->assertJsonValidationErrors('id_token');
    }

    public function test_new_google_users_finish_signing_up_with_their_details(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.google.client_id' => 'web-live.apps.googleusercontent.com']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->claims('web-live.apps.googleusercontent.com'))]);

        // Not registered: the app is told, nothing is created yet.
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'account_type' => 'customer', 'ask_signup' => true])
            ->assertOk()->assertJsonPath('needs_registration', true)->assertJsonPath('email', 'buyer@example.com');
        $this->assertDatabaseMissing('users', ['email' => 'buyer@example.com']);

        // The sign-up form sends the token back with the name and phone.
        $this->postJson('/api/v1/auth/register-customer', ['id_token' => 'x', 'name' => 'Asha Buyer', 'phone' => '+255700000555'])
            ->assertCreated()->assertJsonPath('user.email', 'buyer@example.com')->assertJsonPath('user.phone', '+255700000555');
        $this->assertDatabaseHas('users', ['email' => 'buyer@example.com', 'google_id' => 'google-123', 'user_type' => User::TYPE_CUSTOMER, 'has_password' => false]);

        // Registered now: the same Google account simply signs in.
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'account_type' => 'customer', 'ask_signup' => true])
            ->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/v1/auth/register-customer', ['id_token' => 'x', 'name' => 'Again', 'phone' => '+255700000555'])
            ->assertJsonValidationErrors('email');
    }
}
