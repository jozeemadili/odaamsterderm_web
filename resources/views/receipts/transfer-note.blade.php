<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transfer note {{ $transfer->number }} · {{ $merchant->business_name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { {{ $merchant->brandCssVars() }} }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #01162F; font-size: 14px; }
        .note { max-width: 720px; margin: 16px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .head { background: var(--brand); color: var(--brand-ink); padding: 18px 22px; display: flex; align-items: center; gap: 14px; }
        .head img { height: 52px; width: 52px; object-fit: contain; background: #fff; border-radius: 8px; padding: 4px; }
        .head h1 { margin: 0; font-size: 18px; }
        .head p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
        .band { height: 5px; background: var(--brand-accent); }
        .body { padding: 20px 22px; }
        .title { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
        .title h2 { margin: 0; font-size: 20px; }
        .tag { display: inline-block; background: var(--brand-accent); color: var(--brand-accent-ink); border-radius: 999px; padding: 2px 10px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .route { display: grid; grid-template-columns: 1fr auto 1fr; gap: 12px; align-items: center; margin: 16px 0; }
        .route div { background: #f9fafb; border-radius: 8px; padding: 10px 12px; }
        .route small { display: block; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .route strong { font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6b7280; font-weight: 500; padding: 8px 0; border-bottom: 2px solid var(--brand-accent); }
        td { padding: 8px 0; border-bottom: 1px dashed #e5e7eb; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .small { font-size: 12px; color: #6b7280; }
        .signs { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 32px; }
        .signs div { border-top: 1px solid #01162F; padding-top: 6px; font-size: 12px; }
        .foot { text-align: center; font-size: 11px; color: #9ca3af; padding: 12px 22px 18px; }
        .actions { max-width: 720px; margin: 0 auto 24px; padding: 0 16px; }
        .actions button { width: 100%; border: 0; border-radius: 999px; padding: 11px; font-size: 14px; font-weight: 600; cursor: pointer; background: var(--brand); color: var(--brand-ink); }
        @media print { body { background: #fff; } .note { box-shadow: none; margin: 0 auto; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="note">
        <div class="head">
            @if($merchant->logo())
                <img src="{{ $merchant->logo()->getUrl() }}" alt="{{ $merchant->business_name }} logo">
            @endif
            <div>
                <h1>{{ $merchant->business_name }}</h1>
                <p>Stock transfer note</p>
            </div>
        </div>
        <div class="band"></div>
        <div class="body">
            <div class="title">
                <h2>{{ $transfer->number }}</h2>
                <span class="tag">{{ $transfer->isSent() ? 'On the way' : $transfer->status }}</span>
            </div>
            <div class="route">
                <div><small>From</small><strong>{{ $transfer->fromBranch?->name }}</strong><br><span class="small">Sent {{ $transfer->sent_at?->format('d M Y, H:i') }} · {{ $transfer->sender?->name }}</span></div>
                <span>&rarr;</span>
                <div><small>To</small><strong>{{ $transfer->toBranch?->name }}</strong><br><span class="small">@if($transfer->received_at)Received {{ $transfer->received_at->format('d M Y, H:i') }} · {{ $transfer->receiver?->name }}@else Not yet received @endif</span></div>
            </div>

            <table>
                <thead><tr><th>#</th><th>Product</th><th class="num">Quantity</th></tr></thead>
                <tbody>
                    @foreach($transfer->items as $i => $line)
                        <tr>
                            <td class="small">{{ $i + 1 }}</td>
                            <td>{{ $line->item_name }}</td>
                            <td class="num">{{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($transfer->notes)<p class="small" style="margin-top:14px">Note: {{ $transfer->notes }}</p>@endif

            <div class="signs">
                <div>Sent by (name &amp; signature)</div>
                <div>Received by (name &amp; signature)</div>
            </div>
        </div>
        <div class="foot">Powered by Odaamsterderm Hub</div>
    </div>
    <div class="actions"><button type="button" onclick="window.print()">Print</button></div>
</body>
</html>
