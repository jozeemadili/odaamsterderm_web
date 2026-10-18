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

    public string $brand_accent_color = '';

    /** Ready-made pairs [main, accent] sellers can start from. */
    public const PALETTES = [
        'Navy & gold' => ['#01162F', '#C9972E'],
        'Forest & lime' => ['#14532D', '#84CC16'],
        'Ocean & coral' => ['#0C4A6E', '#F97316'],
        'Maroon & cream' => ['#7F1D1D', '#FDE68A'],
        'Earth & sky' => ['#78350F', '#38BDF8'],
        'Charcoal & red' => ['#1F2937', '#DC2626'],
    ];

    public $logo;

    public function mount(): void
    {
        $this->brand_color = Auth::user()->merchant->brandColor();
        $this->brand_accent_color = Auth::user()->merchant->accentColor();
    }

    public function usePalette(string $name): void
    {
        [$this->brand_color, $this->brand_accent_color] = self::PALETTES[$name] ?? [$this->brand_color, $this->brand_accent_color];
    }

    public function saveColor(): void
    {
        $this->validate([
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/', 'different:brand_color'],
        ], ['brand_accent_color.different' => 'Pick an accent that differs from the main colour.'], [
            'brand_color' => 'main colour', 'brand_accent_color' => 'accent colour',
        ]);

        Auth::user()->merchant->update([
            'brand_color' => strtoupper($this->brand_color),
            'brand_accent_color' => strtoupper($this->brand_accent_color),
        ]);
        session()->flash('status', 'Brand colours saved. Your portal, receipts, invoices and store page now use them.');

        $this->redirectRoute('portal.branding.index', navigate: false);
    }

    public function resetColor(): void
    {
        Auth::user()->merchant->update(['brand_color' => null, 'brand_accent_color' => null]);
        $this->brand_color = Merchant::DEFAULT_BRAND_COLOR;
        $this->brand_accent_color = Merchant::DEFAULT_ACCENT_COLOR;
        session()->flash('status', 'Reverted to the Odaamsterderm Hub colours.');

        $this->redirectRoute('portal.branding.index', navigate: false);
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
