{{-- Expects $store (Branch); optional $distance (km from the customer) for a delivery fee estimate --}}
@php
    $distance ??= null;
    $feeToYou = $store->deliveryFeeFor($distance);
@endphp
<ul class="space-y-1 text-ink-secondary">
    @if($store->offersDelivery())
        <li>
            Delivers within {{ rtrim(rtrim(number_format($store->delivery_radius_km, 1), '0'), '.') }} km
            · TZS {{ number_format($store->delivery_base_fee ?? 0) }}{{ $store->delivery_fee_per_km ? ' + '.number_format($store->delivery_fee_per_km).' per km' : '' }}
            @if($feeToYou !== null)
                <span class="text-ink">(about TZS {{ number_format($feeToYou) }} to you)</span>
            @elseif($distance !== null)
                <span class="text-ink-mute">(you are outside the delivery area)</span>
            @endif
            {{-- Checkout is pickup only for now; delivery is agreed with the store. --}}
            <span class="block text-[12px] text-ink-mute">Arrange delivery with the store after ordering.</span>
        </li>
    @else
        <li>No delivery</li>
    @endif
    <li>{{ $store->pickup_available ? 'Pickup at the store' : 'No pickup' }}</li>
    @if($store->cold_chain_capable)
        <li>Cold storage for vaccines (2–8 °C)</li>
    @endif
    @if($store->vet_advice_on_site)
        <li>Vet / livestock officer advice on site</li>
    @endif
</ul>
