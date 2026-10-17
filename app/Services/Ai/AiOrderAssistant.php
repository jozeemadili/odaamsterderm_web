<?php

namespace App\Services\Ai;

use App\Models\AiRequest;
use App\Models\InventoryItem;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\MarketplaceSearch;
use App\Support\GeoPoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "Use AI to place order": a short chat in which the assistant works out
 * what the customer needs and proposes a basket of live offers (product +
 * store + quantity). It never orders anything itself — the customer adds
 * the basket to the cart and checks out through the normal cart, which
 * re-checks stock and reserves it.
 */
class AiOrderAssistant
{
    public const MAX_MESSAGE_LENGTH = 500;

    /** Conversation turns sent to the model (older ones are dropped). */
    public const MAX_HISTORY = 12;

    public const MAX_LINES = 10;

    /** Most offers described to the model in one request. */
    private const OFFER_LIMIT = 300;

    public const STATUS_OK = 'ok';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_LIMITED = 'limited';

    public function __construct(
        private readonly AiClient $ai,
        private readonly MarketplaceSearch $search,
        private readonly AiUsage $usage,
    ) {}

    /** Whether the admin has switched the "Use AI to place order" button on. */
    public static function enabled(): bool
    {
        return (bool) PlatformSetting::get(PlatformSetting::AI_ORDER_ENABLED);
    }

    /**
     * @param  list<array{role:string, content:string}>  $history  the conversation, ending with the customer's message
     * @return array{status:string, reply:string, basket:list<array<string, mixed>>}
     */
    public function reply(User $customer, array $history, ?GeoPoint $from = null, ?string $ip = null): array
    {
        $messages = $this->cleanHistory($history);
        $question = $messages ? end($messages)['content'] : '';

        if (! static::enabled() || $question === '') {
            return $this->result(self::STATUS_UNAVAILABLE);
        }
        if (! $this->usage->attempt(AiRequest::FEATURE_ORDER, $customer, $ip)) {
            return $this->result(self::STATUS_LIMITED);
        }

        $offers = $this->search->availableOffers($from, self::OFFER_LIMIT)->keyBy('id');
        $started = microtime(true);

        try {
            $response = $this->ai->structured($this->systemPrompt($offers, $from, $customer), $messages, $this->schema(), 6000);
        } catch (AiUnavailableException $e) {
            $this->usage->recordFailure(AiRequest::FEATURE_ORDER, $question, $customer, $ip, $started, $e->getMessage());

            return $this->result(self::STATUS_UNAVAILABLE);
        }

        $basket = $response->refused ? [] : $this->checkBasket($response->data['basket'] ?? [], $offers);
        $this->usage->record(AiRequest::FEATURE_ORDER, $question, $customer, $ip, $started, $response, count($basket));

        if ($response->refused) {
            return $this->result(self::STATUS_REFUSED);
        }

        return $this->result(self::STATUS_OK, Str::limit(trim((string) ($response->data['reply'] ?? '')), 1500), $basket);
    }

    /**
     * Keeps only lines for offers the customer can order now, merges
     * repeats and caps quantities at the units the store has online.
     *
     * @param  array<int, mixed>  $lines  [{offer_id, quantity}]
     * @param  Collection<int, InventoryItem>  $offers  orderable offers keyed by id
     * @return list<array<string, mixed>>
     */
    public function checkBasket(array $lines, Collection $offers): array
    {
        $basket = [];

        foreach ($lines as $line) {
            $offer = $offers->get((int) ($line['offer_id'] ?? 0));
            $quantity = round((float) ($line['quantity'] ?? 0), 2);

            if (! $offer || $quantity <= 0) {
                continue;
            }

            $quantity += $basket[$offer->id]['quantity'] ?? 0;
            $available = (float) $offer->marketplace_quantity;

            $basket[$offer->id] = [
                'offer_id' => $offer->id,
                'product_slug' => $offer->product->slug,
                'name' => $offer->product->displayName(),
                'store' => $offer->branch->name,
                'seller' => $offer->merchant->business_name,
                'unit' => $offer->unit,
                'unit_price' => (float) $offer->unit_price,
                'quantity' => min($quantity, $available),
                'available' => $available,
                'distance' => GeoPoint::formatKm($offer->distance_km !== null ? (float) $offer->distance_km : null),
                'note' => $quantity > $available ? 'Only '.rtrim(rtrim(number_format($available, 2, '.', ''), '0'), '.')." {$offer->unit} available here." : null,
            ];

            if (count($basket) >= self::MAX_LINES) {
                break;
            }
        }

        return array_values($basket);
    }

    /** How a proposed basket is remembered in the conversation for the next turn. */
    public static function basketMemo(array $basket): string
    {
        if (! $basket) {
            return '';
        }

        return "\n\n[Basket I proposed: ".collect($basket)
            ->map(fn ($l) => "offer {$l['offer_id']} {$l['name']} at {$l['store']} x {$l['quantity']}")
            ->implode('; ').']';
    }

    /**
     * @param  list<array{role:string, content:string}>  $history
     * @return list<array{role:string, content:string}>
     */
    protected function cleanHistory(array $history): array
    {
        $messages = collect($history)
            ->filter(fn ($m) => in_array($m['role'] ?? null, ['user', 'assistant'], true) && trim((string) ($m['content'] ?? '')) !== '')
            ->map(fn ($m) => ['role' => $m['role'], 'content' => Str::limit(trim((string) $m['content']), $m['role'] === 'user' ? self::MAX_MESSAGE_LENGTH : 3000, '')])
            ->take(-self::MAX_HISTORY)
            ->values();

        // The conversation must start and end with the customer.
        while ($messages->isNotEmpty() && $messages->first()['role'] !== 'user') {
            $messages->shift();
        }

        return $messages->isNotEmpty() && $messages->last()['role'] === 'user' ? $messages->all() : [];
    }

    /** @param  Collection<int, InventoryItem>  $offers */
    protected function systemPrompt(Collection $offers, ?GeoPoint $from, User $customer): string
    {
        $lines = $offers->map(fn (InventoryItem $o) => implode(' | ', [
            $o->id,
            $o->product->displayName(),
            $o->product->brand ?: '-',
            $o->product->animalType?->name ?: '-',
            $o->product->category?->fullName() ?: '-',
            $o->branch->name.' ('.$o->merchant->business_name.')',
            'TZS '.number_format((float) $o->unit_price).' per '.$o->unit,
            ((float) $o->marketplace_quantity).' '.$o->unit,
            $o->distance_km !== null ? round((float) $o->distance_km, 1).' km' : '-',
        ]))->implode("\n");

        $location = $from ? 'The customer has set a location; distances below are from it.' : 'The customer has not set a location, so distances are unknown.';

        return <<<PROMPT
        You are the ordering assistant of Odaamsterderm Hub, a Tanzanian marketplace for animal products (feeds, veterinary medicines, vaccines, supplements, equipment). Customers collect orders from the store (pickup); there is no delivery.

        Help the customer build a basket from the offers below. Each offer is one product at one store. Reply in the customer's language (Kiswahili if they write Kiswahili), briefly and warmly.

        How to work:
        - If you need to know something to choose well (which animal, how many, their age, how long it should last, brand, cheapest or nearest), ask one or two short questions and return an empty basket.
        - When you know enough, propose a basket: offer ids and quantities. Estimate quantities from what they said (number of animals, days, pack sizes) and explain the estimate in one sentence. Prefer as few stores as possible because each store is a separate pickup. Follow their preference for cheapest or nearest; otherwise balance price and distance.
        - Only use offer ids from the list, and never more units than the list shows. If something is not available, say so and suggest the closest alternative from the list.
        - When the customer changes their mind, return the complete updated basket (not only the change). An empty basket means "no basket yet".
        - You cannot place orders. When the basket is ready, tell them to check it and press "Add to cart" to review and confirm the order.
        - Never invent prices, stock, stores or products. For sick animals give general guidance only, suggest a veterinarian, and never prescribe doses.
        - Politely decline anything unrelated to animals, farming or this marketplace.

        The customer is {$customer->name}. {$location}

        Offers (offer id | product | brand | animal | category | store (seller) | price | units online | distance):
        {$lines}
        PROMPT;
    }

    /** @return array<string, mixed> */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string', 'description' => 'Message to the customer'],
                'basket' => [
                    'type' => 'array',
                    'description' => 'The complete proposed basket; empty while asking questions',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'offer_id' => ['type' => 'integer'],
                            'quantity' => ['type' => 'number'],
                        ],
                        'required' => ['offer_id', 'quantity'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['reply', 'basket'],
            'additionalProperties' => false,
        ];
    }

    protected function result(string $status, string $reply = '', array $basket = []): array
    {
        return ['status' => $status, 'reply' => $reply, 'basket' => $basket];
    }
}
