<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-mute max-w-3xl">Checks that stock, reservations and listings stay consistent, and that orders don't stall. Critical items can cause overselling or unfulfillable orders.</p>
        <label class="flex items-center gap-2 text-[13px] text-ink-secondary">
            <input type="checkbox" wire:model.live="showInfo" class="rounded border-hairline-input text-primary focus:ring-primary"> Show informational checks
        </label>
    </div>

    @foreach($checks as $check)
        @php
            $tone = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'neutral'][$check['severity']];
        @endphp
        <x-ui.card padding="p-0">
            <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3 {{ $check['count'] ? '' : 'opacity-70' }}">
                <div>
                    <div class="flex items-center gap-2">
                        <x-ui.badge :tone="$tone">{{ $check['severity'] }}</x-ui.badge>
                        <h2 class="text-[15px] font-medium">{{ $check['title'] }}</h2>
                    </div>
                    <p class="text-[12px] text-ink-mute mt-1 max-w-3xl">{{ $check['description'] }}</p>
                </div>
                <span class="text-[22px] font-light tnum {{ $check['count'] && $check['severity'] === 'critical' ? 'text-ruby' : '' }}">{{ $check['count'] }}</span>
            </div>

            @if($check['count'])
                <div class="overflow-x-auto border-t border-hairline">
                    <table class="w-full text-[13px] min-w-[760px]">
                        <tbody class="divide-y divide-hairline">
                            @foreach($check['rows']->take(50) as $row)
                                @if($check['kind'] === 'order')
                                    <tr>
                                        <td class="px-5 py-2.5"><a href="{{ route('admin.orders.show', $row) }}" class="text-primary tnum">{{ $row->reference }}</a></td>
                                        <td class="px-5 py-2.5 text-ink-secondary">{{ $row->merchant->business_name }} · {{ $row->branch->name }}</td>
                                        <td class="px-5 py-2.5 text-ink-secondary">{{ $row->customer_name }} {{ $row->customer_phone }}</td>
                                        <td class="px-5 py-2.5 tnum">TZS {{ number_format($row->total) }}</td>
                                        <td class="px-5 py-2.5 text-ink-mute">{{ ($row->ready_at ?? $row->created_at)->diffForHumans() }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="px-5 py-2.5"><span class="font-medium">{{ $row->name }}</span>
                                            @if($check['key'] === 'hidden_listings' && $row->marketplaceBlockers())
                                                <span class="block text-[11px] text-lemon">{{ $row->marketplaceBlockers()[0] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2.5 text-ink-secondary"><a href="{{ route('admin.merchants.show', $row->merchant) }}" class="text-primary">{{ $row->merchant->business_name }}</a> · {{ $row->branch?->name }}</td>
                                        <td class="px-5 py-2.5 tnum text-ink-secondary">stock {{ rtrim(rtrim((string) $row->quantity_on_hand, '0'), '.') }}</td>
                                        <td class="px-5 py-2.5 tnum text-ink-secondary">reserved {{ rtrim(rtrim((string) $row->reserved_quantity, '0'), '.') }}@isset($row->open_order_units) <span class="text-ink-mute">(open orders {{ rtrim(rtrim((string) $row->open_order_units, '0'), '.') }})</span>@endisset</td>
                                        <td class="px-5 py-2.5 tnum text-ink-secondary">online {{ rtrim(rtrim((string) $row->marketplace_quantity, '0'), '.') }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            @if($canHide && $row->is_marketplace_visible && $check['severity'] !== 'info')
                                                <button wire:click="hideListing({{ $row->id }})" wire:confirm="Hide {{ $row->name }} from the marketplace?" class="text-[12px] text-ruby hover:opacity-80">Hide listing</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endforeach
</div>
