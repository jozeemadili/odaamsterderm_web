<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send()
    {
        $this->validate(['email' => ['required', 'email']]);

        // Always show the same confirmation regardless of whether the email
        // exists, so this form can't be used to enumerate accounts.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
