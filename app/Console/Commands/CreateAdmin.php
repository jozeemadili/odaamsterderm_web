<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Creates (or promotes) a platform super administrator. Asks for the password without showing it. */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email? : Login email} {--name= : Full name}';

    protected $description = 'Create a platform super administrator';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email');
        $name = $this->option('name') ?: $this->ask('Full name', 'Platform Administrator');

        if ($user = User::where('email', $email)->first()) {
            if (! $this->confirm("{$email} already exists. Make it a super administrator?", true)) {
                return self::FAILURE;
            }
        } else {
            $password = $this->secret('Password (min 10 characters, hidden)');
            $confirm = $this->secret('Repeat password');

            $validator = Validator::make(
                ['email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
                ['email' => ['required', 'email'], 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]],
            );
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'user_type' => User::TYPE_INTERNAL,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->update(['user_type' => User::TYPE_INTERNAL]);
        $user->syncRoles(['super_admin']);
        $this->info("{$email} is a super administrator. Sign in at ".url('/login'));

        return self::SUCCESS;
    }
}
