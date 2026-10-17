<?php

namespace App\Notifications;

use App\Models\Branch;
use Illuminate\Notifications\Notification;

/** Tells a seller's staff the platform suspended or reactivated a store. */
class StoreStatusChanged extends Notification
{
    public function __construct(public readonly Branch $store) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $suspended = ! $this->store->isActive();

        return [
            'type' => 'store_status',
            'store_id' => $this->store->id,
            'status' => $this->store->status,
            'title' => $suspended ? "{$this->store->name} was suspended" : "{$this->store->name} is active again",
            'message' => $suspended
                ? 'Its products are hidden from the marketplace'.($this->store->suspension_reason ? ": {$this->store->suspension_reason}" : '.').' Contact Odaamsterderm Hub support to resolve this.'
                : 'Its listings are visible on the marketplace again.',
        ];
    }
}
