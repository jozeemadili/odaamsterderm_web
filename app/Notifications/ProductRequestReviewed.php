<?php

namespace App\Notifications;

use App\Models\ProductRequest;
use Illuminate\Notifications\Notification;

/** Tells an agent's staff what happened to a product they asked to add to the catalogue. */
class ProductRequestReviewed extends Notification
{
    public function __construct(public ProductRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->request;
        $message = match ($r->status) {
            ProductRequest::APPROVED => "\"{$r->name}\" is now in the catalogue".($r->inventory_item_id ? ' and your stock is linked to it — you can list it on the marketplace.' : '. Add it to your stock from the catalogue.'),
            ProductRequest::LINKED => "\"{$r->name}\" was already in the catalogue as \"{$r->product?->name}\"".($r->inventory_item_id ? '; your stock is linked to it.' : '. Add it to your stock from the catalogue.'),
            default => "\"{$r->name}\" was not added to the catalogue".($r->review_notes ? ": {$r->review_notes}" : '.'),
        };

        return [
            'type' => 'product_request',
            'product_request_id' => $r->id,
            'status' => $r->status,
            'title' => $r->status === ProductRequest::REJECTED ? 'Product request not added' : 'Product request approved',
            'message' => $message,
        ];
    }
}
