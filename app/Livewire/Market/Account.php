<?php

namespace App\Livewire\Market;

use App\Services\AccountDeletion;
use App\Support\GeoPoint;
use App\Support\MarketLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** A customer's profile and default marketplace location. Orders arrive in Phase 3. */
#[Layout('layouts.market', ['title' => 'My account'])]
class Account extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public $latitude = null;

    public $longitude = null;

    public string $location_label = '';

    /** Confirmation for deleting the account: the password, or DELETE for Google-only accounts. */
    public string $deletePassword = '';

    public string $deleteConfirm = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->phone = (string) $user->phone;
        $this->email = $user->email;
        $this->latitude = $user->latitude;
        $this->longitude = $user->longitude;
        $this->location_label = (string) $user->location_label;
    }

    public function save(): void
    {
        $user = Auth::user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update(['name' => $this->name, 'phone' => $this->phone, 'email' => $this->email]);

        if ($point = GeoPoint::tryFrom($this->latitude, $this->longitude, trim($this->location_label) ?: 'Saved location')) {
            MarketLocation::set($point);
            $this->dispatch('market-location-updated');
        }

        session()->flash('status', 'Your details are saved.');
    }

    public function deleteAccount(AccountDeletion $deletion)
    {
        $user = Auth::user();

        if ($user->has_password) {
            if (! Hash::check($this->deletePassword, $user->password)) {
                throw ValidationException::withMessages(['deletePassword' => 'The password is incorrect.']);
            }
        } elseif (! in_array(mb_strtoupper(trim($this->deleteConfirm)), ['DELETE', 'FUTA'], true)) {
            throw ValidationException::withMessages(['deleteConfirm' => 'Type DELETE to confirm.']);
        }

        $deletion->delete($user);

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Your account has been deleted.');

        return $this->redirectRoute('market.home');
    }

    public function render()
    {
        return view('livewire.market.account');
    }
}
