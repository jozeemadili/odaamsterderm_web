<?php

namespace App\Livewire\Portal\Transfers;

use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Stock Transfer'])]
class Show extends Component
{
    public StockTransfer $transfer;

    public function mount(StockTransfer $transfer): void
    {
        abort_unless($transfer->canBeSeenBy(), 403);
        $this->transfer = $transfer;
    }

    public function receive(StockTransferService $service): void
    {
        $this->transfer = $service->receive($this->transfer, Auth::user());
        session()->flash('status', "Transfer {$this->transfer->number} received. The stock is now in {$this->transfer->toBranch->name}.");
    }

    public function cancel(StockTransferService $service): void
    {
        $this->transfer = $service->cancel($this->transfer, Auth::user());
        session()->flash('status', "Transfer {$this->transfer->number} cancelled. The stock is back in {$this->transfer->fromBranch->name}.");
    }

    public function render()
    {
        return view('livewire.portal.transfers.show', [
            'lines' => $this->transfer->items()->with(['fromItem', 'toItem'])->get(),
        ]);
    }
}
