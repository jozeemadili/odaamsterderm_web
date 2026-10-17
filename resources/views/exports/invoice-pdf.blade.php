<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; margin: 0; }
        .page { padding: 20px 8px; }
        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { border: none; padding: 0; vertical-align: top; }
        .logo { max-height: 56px; max-width: 200px; margin-bottom: 8px; }
        .biz-name { font-size: 15px; font-weight: bold; color: #1a1a1a; margin: 0 0 3px 0; }
        .biz-meta { font-size: 10px; color: #6b7280; line-height: 1.6; margin: 0; }
        .doc-title { font-size: 24px; font-weight: bold; margin: 0 0 4px 0; text-align: right; }
        .doc-number { font-size: 12px; color: #6b7280; margin: 0; text-align: right; }
        .doc-badge-cell { text-align: right; padding-top: 6px; }
        .rule { border: none; border-top: 3px solid #{{ ltrim($brandColor, '#') }}; margin: 14px 0 18px 0; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .badge-draft { background: #f5e9d4; color: #9b6829; }
        .badge-invoiced { background: #e1ecfb; color: #1a56b0; }
        .badge-paid { background: #dcf2e6; color: #1f8a57; }
        .badge-partial { background: #fdead9; color: #c24e00; }
        .badge-cancelled { background: #fbe3e8; color: #c21e4a; }
        .bill-to { background: #f6f9fc; padding: 10px 14px; }
        .bill-to .label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin: 0 0 4px 0; }
        .bill-to .name { font-size: 12px; font-weight: bold; color: #1a1a1a; margin: 0 0 2px 0; }
        .bill-to .meta { font-size: 10px; color: #6b7280; line-height: 1.6; }
        .details-table { width: 100%; }
        .details-table td { padding: 2px 0; font-size: 10px; border: none; }
        .details-table .label { color: #6b7280; }
        .details-table .value { text-align: right; font-weight: bold; color: #1a1a1a; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.items th { background: #{{ ltrim($brandColor, '#') }}; color: #ffffff; text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #eef1f5; font-size: 11px; }
        .item-thumb { width: 32px; height: 32px; }
        .item-thumb-placeholder { display: block; width: 32px; height: 32px; background: #eef1f5; border-radius: 4px; }
        .right { text-align: right; }
        .totals-table { width: 100%; margin-top: 12px; }
        .totals-table td { padding: 4px 10px; border: none; font-size: 11px; }
        .totals-table .grand td { font-weight: bold; font-size: 14px; background: #f6f9fc; border-top: 2px solid #{{ ltrim($brandColor, '#') }}; padding-top: 8px; padding-bottom: 8px; }
        .section-title { font-size: 11px; font-weight: bold; color: #1a1a1a; margin: 24px 0 6px 0; }
        .notes { margin-top: 24px; padding-top: 12px; border-top: 1px solid #eef1f5; font-size: 10px; color: #6b7280; }
        .footer { margin-top: 40px; padding-top: 14px; border-top: 1px solid #eef1f5; }
        .footer .qr-cell { width: 80px; vertical-align: middle; }
        .footer .qr-cell img { width: 62px; height: 62px; }
        .footer .qr-caption { font-size: 7px; color: #b0b6bf; margin: 2px 0 0 0; }
        .footer .powered-cell { vertical-align: middle; text-align: center; }
        .footer .powered { font-size: 10px; font-weight: bold; color: #9ca3af; margin: 0 0 2px 0; }
        .footer .contacts { font-size: 9px; color: #b0b6bf; margin: 0; }
        .footer .spacer-cell { width: 80px; }
    </style>
</head>
<body>
    <div class="page">
        <table class="layout">
            <tr>
                <td style="width: 55%;">
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" class="logo" alt="{{ $merchant->business_name }}">
                    @endif
                    <p class="biz-name">{{ $merchant->business_name }}</p>
                    <p class="biz-meta">
                        @if($merchant->physical_address) {{ $merchant->physical_address }}<br> @endif
                        {{ $merchant->phone }} @if($merchant->email) &middot; {{ $merchant->email }} @endif
                        @if($merchant->tin_number) <br>TIN: {{ $merchant->tin_number }} @endif
                    </p>
                </td>
                <td style="width: 45%;">
                    <p class="doc-title" style="color: #{{ ltrim($brandColor, '#') }};">{{ $invoice->isDraft() ? 'PROFORMA' : 'INVOICE' }}</p>
                    <p class="doc-number">{{ $invoice->number }}</p>
                    <p class="doc-badge-cell">
                        @if($invoice->isDraft())
                            <span class="badge badge-draft">FOR REVIEW</span>
                        @elseif($invoice->status === 'paid')
                            <span class="badge badge-paid">PAID</span>
                        @elseif($invoice->status === 'partially_paid')
                            <span class="badge badge-partial">PARTIALLY PAID</span>
                        @elseif($invoice->status === 'cancelled')
                            <span class="badge badge-cancelled">CANCELLED</span>
                        @else
                            <span class="badge badge-invoiced">{{ strtoupper($invoice->statusLabel()) }}</span>
                        @endif
                    </p>
                </td>
            </tr>
        </table>

        <hr class="rule">

        <table class="layout">
            <tr>
                <td style="width: 58%; padding-right: 12px;">
                    <div class="bill-to">
                        <p class="label">Billed to</p>
                        <p class="name">{{ $invoice->customer->name }}</p>
                        <p class="meta">
                            {{ $invoice->customer->phone }}<br>
                            {{ $invoice->customer->email }}<br>
                            {{ $invoice->customer->address }}
                        </p>
                    </div>
                </td>
                <td style="width: 42%;">
                    <table class="details-table">
                        <tr>
                            <td class="label">Issue date</td>
                            <td class="value">{{ $invoice->issue_date->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="label">Due date</td>
                            <td class="value">{{ $invoice->due_date?->format('d M Y') ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Balance due</td>
                            <td class="value">TZS {{ number_format($invoice->balanceDue(), 0) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    @if($includeImages)
                        <th style="width: 44px;">&nbsp;</th>
                    @endif
                    <th>Item</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit price</th>
                    <th class="right">Discount</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $line)
                    <tr>
                        @if($includeImages)
                            <td style="width: 44px;">
                                @if(isset($itemImages[$line->id]))
                                    <img src="{{ $itemImages[$line->id] }}" class="item-thumb" alt="">
                                @else
                                    <span class="item-thumb-placeholder"></span>
                                @endif
                            </td>
                        @endif
                        <td>{{ $line->item_name }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format($line->quantity, 2, '.', ''), '0'), '.') }}</td>
                        <td class="right">{{ number_format($line->unit_price, 0) }}</td>
                        <td class="right">{{ $line->discount_amount > 0 ? number_format($line->discount_amount, 0) : '—' }}</td>
                        <td class="right">{{ number_format($line->subtotal, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="layout">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">
                    <table class="totals-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="right">TZS {{ number_format($invoice->subtotal, 0) }}</td>
                        </tr>
                        @if($invoice->discount_amount > 0)
                            <tr>
                                <td>Discount</td>
                                <td class="right">-TZS {{ number_format($invoice->discount_amount, 0) }}</td>
                            </tr>
                        @endif
                        <tr class="grand">
                            <td>Total</td>
                            <td class="right">TZS {{ number_format($invoice->total, 0) }}</td>
                        </tr>
                        @if($invoice->amount_paid > 0)
                            <tr>
                                <td>Paid</td>
                                <td class="right">TZS {{ number_format($invoice->amount_paid, 0) }}</td>
                            </tr>
                            <tr>
                                <td>Balance due</td>
                                <td class="right">TZS {{ number_format($invoice->balanceDue(), 0) }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        @if($invoice->payments->isNotEmpty())
            <p class="section-title">Payment history</p>
            <table class="items">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Channel</th>
                        <th class="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments->sortBy('payment_date') as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->format('d M Y') }}</td>
                            <td>{{ $payment->paymentMethod?->name ?? '—' }}</td>
                            <td class="right">TZS {{ number_format($payment->amount, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($invoice->notes)
            <div class="notes">
                <strong>Notes:</strong> {{ $invoice->notes }}
            </div>
        @endif

        <div class="footer">
            @if($invoice->isDraft())
                <p class="notes" style="border-top: none; margin-top: 0; padding-top: 0;">This is a proforma invoice for review only. Prices and quantities are subject to change until approved.</p>
            @endif
            <table class="layout">
                <tr>
                    <td class="qr-cell">
                        <img src="{{ $qrDataUri }}" alt="">
                        <p class="qr-caption">Scan to get this PDF</p>
                    </td>
                    <td class="powered-cell">
                        <p class="powered">Powered by DukaFlow</p>
                        <p class="contacts">
                            {{ config('dukaflow.support_email') }}
                            @if(config('dukaflow.support_phone')) &middot; {{ config('dukaflow.support_phone') }} @endif
                            @if(config('dukaflow.support_address')) &middot; {{ config('dukaflow.support_address') }} @endif
                        </p>
                    </td>
                    <td class="spacer-cell">&nbsp;</td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
