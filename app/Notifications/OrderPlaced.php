<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

/** Tells a seller's staff a new marketplace order is waiting. */
class OrderPlaced extends Notification
{
    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_placed',
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'status' => $this->order->status,
            'title' => "New order {$this->order->reference}",
            'message' => "{$this->order->customer_name} ordered {$this->order->items_count} item(s) for pickup at {$this->order->branch->name} — TZS ".number_format((float) $this->order->total).'.',
        ];
    }
}
