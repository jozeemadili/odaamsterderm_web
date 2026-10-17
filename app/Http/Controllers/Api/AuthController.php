<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\UserResource;
use App\Models\Merchant;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Verify a Google ID token from the Flutter app's native Google Sign-In and
     * either log the matching user in, or hand back the Google identity so the
     * app can collect the remaining business details via register().
     *
     * The Flutter app must request the ID token with `serverClientId` set to
     * our web OAuth client (GOOGLE_CLIENT_ID), otherwise the `aud` check below
     * will reject it.
     */
    public function google(Request $request)
    {
        $request->validate(['id_token' => ['required', 'string']]);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->string('id_token'),
        ]);

        if (! $response->ok()) {
            throw ValidationException::withMessages(['id_token' => 'That Google sign-in could not be verified.']);
        }

        $claims = $response->json();

        if (($claims['aud'] ?? null) !== config('services.google.client_id')) {
            throw ValidationException::withMessages(['id_token' => 'That Google sign-in could not be verified.']);
        }

        $googleId = $claims['sub'];
        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? null;

        $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

        if (! $user) {
            return response()->json([
                'needs_registration' => true,
                'google_id' => $googleId,
                'email' => $email,
                'name' => $name,
            ]);
        }

        if (! $user->google_id) {
            $user->update(['google_id' => $googleId]);
        }

        return $this->issueSession($user);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'business_type_id' => ['nullable', 'integer', 'exists:business_types,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'email' => ['required', 'email', 'unique:users,email'],
            'google_id' => ['nullable', 'string'],
            'password' => [Rule::requiredIf(fn () => ! $request->filled('google_id')), 'nullable', 'string', 'min:8'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $merchant = Merchant::create([
                'business_name' => $data['business_name'],
                'owner_name' => $data['owner_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'business_type_id' => $data['business_type_id'] ?? null,
                'region_id' => $data['region_id'] ?? null,
                'kyc_status' => Merchant::KYC_PENDING,
            ]);

            PaymentMethod::seedDefaultsFor($merchant->id);

            $user = User::create([
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'google_id' => $data['google_id'] ?? null,
                'password' => Hash::make($data['password'] ?? Str::random(32)),
                'user_type' => User::TYPE_MERCHANT,
                'merchant_id' => $merchant->id,
                'phone' => $data['phone'],
            ]);

            $user->assignRole('merchant_owner');

            return $user;
        });

        return $this->issueSession($user);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Those credentials do not match our records.']);
        }

        if ($user->isMerchantUser() && (! $user->merchant || ! $user->merchant->isActive())) {
            throw ValidationException::withMessages(['email' => 'This shop account has been deactivated. Contact DukaFlow support.']);
        }

        return $this->issueSession($user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => new UserResource($request->user()),
            'merchant' => $request->user()->merchant ? new MerchantResource($request->user()->merchant) : null,
        ]);
    }

    protected function issueSession(User $user): \Illuminate\Http\JsonResponse
    {
        $token = $user->createToken('dukaflow-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
            'merchant' => $user->merchant ? new MerchantResource($user->merchant) : null,
        ]);
    }
}
