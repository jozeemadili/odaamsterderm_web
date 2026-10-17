<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; }
        .page { padding: 16px; }
        .page-title { font-size: 10px; color: #9ca3af; margin: 0 0 10px 0; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid td { width: 33.33%; padding: 4px; vertical-align: top; }
        .label { border: 1px dashed #c7ccd4; border-radius: 4px; padding: 8px; text-align: center; height: 122px; overflow: hidden; }
        .label .shop-logo { height: 16px; max-width: 90%; margin-bottom: 2px; }
        .label .biz { font-size: 8px; color: #9ca3af; text-transform: uppercase; letter-spacing: .3px; margin: 0 0 2px 0; }
        .label .name { font-size: 11px; font-weight: bold; color: #1a1a1a; margin: 0 0 2px 0; }
        .label .price { font-size: 12px; font-weight: bold; color: #01162F; margin: 0 0 4px 0; }
        .label .barcode-img { height: 34px; }
        .label .code { font-size: 9px; letter-spacing: 1px; color: #333; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="page">
        <p class="page-title">{{ $merchant->business_name }} &middot; Barcode labels &middot; {{ now()->format('d M Y') }}</p>

        @php $rows = array_chunk($labels, 3); @endphp

        @foreach($rows as $row)
            <table class="grid">
                <tr>
                    @foreach($row as $label)
                        <td>
                            <div class="label">
                                @if($shopLogoDataUri)
                                    <img src="{{ $shopLogoDataUri }}" class="shop-logo" alt="">
                                @endif
                                <p class="biz">{{ $merchant->business_name }}</p>
                                @if($showProductName)
                                    <p class="name">{{ \Illuminate\Support\Str::limit($label['name'], 26) }}</p>
                                @endif
                                @if($showPrice && $label['price'])
                                    <p class="price">TZS {{ number_format($label['price'], 0) }}</p>
                                @endif
                                <img src="{{ $label['image'] }}" class="barcode-img" alt="">
                                <p class="code">{{ $label['barcode'] }}</p>
                            </div>
                        </td>
                    @endforeach
                    @for($i = count($row); $i < 3; $i++)
                        <td></td>
                    @endfor
                </tr>
            </table>
        @endforeach
    </div>
</body>
</html>
