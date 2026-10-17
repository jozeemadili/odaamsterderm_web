<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

/**
 * Order updates: to the customer when the seller moves the order on, and to
 * the seller's staff when a customer cancels.
 */
class OrderStatusChanged extends Notification
{
    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $store = $this->order->branch->name;

        $message = match ($this->order->status) {
            Order::ACCEPTED => "{$store} accepted your order.",
            Order::PREPARING => "{$store} is preparing your order.",
            Order::READY => "Your order is ready for pickup at {$store}. Show reference {$this->order->reference} at the counter.",
            Order::COMPLETED => 'Order collected — thank you!',
            Order::REJECTED => "{$store} could not fulfil your order".($this->order->status_reason ? ": {$this->order->status_reason}" : '.'),
            Order::CANCELLED => "Order {$this->order->reference} was cancelled by the customer".($this->order->status_reason ? ": {$this->order->status_reason}" : '.'),
            default => "Order {$this->order->reference} is now {$this->order->statusLabel()}.",
        };

        return [
            'type' => 'order_status',
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'status' => $this->order->status,
            'title' => "Order {$this->order->reference}: {$this->order->statusLabel()}",
            'message' => $message,
        ];
    }
}
