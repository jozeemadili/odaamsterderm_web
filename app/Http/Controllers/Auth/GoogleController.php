<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // "?as=customer" comes from the marketplace sign-up page: a new Google
        // account then becomes a customer straight away instead of being
        // asked for business details.
        session(['google_intent' => request('as') === 'customer' ? 'customer' : 'merchant']);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            Log::error('Google sign-in failed', ['message' => $e->getMessage(), 'class' => get_class($e)]);

            return redirect()->route('login')->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }

            if ($user->isMerchantUser() && $user->merchant && ! $user->merchant->isActive()) {
                return redirect()->route('login')->withErrors([
                    'email' => 'This shop account has been deactivated. Contact Odaamsterderm Hub support.',
                ]);
            }

            Auth::login($user);
            request()->session()->regenerate();

            return redirect()->intended($user->homeUrl());
        }

        if (session()->pull('google_intent') === 'customer') {
            $user = User::registerCustomer([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $googleUser->getEmail(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
            ]);

            Auth::login($user);
            request()->session()->regenerate();

            return redirect()->intended($user->homeUrl());
        }

        session(['google_pending' => [
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName() ?: $googleUser->getNickname(),
            'email' => $googleUser->getEmail(),
        ]]);

        return redirect()->route('register.complete');
    }
}
