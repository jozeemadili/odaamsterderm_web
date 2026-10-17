<?php

namespace App\Livewire\Admin;

use App\Models\Merchant;
use App\Models\PaymentRecord;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.admin', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public function render()
    {
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $paymentsByDay = PaymentRecord::selectRaw('payment_date, SUM(amount) as total')
            ->where('payment_date', '>=', now()->subDays(13)->toDateString())
            ->groupBy('payment_date')
            ->pluck('total', 'payment_date');

        $kycCounts = Merchant::selectRaw('kyc_status, COUNT(*) as total')
            ->groupBy('kyc_status')
            ->pluck('total', 'kyc_status');

        $regionCounts = Merchant::whereNotNull('region')
            ->selectRaw('region, COUNT(*) as total')
            ->groupBy('region')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'region');

        return view('livewire.admin.dashboard', [
            'totalMerchants' => Merchant::count(),
            'pendingKyc' => Merchant::whereIn('kyc_status', [Merchant::KYC_PENDING, Merchant::KYC_UNDER_REVIEW])->count(),
            'approvedMerchants' => Merchant::where('kyc_status', Merchant::KYC_APPROVED)->count(),
            'flaggedPayments' => PaymentRecord::where('status', PaymentRecord::STATUS_FLAGGED)->count(),
            'pendingPayments' => PaymentRecord::where('status', PaymentRecord::STATUS_RECORDED)->count(),
            'paymentTrendLabels' => $days->map(fn ($d) => Carbon::parse($d)->format('d M'))->all(),
            'paymentTrendValues' => $days->map(fn ($d) => (float) ($paymentsByDay[$d] ?? 0))->all(),
            'kycLabels' => $kycCounts->keys()->map(fn ($k) => str_replace('_', ' ', ucfirst($k)))->all(),
            'kycValues' => $kycCounts->values()->all(),
            'regionLabels' => $regionCounts->keys()->all(),
            'regionValues' => $regionCounts->values()->all(),
            'recentActivity' => Activity::with('causer')->latest()->limit(6)->get(),
        ]);
    }
}
