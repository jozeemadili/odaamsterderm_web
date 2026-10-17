<?php

namespace App\Livewire\Portal\Branding;

use App\Models\Merchant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal', ['title' => 'Branding'])]
class Index extends Component
{
    use WithFileUploads;

    public string $brand_color = '';

    public $logo;

    public function mount(): void
    {
        $this->brand_color = Auth::user()->merchant->brandColor();
    }

    public function saveColor(): void
    {
        $this->validate([
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Auth::user()->merchant->update(['brand_color' => strtoupper($this->brand_color)]);
        session()->flash('status', 'Brand color updated.');
    }

    public function resetColor(): void
    {
        Auth::user()->merchant->update(['brand_color' => null]);
        $this->brand_color = Merchant::DEFAULT_BRAND_COLOR;
        session()->flash('status', 'Reverted to the DukaFlow default color.');
    }

    public function saveLogo(): void
    {
        $this->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        $merchant = Auth::user()->merchant;
        $merchant->addMedia($this->logo->getRealPath())
            ->usingFileName($this->logo->getClientOriginalName())
            ->toMediaCollection('logo');

        $this->reset('logo');
        session()->flash('status', 'Logo uploaded.');
    }

    public function removeLogo(): void
    {
        Auth::user()->merchant->clearMediaCollection('logo');
        session()->flash('status', 'Logo removed.');
    }

    public function render()
    {
        return view('livewire.portal.branding.index', [
            'merchant' => Auth::user()->merchant,
        ]);
    }
}
