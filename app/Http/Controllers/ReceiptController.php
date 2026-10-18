<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SalesRecord;

/**
 * Printable receipts in the seller's own branding (logo and colours).
 * Links are signed, so customers can open or share them without signing in.
 */
class ReceiptController extends Controller
{
    public function order(Order $order)
    {
        $order->load(['items', 'branch', 'merchant']);

        return view('receipts.show', [
            'merchant' => $order->merchant,
            'store' => $order->branch,
            'title' => 'Order receipt',
            'reference' => $order->reference,
            'date' => $order->created_at,
            'status' => $order->statusLabel(),
            'customer' => trim(($order->customer_name ?? '').' '.($order->customer_phone ?? '')),
            'lines' => $order->items->map(fn ($i) => [
                'name' => $i->name, 'qty' => (float) $i->quantity, 'unit' => $i->unit,
                'price' => (float) $i->unit_price, 'total' => (float) $i->line_total,
            ]),
            'subtotal' => null,
            'discount' => 0.0,
            'total' => (float) $order->total,
            'payment' => $order->status === Order::COMPLETED ? 'Paid at pickup' : 'Pay at pickup',
            'note' => $order->status === Order::COMPLETED ? null : 'Show this reference at the counter when you collect your order.',
        ]);
    }

    public function sale(SalesRecord $sale)
    {
        $sale->load(['items', 'branch', 'merchant', 'customer']);

        return view('receipts.show', [
            'merchant' => $sale->merchant,
            'store' => $sale->branch,
            'title' => 'Sales receipt',
            'reference' => 'Sale #'.$sale->id,
            'date' => $sale->created_at ?? $sale->sale_date,
            'status' => null,
            'customer' => $sale->customer?->name,
            'lines' => $sale->items->map(fn ($i) => [
                'name' => $i->item_name, 'qty' => (float) $i->quantity, 'unit' => null,
                'price' => (float) $i->unit_price, 'total' => (float) ($i->subtotal ?? $i->gross_amount),
            ]),
            'subtotal' => $sale->subtotal !== null ? (float) $sale->subtotal : null,
            'discount' => (float) $sale->discount_amount,
            'total' => (float) $sale->amount,
            'payment' => trim(($sale->payment_method ?? '').($sale->change_due > 0 ? ' · change TZS '.number_format($sale->change_due) : '')),
            'note' => null,
        ]);
    }
}
