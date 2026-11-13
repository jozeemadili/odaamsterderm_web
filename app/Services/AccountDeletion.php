<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A customer deleting their own account (required by Google Play and the
 * App Store). Their personal details are wiped and they can no longer sign
 * in; order records stay for the shops' books, without the person's name,
 * phone or notes. Orders a shop is already preparing keep the contact
 * details until the shop hands them over.
 */
class AccountDeletion
{
    public function __construct(private readonly OrderService $orders) {}

    public function delete(User $user): void
    {
        if (! $user->isCustomer()) {
            throw ValidationException::withMessages([
                'account' => 'Seller and staff accounts are closed by Amsterdam Digital support. Please contact us.',
            ]);
        }

        // Orders the shop has not started yet are cancelled, so the stock is freed.
        $user->orders()->whereIn('status', [Order::PENDING, Order::ACCEPTED])->get()
            ->each(fn (Order $order) => $this->orders->cancelByCustomer($order, $user, 'Customer deleted their account'));

        DB::transaction(function () use ($user) {
            Cart::for($user)->items()->delete();

            $user->orders()->whereNotIn('status', [Order::PREPARING, Order::READY])->update([
                'customer_name' => 'Deleted customer',
                'customer_phone' => null,
                'customer_note' => null,
            ]);

            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->tokens()->delete();
            $user->notifications()->delete();

            $user->forceFill([
                'name' => 'Deleted customer',
                'email' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(8)).'@deleted.invalid',
                'phone' => null,
                'google_id' => null,
                'avatar_path' => null,
                'latitude' => null,
                'longitude' => null,
                'location_label' => null,
                'password' => Hash::make(Str::random(64)),
                'has_password' => false,
                'status' => 'deleted',
                'remember_token' => null,
            ])->save();
        });
    }
}
