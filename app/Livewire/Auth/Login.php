<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    /**
     * Who the page is for: customers by default (the marketplace header);
     * "seller" from the footer's seller links. Any account can sign in on
     * either — only the sign-up links and Google's new-account path differ.
     */
    #[Url]
    public string $as = '';

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isMerchantUser() && $user->merchant && ! $user->merchant->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This shop account has been deactivated. Contact Amsterdam Digital support.',
            ]);
        }

        request()->session()->regenerate();

        return $this->redirectIntended($user->homeUrl(), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login', ['seller' => $this->as === 'seller']);
    }
}
