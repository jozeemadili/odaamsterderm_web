<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Merchant;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a login on any environment: a platform admin, a seller (business,
 * owner login, first store, approved for the marketplace) or a customer.
 * The password is typed hidden by whoever runs the command.
 */
class CreateAccount extends Command
{
    protected $signature = 'app:create-account
        {type : admin, seller or customer}
        {email? : Login email}
        {--name= : Person\'s full name}
        {--phone= : Phone number}
        {--business= : Seller business name}
        {--store= : Seller first store name}
        {--existing-business= : Give an existing seller business (e.g. a demo seller) its owner login}';

    protected $description = 'Create an admin, seller or customer login (password asked hidden)';

    public function handle(): int
    {
        $type = strtolower((string) $this->argument('type'));
        if (! in_array($type, ['admin', 'seller', 'customer'], true)) {
            $this->error('Type must be admin, seller or customer.');

            return self::FAILURE;
        }

        $email = $this->argument('email') ?: $this->ask('Email');
        if (User::where('email', $email)->exists()) {
            $this->error("{$email} already has an account.");

            return self::FAILURE;
        }

        $existing = null;
        if ($type === 'seller' && ($businessName = $this->option('existing-business'))) {
            $existing = Merchant::where('business_name', $businessName)->first();
            if (! $existing) {
                $this->error("No seller business named \"{$businessName}\".");

                return self::FAILURE;
            }
            if ($existing->users()->exists()) {
                $this->error("{$businessName} already has a login.");

                return self::FAILURE;
            }
        }

        $name = $this->option('name') ?: ($existing?->owner_name ?? $this->ask('Full name'));
        $phone = $this->option('phone') ?: ($type === 'admin' ? null : $this->ask('Phone (e.g. +2557…)', ''));
        $business = $type === 'seller' ? ($existing?->business_name ?? $this->option('business') ?: $this->ask('Business name')) : null;

        $password = $this->secret('Password (min 10 characters, letters and numbers, hidden)');
        $confirm = $this->secret('Repeat password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'business' => $business, 'password' => $password, 'password_confirmation' => $confirm],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'business' => [$type === 'seller' ? 'required' : 'nullable', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(fn () => match ($type) {
            'admin' => $this->admin($name, $email, $password),
            'seller' => $existing
                ? $this->ownerLogin($existing, $name, $email, $phone ?: null, $password)
                : $this->seller($name, $email, $phone ?: null, $password, $business),
            'customer' => User::registerCustomer(['name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'password' => $password]),
        });
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info(ucfirst($type)." account created for {$email}. Sign in at ".url('/login'));
        if ($type === 'seller') {
            $this->line('Next: sign in, open Stores and set the store\'s map location, then add stock and list it on the marketplace.');
        }

        return self::SUCCESS;
    }

    protected function admin(string $name, string $email, string $password): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'user_type' => User::TYPE_INTERNAL]);
        $user->assignRole('super_admin');

        return $user;
    }

    protected function ownerLogin(Merchant $merchant, string $name, string $email, ?string $phone, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => $merchant->id,
        ]);
        $user->assignRole('merchant_owner');

        return $user;
    }

    protected function seller(string $name, string $email, ?string $phone, string $password, string $business): User
    {
        $merchant = Merchant::create([
            'business_name' => $business,
            'owner_name' => $name,
            'phone' => $phone ?? '',
            'email' => $email,
            'kyc_status' => Merchant::KYC_APPROVED,
            'reviewed_at' => now(),
        ]);
        PaymentMethod::seedDefaultsFor($merchant->id);

        Branch::create([
            'merchant_id' => $merchant->id,
            'name' => $this->option('store') ?: $business,
            'is_primary' => true,
        ]);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => $merchant->id,
        ]);
        $user->assignRole('merchant_owner');

        return $user;
    }
}
