<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #01162f; }
        h1 { font-size: 18px; color: #01162f; margin-bottom: 2px; }
        .meta { color: #5c6b7a; font-size: 11px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f6f9fc; text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; color: #5c6b7a; border-bottom: 1px solid #e3e8ee; }
        td { padding: 6px 8px; border-bottom: 1px solid #e3e8ee; font-size: 11px; }
        .right { text-align: right; }
        .total-row td { font-weight: bold; border-top: 2px solid #01162f; border-bottom: none; }
        .badge { color: #c89a44; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $merchant->business_name }}</h1>
    <p class="meta">Sales report &middot; {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d M Y') }} &middot; Generated {{ now()->format('d M Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Customer</th>
                <th class="right">Items</th>
                <th class="right">Subtotal</th>
                <th class="right">Discount</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $sale)
                <tr>
                    <td>{{ $sale->sale_date->format('d M Y') }}</td>
                    <td>{{ $sale->created_at->format('H:i') }}</td>
                    <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                    <td class="right">{{ $sale->items_count }}</td>
                    <td class="right">{{ number_format($sale->subtotal ?? $sale->amount, 0) }}</td>
                    <td class="right">{{ $sale->discount_amount ? number_format($sale->discount_amount, 0) : '—' }}</td>
                    <td class="right">{{ number_format($sale->amount, 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; color:#5c6b7a;">No sales in this period.</td></tr>
            @endforelse
        </tbody>
        @if($sales->isNotEmpty())
            <tfoot>
                <tr class="total-row">
                    <td colspan="6">Total</td>
                    <td class="right">TZS {{ number_format($total, 0) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
