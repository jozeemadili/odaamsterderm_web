<?php

namespace App\Livewire\Admin\Kyc;

use App\Models\Merchant;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'KYC Review'])]
class Index extends Component
{
    public function render()
    {
        $queue = Merchant::whereIn('kyc_status', [Merchant::KYC_PENDING, Merchant::KYC_UNDER_REVIEW])
            ->withCount('kycDocuments')
            ->oldest()
            ->get();

        return view('livewire.admin.kyc.index', compact('queue'));
    }
}
