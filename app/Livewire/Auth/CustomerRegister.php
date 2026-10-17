<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Sign-up for marketplace customers (farmers, livestock keepers, retailers). */
#[Layout('layouts.guest', ['title' => 'Create your account'])]
class CustomerRegister extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::registerCustomer($data);

        Auth::login($user);
        session()->regenerate();

        return $this->redirectIntended($user->homeUrl(), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.customer-register');
    }
}
