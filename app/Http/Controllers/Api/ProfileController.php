<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AccountDeletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** The signed-in person's own profile: details, photo and password. */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return new UserResource($user->fresh());
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $old = $user->avatar_path;

        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return new UserResource($user->fresh());
    }

    public function destroyAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return new UserResource($user->fresh());
    }

    /**
     * Deletes the customer's own account. Confirmed with the password, or
     * by typing DELETE (FUTA) when the account never had one (Google).
     */
    public function destroy(Request $request, AccountDeletion $deletion)
    {
        $user = $request->user();

        $data = $request->validate([
            'password' => [Rule::requiredIf((bool) $user->has_password), 'nullable', 'string'],
            'confirm' => [Rule::requiredIf(! $user->has_password), 'nullable', 'string'],
        ]);

        if ($user->has_password && ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        if (! $user->has_password && ! in_array(mb_strtoupper(trim((string) $data['confirm'])), ['DELETE', 'FUTA'], true)) {
            throw ValidationException::withMessages(['confirm' => __('Type DELETE to confirm.')]);
        }

        $deletion->delete($user);

        return response()->noContent();
    }

    /**
     * Changes the password. Accounts made through Google set their first
     * password without the current one. Other devices are signed out.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => [Rule::requiredIf((bool) $user->has_password), 'nullable', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if ($user->has_password && ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The current password is incorrect.'),
            ]);
        }

        $user->update(['password' => $data['password'], 'has_password' => true]);

        $current = $user->currentAccessToken();
        $user->tokens()->when($current?->id, fn ($q, $id) => $q->whereKeyNot($id))->delete();

        return new UserResource($user->fresh());
    }
}
