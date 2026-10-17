<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

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
                'email' => 'This shop account has been deactivated. Contact DukaFlow support.',
            ]);
        }

        request()->session()->regenerate();

        return $this->redirect($user->isInternal() ? route('admin.dashboard') : route('portal.dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
