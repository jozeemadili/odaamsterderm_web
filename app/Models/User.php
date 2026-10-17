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

#[Fillable(['name', 'email', 'google_id', 'password', 'user_type', 'merchant_id', 'phone', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_MERCHANT = 'merchant';

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

    public function roleLabel(): ?string
    {
        $role = $this->roles->first()?->name;

        if (! $role) {
            return null;
        }

        return str($role)->after('merchant_')->replace('_', ' ')->title()->toString();
    }
}
