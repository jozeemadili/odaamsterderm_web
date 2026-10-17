<?php

namespace App\Livewire\Auth;

use App\Models\BusinessType;
use App\Models\Merchant;
use App\Models\PaymentMethod;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public string $business_name = '';

    public string $owner_name = '';

    public string $phone = '';

    public string $business_type_id = '';

    public string $region_id = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register()
    {
        $this->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'business_type_id' => ['nullable', 'exists:business_types,id'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () {
            $merchant = Merchant::create([
                'business_name' => $this->business_name,
                'owner_name' => $this->owner_name,
                'phone' => $this->phone,
                'email' => $this->email,
                'business_type_id' => $this->business_type_id ?: null,
                'region_id' => $this->region_id ?: null,
                'kyc_status' => Merchant::KYC_PENDING,
            ]);

            PaymentMethod::seedDefaultsFor($merchant->id);

            $user = User::create([
                'name' => $this->owner_name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'user_type' => User::TYPE_MERCHANT,
                'merchant_id' => $merchant->id,
                'phone' => $this->phone,
            ]);

            $user->assignRole('merchant_owner');

            return $user;
        });

        Auth::login($user);
        request()->session()->regenerate();

        return $this->redirect(route('portal.dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'businessTypes' => BusinessType::active()->orderBy('name')->get(),
            'regions' => Region::active()->orderBy('name')->get(),
        ]);
    }
}
