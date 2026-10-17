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
                    'email' => 'This shop account has been deactivated. Contact DukaFlow support.',
                ]);
            }

            Auth::login($user);
            request()->session()->regenerate();

            return redirect($user->isInternal() ? route('admin.dashboard') : route('portal.dashboard'));
        }

        session(['google_pending' => [
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->getName() ?: $googleUser->getNickname(),
            'email' => $googleUser->getEmail(),
        ]]);

        return redirect()->route('register.complete');
    }
}
