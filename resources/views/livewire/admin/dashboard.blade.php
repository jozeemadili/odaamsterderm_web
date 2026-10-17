<div class="space-y-4">
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total merchants" :value="$totalMerchants" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Approved" :value="$approvedMerchants" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Pending KYC" :value="$pendingKyc" tone="lemon" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Payments to verify" :value="$pendingPayments" tone="lemon" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Flagged payments" :value="$flaggedPayments" tone="ruby" />
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-ui.card>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] text-ink-secondary">Merchants by KYC status</h2>
            </div>
            @if(array_sum($kycValues) > 0)
                <div class="flex items-center gap-6">
                    <x-ui.chart
                        type="doughnut"
                        :labels="$kycLabels"
                        :height="160"
                        :datasets="[[
                            'data' => $kycValues,
                            'backgroundColor' => ['#c89a44', '#01162f', '#1f8a57', '#ea2261', '#f3e6c8'],
                            'borderWidth' => 0,
                        ]]"
                        :options="['cutout' => '72%']"
                        class="w-40 shrink-0"
                    />
                    <div class="space-y-2.5 flex-1">
                        @foreach($kycLabels as $i => $label)
                            @php $colors = ['#c89a44', '#01162f', '#1f8a57', '#ea2261', '#f3e6c8']; @endphp
                            <div class="flex items-center justify-between text-[13px]">
                                <span class="flex items-center gap-2 text-ink-secondary">
                                    <span class="h-2 w-2 rounded-full" style="background-color: {{ $colors[$i % 5] }}"></span>
                                    {{ $label }}
                                </span>
                                <span class="tnum text-ink font-medium">{{ $kycValues[$i] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-[13px] text-ink-mute">No merchants yet.</p>
            @endif
        </x-ui.card>

        <x-ui.card>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] text-ink-secondary">Payment volume (14 days)</h2>
                <span class="text-[13px] text-ink-mute tnum">TZS {{ number_format(array_sum($paymentTrendValues), 0) }}</span>
            </div>
            <x-ui.chart
                type="line"
                :labels="$paymentTrendLabels"
                :height="200"
                :datasets="[[
                    'data' => $paymentTrendValues,
                    'borderColor' => '#c89a44',
                    'backgroundColor' => 'rgba(200,154,68,0.12)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                ]]"
                :options="[
                    'scales' => [
                        'y' => ['ticks' => ['display' => false], 'grid' => ['display' => false], 'border' => ['display' => false]],
                        'x' => ['grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => ['color' => '#64748d', 'font' => ['size' => 11]]],
                    ],
                ]"
            />
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">Merchants by region</h2>
            @forelse($regionLabels as $i => $label)
                @php $max = max($regionValues ?: [1]); @endphp
                <div class="mb-3">
                    <div class="flex items-center justify-between text-[13px] mb-1">
                        <span class="text-ink-secondary">{{ $label }}</span>
                        <span class="tnum text-ink font-medium">{{ $regionValues[$i] }}</span>
                    </div>
                    <div class="h-1.5 bg-hairline rounded-pill overflow-hidden">
                        <div class="h-full bg-primary rounded-pill" style="width: {{ $max ? ($regionValues[$i] / $max * 100) : 0 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-[13px] text-ink-mute">No regional data yet.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">Recent activity</h2>
            <div class="space-y-3">
                @forelse($recentActivity as $activity)
                    <div class="flex items-start justify-between text-[13px] pb-3 border-b border-hairline last:border-0 last:pb-0">
                        <div>
                            <p class="text-ink-secondary">
                                <span class="font-medium">{{ $activity->causer?->name ?? 'System' }}</span>
                                {{ $activity->event }} {{ class_basename($activity->subject_type ?? '') }}
                            </p>
                            <p class="text-ink-mute text-[12px] mt-0.5">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-[13px] text-ink-mute">No activity recorded yet.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    <div class="flex gap-3 text-[13px]">
        <a href="{{ route('admin.kyc.index') }}" class="bg-canvas border border-hairline rounded-pill px-4 py-2 hover:border-primary/40 text-ink-secondary">Review KYC queue &rarr;</a>
        <a href="{{ route('admin.payments.index') }}" class="bg-canvas border border-hairline rounded-pill px-4 py-2 hover:border-primary/40 text-ink-secondary">Verify payments &rarr;</a>
        <a href="{{ route('admin.merchants.index') }}" class="bg-canvas border border-hairline rounded-pill px-4 py-2 hover:border-primary/40 text-ink-secondary">All merchants &rarr;</a>
    </div>
</div>
