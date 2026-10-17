<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'google_id', 'password', 'user_type', 'merchant_id', 'phone', 'status', 'latitude', 'longitude', 'location_label'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_MERCHANT = 'merchant';

    /** Marketplace buyer: farmer, livestock keeper or retailer. */
    public const TYPE_CUSTOMER = 'customer';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isInternal(): bool
    {
        return $this->user_type === self::TYPE_INTERNAL;
    }

    public function isMerchantUser(): bool
    {
        return $this->user_type === self::TYPE_MERCHANT;
    }

    public function isCustomer(): bool
    {
        return $this->user_type === self::TYPE_CUSTOMER;
    }

    /** Where this user lands after signing in. */
    public function homeUrl(): string
    {
        return match (true) {
            $this->isInternal() => route('admin.dashboard'),
            $this->isMerchantUser() => route('portal.dashboard'),
            default => route('market.home'),
        };
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Creates a marketplace customer account.
     *
     * @param  array{name:string, email:string, phone?:?string, password?:?string, google_id?:?string}  $data
     */
    public static function registerCustomer(array $data): self
    {
        $user = self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'google_id' => $data['google_id'] ?? null,
            'password' => $data['password'] ?? \Illuminate\Support\Str::random(32),
            'user_type' => self::TYPE_CUSTOMER,
        ]);

        $user->assignRole('customer');

        return $user;
    }

    public function roleLabel(): ?string
    {
        $role = $this->roles->first()?->name;

        if (! $role) {
            return null;
        }

        return str($role)->after('merchant_')->replace('_', ' ')->title()->toString();
    }
}
