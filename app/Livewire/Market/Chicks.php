<?php

namespace App\Livewire\Market;

use App\Models\HatchSchedule;
use App\Services\ChickService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Day-old chicks for customers, in four steps: chick type → hatchery →
 * hatch date → boxes and pickup store.
 */
#[Layout('layouts.market', ['title' => 'Day-old chicks'])]
class Chicks extends Component
{
    #[Url]
    public string $type = '';

    #[Url]
    public string $hatchery = '';

    #[Url(as: 'date')]
    public ?int $scheduleId = null;

    public int $boxes = 1;

    public string $storeId = '';

    public string $phone = '';

    public string $note = '';

    public function mount(ChickService $chicks): void
    {
        $this->phone = (string) Auth::user()?->phone;
        $this->storeId = (string) ($chicks->pickupStores()->first()?->id ?? '');
    }

    public function chooseType(string $code): void
    {
        $this->type = $code;
        $this->reset(['hatchery', 'scheduleId']);
    }

    public function chooseHatchery(string $slug): void
    {
        $this->hatchery = $slug;
        $this->scheduleId = null;
    }

    public function chooseDate(int $id): void
    {
        $this->scheduleId = $id;
        $this->boxes = 1;
        $this->resetErrorBag();
    }

    public function back(): void
    {
        match (true) {
            $this->scheduleId !== null => $this->scheduleId = null,
            $this->hatchery !== '' => $this->hatchery = '',
            default => $this->type = '',
        };
    }

    public function book(ChickService $chicks)
    {
        $user = Auth::user();

        if (! $user) {
            session()->put('url.intended', url()->previous());

            return $this->redirectRoute('login');
        }

        if (! $user->isCustomer()) {
            $this->addError('book', 'Sign in with a customer account to book chicks.');

            return null;
        }

        $this->validate([
            'boxes' => ['required', 'integer', 'min:1', 'max:1000'],
            'storeId' => ['required', 'integer'],
            'phone' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['storeId' => 'pickup store']);

        try {
            $order = $chicks->placeOrder($user, (int) $this->scheduleId, $this->boxes, (int) $this->storeId, $this->phone, $this->note);
        } catch (ValidationException $e) {
            $this->addError('book', collect($e->errors())->flatten()->first());

            return null;
        }

        session()->flash('status', "Chicks booked — order {$order->reference}. The store will tell you when they are ready to collect.");

        return $this->redirectRoute('market.order', $order);
    }

    public function render(ChickService $chicks)
    {
        $types = collect($chicks->catalogue());
        $type = $types->firstWhere('code', $this->type);
        $hatchery = $type ? collect($type['hatcheries'])->firstWhere('slug', $this->hatchery) : null;
        $date = $hatchery && $this->scheduleId ? collect($hatchery['dates'])->firstWhere('id', $this->scheduleId) : null;

        return view('livewire.market.chicks', [
            'types' => $types,
            'chosenType' => $type,
            'chosenHatchery' => $hatchery,
            'chosenDate' => $date,
            'stores' => $chicks->pickupStores(),
            'boxSize' => HatchSchedule::boxSize(),
            'sw' => app()->getLocale() === 'sw',
        ]);
    }
}
