<?php

namespace App\Livewire\Market;

use App\Services\Ai\AiOrderAssistant;
use App\Services\OrderService;
use App\Support\MarketLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Use AI to place order" chat panel. The assistant proposes a basket; the
 * customer adjusts it and adds it to the cart, then confirms the order in
 * the cart as usual.
 */
class AiAssistant extends Component
{
    public string $input = '';

    /** @var list<array{role:string, content:string, memo?:string}> role: user|assistant|notice */
    #[Locked]
    public array $messages = [];

    /** @var list<array<string, mixed>> lines from AiOrderAssistant::checkBasket() */
    #[Locked]
    public array $basket = [];

    /** @var array<int, string> offer id → quantity the customer may adjust */
    public array $qty = [];

    #[Locked]
    public bool $waiting = false;

    public function ask(?string $text = null): void
    {
        if ($text !== null) {
            $this->input = $text;
        }

        if (! $this->canChat() || $this->waiting) {
            return;
        }

        $this->validate(['input' => 'required|string|max:'.AiOrderAssistant::MAX_MESSAGE_LENGTH], [], ['input' => 'message']);

        $this->messages[] = ['role' => 'user', 'content' => trim($this->input)];
        $this->input = '';
        $this->waiting = true;

        // Show the customer's message at once, then fetch the answer.
        $this->dispatch('ai-scroll');
        $this->js('$wire.respond()');
    }

    public function respond(AiOrderAssistant $assistant): void
    {
        if (! $this->waiting || ! $this->canChat()) {
            return;
        }

        $history = collect($this->messages)
            ->whereIn('role', ['user', 'assistant'])
            ->map(fn ($m) => ['role' => $m['role'], 'content' => $m['content'].($m['memo'] ?? '')])
            ->values()->all();

        $result = $assistant->reply(Auth::user(), $history, MarketLocation::current(), request()->ip());
        $this->waiting = false;
        $this->dispatch('ai-scroll');

        if ($result['status'] !== AiOrderAssistant::STATUS_OK) {
            $this->messages[] = ['role' => 'notice', 'content' => match ($result['status']) {
                AiOrderAssistant::STATUS_LIMITED => 'The assistant is busy right now. Please try again in a minute.',
                AiOrderAssistant::STATUS_REFUSED => 'The assistant can\'t help with that. Tell it which animal products you need.',
                default => 'The assistant is not available right now. You can still search and add products to your cart yourself.',
            }];

            return;
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $result['reply'], 'memo' => AiOrderAssistant::basketMemo($result['basket'])];

        if ($result['basket']) {
            $this->basket = $result['basket'];
            $this->qty = collect($result['basket'])->mapWithKeys(fn ($l) => [$l['offer_id'] => (string) $l['quantity']])->all();
        }
    }

    public function removeLine(int $offerId): void
    {
        $this->basket = array_values(array_filter($this->basket, fn ($l) => $l['offer_id'] !== $offerId));
        unset($this->qty[$offerId]);
    }

    /** Puts the basket in the cart; the customer reviews and confirms the order there. */
    public function addToCart(OrderService $orders)
    {
        if (! $this->canChat() || ! $this->basket) {
            return null;
        }

        $problems = [];
        $added = 0;
        foreach ($this->basket as $line) {
            $quantity = (float) ($this->qty[$line['offer_id']] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            try {
                $orders->addToCart(Auth::user(), $line['offer_id'], $quantity);
                $added++;
            } catch (ValidationException $e) {
                $problems[] = $line['name'].': '.collect($e->errors())->flatten()->first();
            }
        }

        $this->dispatch('cart-updated');

        if ($problems) {
            $this->addError('basket', implode(' ', $problems));
            $this->messages[] = ['role' => 'notice', 'content' => $added
                ? "Added {$added} item(s) to your cart. Some could not be added — see below."
                : 'Nothing could be added — see below.'];

            return null;
        }

        session()->flash('status', 'The AI basket is in your cart. Check it and confirm to place your order.');

        return $this->redirectRoute('market.cart');
    }

    public function startOver(): void
    {
        $this->reset(['messages', 'basket', 'qty', 'input', 'waiting']);
        $this->resetErrorBag();
    }

    protected function canChat(): bool
    {
        return AiOrderAssistant::enabled() && Auth::user()?->isCustomer();
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.market.ai-assistant', [
            'state' => match (true) {
                ! $user => 'guest',
                ! $user->isCustomer() => 'not_customer',
                default => 'ready',
            },
            'total' => collect($this->basket)->sum(fn ($l) => (float) ($this->qty[$l['offer_id']] ?? 0) * $l['unit_price']),
            'stores' => collect($this->basket)->pluck('store')->unique()->count(),
        ]);
    }
}
