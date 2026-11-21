<?php

namespace App\Livewire\Market;

use App\Models\HatchSchedule;
use App\Services\CareSuggestions;
use App\Services\ChickService;
use App\Support\MarketLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Day-old chicks for customers, in four steps: chick type → agent near
 * them → hatchery and the day chicks arrive → boxes and pickup store.
 */
#[Layout('layouts.market', ['title' => 'Day-old chicks'])]
class Chicks extends Component
{
    #[Url]
    public string $type = '';

    #[Url]
    public string $agent = '';

    #[Url(as: 'day')]
    public ?int $scheduleId = null;

    public int $boxes = 1;

    public string $storeId = '';

    public string $phone = '';

    public string $note = '';

    /** Suggested products the customer ticked: offer id => true. Nothing is ticked for them. */
    public array $extras = [];

    public function mount(): void
    {
        $this->phone = (string) Auth::user()?->phone;
    }

    #[On('market-location-updated')]
    public function locationUpdated(): void {}

    public function chooseType(string $code): void
    {
        $this->type = $code;
        $this->reset(['agent', 'scheduleId']);
    }

    public function chooseAgent(int $id): void
    {
        $this->agent = (string) $id;
        $this->scheduleId = null;
    }

    public function chooseDay(int $id): void
    {
        $this->scheduleId = $id;
        $this->boxes = 1;
        $this->storeId = '';
        $this->extras = [];
        $this->resetErrorBag();
    }

    public function back(): void
    {
        match (true) {
            $this->scheduleId !== null => $this->scheduleId = null,
            $this->agent !== '' => $this->agent = '',
            default => $this->type = '',
        };
    }

    public function book(ChickService $chicks, CareSuggestions $care)
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

        // Ticked suggestions, at the quantity sized for the boxes being booked.
        $extras = collect($this->suggestions($care))
            ->filter(fn ($s) => ! empty($this->extras[$s['offer_id']]))
            ->mapWithKeys(fn ($s) => [$s['offer_id'] => $s['quantity']])
            ->all();

        try {
            $order = $chicks->placeOrder($user, (int) $this->scheduleId, $this->boxes, (int) $this->storeId, $this->phone, $this->note, $extras);
        } catch (ValidationException $e) {
            $this->addError('book', collect($e->errors())->flatten()->first());

            return null;
        }

        session()->flash('status', "Chicks booked — order {$order->reference}. The store will tell you when they are ready to collect.");

        return $this->redirectRoute('market.order', $order);
    }

    /** @return list<array<string, mixed>> */
    protected function suggestions(CareSuggestions $care): array
    {
        $day = $this->scheduleId ? HatchSchedule::with('chickType')->find($this->scheduleId) : null;

        return $day && $this->storeId !== '' && (int) $this->boxes > 0
            ? $care->forBooking($day, (int) $this->boxes, (int) $this->storeId)
            : [];
    }

    public function render(ChickService $chicks)
    {
        $from = MarketLocation::current();
        $types = collect($chicks->catalogue($from));
        $type = $types->firstWhere('code', $this->type);
        $agent = $type ? collect($type['agents'])->firstWhere('id', (int) $this->agent) : null;

        $day = null;
        $hatchery = null;
        if ($agent && $this->scheduleId) {
            foreach ($agent['hatcheries'] as $h) {
                if ($found = collect($h['dates'])->firstWhere('id', $this->scheduleId)) {
                    [$day, $hatchery] = [$found, $h];
                }
            }
        }
        if ($hatchery && ($this->storeId === '' || ! collect($hatchery['pickup_stores'])->contains('id', (int) $this->storeId))) {
            $this->storeId = (string) ($hatchery['pickup_stores'][0]['id'] ?? '');
        }

        $suggestions = $day && $this->storeId !== '' ? $this->suggestions(app(CareSuggestions::class)) : [];

        return view('livewire.market.chicks', [
            'suggestions' => $suggestions,
            'types' => $types,
            'chosenType' => $type,
            'chosenAgent' => $agent,
            'chosenHatchery' => $hatchery,
            'chosenDay' => $day,
            'boxSize' => HatchSchedule::boxSize(),
            'hasLocation' => $from !== null,
        ]);
    }
}
