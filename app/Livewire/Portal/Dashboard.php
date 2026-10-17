<?php

namespace App\Livewire\Portal;

use App\Services\DashboardMetricsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public string $period = 'month';

    public function setPeriod(string $period): void
    {
        if (array_key_exists($period, DashboardMetricsService::PERIOD_LABELS)) {
            $this->period = $period;
        }
    }

    public function render()
    {
        $merchant = Auth::user()->merchant;
        $summary = app(DashboardMetricsService::class)->summary($merchant, $this->period);

        return view('livewire.portal.dashboard', [
            'merchant' => $merchant,
            'summary' => $summary,
            'lowStockItems' => $merchant->lowStockItems()->limit(5)->get(),
            'expiringSoonItems' => $merchant->expiringSoonItems()->orderBy('expiry_date')->limit(5)->get(),
            'recentPayments' => $merchant->paymentRecords()->latest('payment_date')->limit(5)->get(),
        ]);
    }
}
