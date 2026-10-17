<?php

namespace App\Livewire\Admin\Merchants;

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

#[Layout('layouts.admin', ['title' => 'New Merchant'])]
class Create extends Component
{
    public string $business_name = '';

    public string $owner_name = '';

    public string $phone = '';

    public string $email = '';

    public string $business_type_id = '';

    public string $region_id = '';

    public string $city = '';

    public bool $markVerified = true;

    public function save()
    {
        $this->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users,email'],
            'business_type_id' => ['nullable', 'exists:business_types,id'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $admin = Auth::user();

        $merchant = DB::transaction(function () use ($admin) {
            $merchant = Merchant::create([
                'business_name' => $this->business_name,
                'owner_name' => $this->owner_name,
                'phone' => $this->phone,
                'email' => $this->email,
                'business_type_id' => $this->business_type_id ?: null,
                'region_id' => $this->region_id ?: null,
                'city' => $this->city,
                'kyc_status' => $this->markVerified ? Merchant::KYC_APPROVED : Merchant::KYC_PENDING,
                'created_by_agent_id' => $admin->id,
                'reviewed_by' => $this->markVerified ? $admin->id : null,
                'reviewed_at' => $this->markVerified ? now() : null,
            ]);

            PaymentMethod::seedDefaultsFor($merchant->id);

            $owner = User::create([
                'name' => $this->owner_name,
                'email' => $this->email,
                'password' => Hash::make(str()->random(16)),
                'user_type' => User::TYPE_MERCHANT,
                'merchant_id' => $merchant->id,
                'phone' => $this->phone,
            ]);

            $owner->assignRole('merchant_owner');

            return $merchant;
        });

        session()->flash('status', "Merchant \"{$merchant->business_name}\" created. The owner should use \"forgot password\" with {$this->email} to set their login password.");

        return $this->redirect(route('admin.merchants.show', $merchant), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.merchants.create', [
            'businessTypes' => BusinessType::active()->orderBy('name')->get(),
            'regions' => Region::active()->orderBy('name')->get(),
        ]);
    }
}
