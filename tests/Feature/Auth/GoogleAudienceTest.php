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
}
