<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $reference }} · {{ $merchant->business_name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { {{ $merchant->brandCssVars() }} }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #01162F; font-size: 14px; }
        .receipt { max-width: 420px; margin: 16px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .head { background: var(--brand); color: var(--brand-ink); padding: 18px 20px; display: flex; align-items: center; gap: 14px; }
        .head img { height: 52px; width: 52px; object-fit: contain; background: #fff; border-radius: 8px; padding: 4px; }
        .head h1 { margin: 0; font-size: 18px; }
        .head p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
        .band { height: 5px; background: var(--brand-accent); }
        .body { padding: 18px 20px; }
        .meta { display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; margin-bottom: 12px; gap: 8px; }
        .meta strong { display: block; color: #01162F; font-size: 15px; }
        .tag { display: inline-block; background: var(--brand-accent); color: var(--brand-accent-ink); border-radius: 999px; padding: 2px 10px; font-size: 11px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 7px 0; border-bottom: 1px dashed #e5e7eb; vertical-align: top; }
        td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .small { font-size: 11px; color: #6b7280; }
        .totals td { border: none; padding: 3px 0; }
        .grand td { font-size: 17px; font-weight: 700; padding-top: 10px; border-top: 2px solid var(--brand-accent); }
        .note { margin-top: 14px; padding: 10px 12px; border-radius: 8px; background: #f9fafb; border-left: 4px solid var(--brand-accent); font-size: 12px; }
        .foot { text-align: center; font-size: 11px; color: #9ca3af; padding: 12px 20px 18px; }
        .actions { max-width: 420px; margin: 0 auto 24px; display: flex; gap: 8px; padding: 0 16px; }
        .actions button { flex: 1; border: 0; border-radius: 999px; padding: 11px; font-size: 14px; font-weight: 600; cursor: pointer; background: var(--brand); color: var(--brand-ink); }
        .actions button.alt { background: var(--brand-accent); color: var(--brand-accent-ink); }
        @media print { body { background: #fff; } .receipt { box-shadow: none; margin: 0 auto; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="head">
            @if($merchant->logo())
                <img src="{{ $merchant->logo()->getUrl() }}" alt="{{ $merchant->business_name }} logo">
            @endif
            <div>
                <h1>{{ $merchant->business_name }}</h1>
                <p>{{ collect([$store?->name, $store?->address, $store?->city])->filter()->unique()->join(' · ') }}</p>
                @if($store?->phone ?? $merchant->phone)<p>{{ $store?->phone ?? $merchant->phone }}</p>@endif
            </div>
        </div>
        <div class="band"></div>
        <div class="body">
            <div class="meta">
                <div>{{ $title }}<strong>{{ $reference }}</strong></div>
                <div style="text-align:right">{{ $date?->format('d M Y, H:i') }}@if($status)<br><span class="tag">{{ $status }}</span>@endif</div>
            </div>
            @if($customer)<p class="small">Customer: {{ $customer }}</p>@endif

            <table>
                @foreach($lines as $line)
                    <tr>
                        <td>{{ $line['name'] }}<br><span class="small">{{ rtrim(rtrim(number_format($line['qty'], 2, '.', ''), '0'), '.') }}{{ $line['unit'] ? ' '.$line['unit'] : '' }} × TZS {{ number_format($line['price']) }}</span></td>
                        <td class="num">TZS {{ number_format($line['total']) }}</td>
                    </tr>
                @endforeach
            </table>

            <table class="totals" style="margin-top:10px">
                @if($subtotal !== null && $discount > 0)
                    <tr><td>Subtotal</td><td class="num">TZS {{ number_format($subtotal) }}</td></tr>
                    <tr><td>Discount</td><td class="num">− TZS {{ number_format($discount) }}</td></tr>
                @endif
                <tr class="grand"><td>Total</td><td class="num">TZS {{ number_format($total) }}</td></tr>
                @if($payment)<tr><td class="small">Payment</td><td class="num small">{{ $payment }}</td></tr>@endif
            </table>

            @if($note)<div class="note">{{ $note }}</div>@endif
        </div>
        <div class="foot">Thank you for shopping with {{ $merchant->business_name }}<br>Powered by Odaamsterderm Hub</div>
    </div>
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" class="alt" onclick="navigator.share ? navigator.share({ title: document.title, url: location.href }) : navigator.clipboard.writeText(location.href).then(() => this.textContent = 'Link copied')">Share</button>
    </div>
</body>
</html>
