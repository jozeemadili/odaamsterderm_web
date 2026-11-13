<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\UserResource;
use App\Models\Merchant;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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

        $claims = $this->verifiedGoogleClaims((string) $request->string('id_token'));

        $googleId = $claims['sub'];
        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? null;

        $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();
        $customersOnly = $request->input('account_type') === User::TYPE_CUSTOMER;

        // For now Google sign-in in the app is for customers only: sellers
        // and staff sign in there with their email and password.
        if ($user && $customersOnly && ! $user->isCustomer()) {
            throw ValidationException::withMessages([
                'id_token' => 'Google sign-in in the app is for customers. Sellers: please sign in with your email and password.',
            ]);
        }

        // The app can ask to be told about a new Google user, so it can open
        // the sign-up form (prefilled) instead of creating the account here.
        if (! $user && $customersOnly && $request->boolean('ask_signup')) {
            return response()->json([
                'needs_registration' => true,
                'google_id' => $googleId,
                'email' => $email,
                'name' => $name,
            ]);
        }

        // Older app versions: sign the new customer up in one step.
        if (! $user && $customersOnly && $email) {
            return $this->issueSession(User::registerCustomer([
                'name' => $name ?: $email,
                'email' => $email,
                'google_id' => $googleId,
            ]));
        }

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
                'has_password' => isset($data['password']),
                'user_type' => User::TYPE_MERCHANT,
                'merchant_id' => $merchant->id,
                'phone' => $data['phone'],
            ]);

            $user->assignRole('merchant_owner');

            return $user;
        });

        return $this->issueSession($user);
    }

    /** Marketplace customer sign-up (email + password, or a verified Google identity). */
    public function registerCustomer(Request $request)
    {
        // With a Google ID token the email and Google identity come from the
        // verified token, and no password is needed.
        if ($request->filled('id_token')) {
            $data = $request->validate([
                'id_token' => ['required', 'string'],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:30'],
            ]);

            $claims = $this->verifiedGoogleClaims($data['id_token']);
            $email = $claims['email'] ?? null;

            if (! $email || User::where('google_id', $claims['sub'])->orWhere('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'This Google account is already registered. Please sign in instead.']);
            }

            return $this->issueSession(User::registerCustomer([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $email,
                'google_id' => $claims['sub'],
            ]))->setStatusCode(201);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        return $this->issueSession(User::registerCustomer($data))->setStatusCode(201);
    }

    /**
     * Verifies a Google ID token with Google and checks it was issued for one
     * of our clients; returns its claims (sub, email, name…).
     *
     * @return array<string, mixed>
     */
    private function verifiedGoogleClaims(string $idToken): array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        if (! $response->ok()) {
            throw ValidationException::withMessages(['id_token' => 'That Google sign-in could not be verified.']);
        }

        $claims = $response->json();
        $audiences = array_filter([config('services.google.client_id'), ...config('services.google.app_client_ids', [])]);

        if (! in_array($claims['aud'] ?? null, $audiences, true) || empty($claims['sub'])) {
            throw ValidationException::withMessages(['id_token' => 'That Google sign-in could not be verified.']);
        }

        return $claims;
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
            throw ValidationException::withMessages(['email' => 'This shop account has been deactivated. Contact Amsterdam Digital support.']);
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

    protected function issueSession(User $user): JsonResponse
    {
        $token = $user->createToken('odaamsterderm-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
            'merchant' => $user->merchant ? new MerchantResource($user->merchant) : null,
        ]);
    }
}
