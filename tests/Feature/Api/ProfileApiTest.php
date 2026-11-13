<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_updates_their_name_and_phone(): void
    {
        $user = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me/profile', ['name' => 'Asha Juma', 'phone' => '+255 712 345 678'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Asha Juma')
            ->assertJsonPath('data.phone', '+255 712 345 678');
    }

    public function test_phone_must_look_like_a_phone_number(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me/profile', ['phone' => 'call me'])
            ->assertJsonValidationErrors('phone');
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me/profile', ['email' => 'taken@example.com'])
            ->assertJsonValidationErrors('email');
    }

    public function test_a_user_uploads_and_replaces_and_removes_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.avatar_url', fn ($url) => str_contains($url, '/storage/avatars/'));
        $first = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($first);

        $this->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])
            ->assertOk();
        Storage::disk('public')->assertMissing($first);

        $this->deleteJson('/api/v1/me/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_the_photo_must_be_an_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('avatar');
    }

    public function test_changing_the_password_needs_the_current_one_and_signs_out_other_devices(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $phone = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($phone)->putJson('/api/v1/me/password', [
            'current_password' => 'wrong',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertJsonValidationErrors('current_password');

        $this->withToken($phone)->putJson('/api/v1/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count(), 'Only this device stays signed in.');
    }

    public function test_a_google_account_sets_its_first_password_without_a_current_one(): void
    {
        $user = User::factory()->create(['google_id' => 'g-123']);
        $this->assertFalse($user->has_password);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.has_password', false);

        $this->putJson('/api/v1/me/password', [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk()->assertJsonPath('data.has_password', true);
    }

    public function test_guests_cannot_change_a_profile(): void
    {
        $this->patchJson('/api/v1/me/profile', ['name' => 'x'])->assertUnauthorized();
    }
}
