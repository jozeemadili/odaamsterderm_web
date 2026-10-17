<?php

namespace App\Services\Ai;

use App\Models\AiRequest;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\MarketplaceSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Ask AI" search: turns a customer's plain-language need (English or
 * Kiswahili) into a ranked list of catalog products that are on the
 * marketplace. The model only chooses from the live product list we send
 * it — prices, stock and distance always come from MarketplaceSearch.
 */
class AiProductSearch
{
    public const MAX_QUERY_LENGTH = 300;

    public const MAX_RESULTS = 12;

    /** Most products described to the model in one request. */
    private const CATALOG_LIMIT = 400;

    public const STATUS_OK = 'ok';

    public const STATUS_OFF_TOPIC = 'off_topic';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_LIMITED = 'limited';

    public function __construct(
        private readonly AiClient $ai,
        private readonly MarketplaceSearch $search,
        private readonly AiUsage $usage,
    ) {}

    /** Whether the admin has switched Ask AI on (the button is shown). */
    public static function enabled(): bool
    {
        return (bool) PlatformSetting::get(PlatformSetting::AI_SEARCH_ENABLED);
    }

    public static function availableTo(?User $user): bool
    {
        return static::enabled() && ($user !== null || (bool) PlatformSetting::get(PlatformSetting::AI_SEARCH_GUESTS));
    }

    /**
     * @return array{status:string, summary:string, product_ids:list<int>, in_stock:bool, sort:string}
     */
    public function search(string $query, ?User $user = null, ?string $ip = null): array
    {
        $query = Str::limit(trim(preg_replace('/\s+/', ' ', $query)), self::MAX_QUERY_LENGTH, '');

        if ($query === '' || ! static::availableTo($user)) {
            return $this->result(self::STATUS_UNAVAILABLE);
        }

        $cacheKey = 'ai-search:'.md5(Str::lower($query));
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        if (! $this->usage->attempt(AiRequest::FEATURE_SEARCH, $user, $ip)) {
            return $this->result(self::STATUS_LIMITED);
        }

        $catalog = $this->catalog();
        $started = microtime(true);

        try {
            $response = $this->ai->structured($this->systemPrompt($catalog), $query, $this->schema(), 4000);
        } catch (AiUnavailableException $e) {
            $this->usage->recordFailure(AiRequest::FEATURE_SEARCH, $query, $user, $ip, $started, $e->getMessage());

            return $this->result(self::STATUS_UNAVAILABLE);
        }

        $data = $response->data;
        $ids = collect($data['product_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $catalog->has($id))
            ->unique()->take(self::MAX_RESULTS)->values()->all();

        $status = match (true) {
            $response->refused => self::STATUS_REFUSED,
            ! ($data['on_topic'] ?? true) => self::STATUS_OFF_TOPIC,
            default => self::STATUS_OK,
        };
        $sort = $data['sort'] ?? MarketplaceSearch::SORT_RELEVANCE;

        $result = $this->result(
            $status,
            $status === self::STATUS_REFUSED ? '' : Str::limit(trim((string) ($data['summary'] ?? '')), 600),
            $status === self::STATUS_OK ? $ids : [],
            (bool) ($data['in_stock_only'] ?? false),
            array_key_exists($sort, MarketplaceSearch::SORTS) || $sort === MarketplaceSearch::SORT_RELEVANCE ? $sort : MarketplaceSearch::SORT_RELEVANCE,
        );

        $this->usage->record(AiRequest::FEATURE_SEARCH, $query, $user, $ip, $started, $response, count($result['product_ids']));

        // Same question, same answer for a while — saves cost on reloads and paging.
        Cache::put($cacheKey, $result, now()->addMinutes(30));

        return $result;
    }

    /**
     * Meaningful words of a question, for a keyword fallback when there is
     * no AI answer (switched off, unavailable or busy).
     *
     * @return list<string>
     */
    public static function fallbackWords(string $question): array
    {
        return collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($question)))
            ->filter(fn (string $w) => mb_strlen($w) >= 4)->unique()->take(8)->values()->all();
    }

    /** Products customers can buy now, keyed by id, with live "from" price and units. */
    protected function catalog(): Collection
    {
        return collect($this->search->products([], null, MarketplaceSearch::SORT_NAME, self::CATALOG_LIMIT)->items())
            ->keyBy('id');
    }

    protected function systemPrompt(Collection $catalog): string
    {
        $lines = $catalog->map(fn (Product $p) => implode(' | ', [
            $p->id,
            $p->displayName(),
            $p->brand ?: '-',
            $p->animalType?->name ?: '-',
            $p->category?->fullName() ?: '-',
            $p->min_price !== null ? 'from TZS '.number_format((float) $p->min_price) : 'out of stock',
            ((float) $p->available_quantity).' '.($p->unit ?? 'units').' available',
        ]))->implode("\n");

        return <<<PROMPT
        You are the shopping assistant of Odaamsterderm Hub, a Tanzanian marketplace for animal products: feeds, veterinary medicines, vaccines, supplements and farm equipment for poultry, cattle, goats, pigs, pets and other animals.

        A customer describes what they need, in English or Kiswahili. Pick the products from the catalog below that best meet the need, most relevant first, at most 12. Only use ids from the catalog. If nothing in the catalog fits, return an empty list and say so in the summary, suggesting what kind of product to look for instead.

        Write "summary" in the customer's language (Kiswahili if they wrote Kiswahili), 1-3 short sentences: why these products fit and, when the customer gives numbers (animals, weeks, weight), a rough quantity estimate. Never invent prices, stock or stores; the page shows live prices next to your picks. For animal health problems give general guidance only and advise consulting a veterinarian; do not prescribe doses.

        When the customer states a budget, prefer products whose price fits it. Set "in_stock_only" true when they need it now or today. Set "sort" to "price" when they ask for cheap or cheapest, "nearest" when they ask for near or nearby, otherwise "relevance". Set "on_topic" false (with an empty product list and a polite one-sentence summary) when the request has nothing to do with animals, farming or this marketplace.

        Catalog (id | name | brand | animal | category | price | stock):
        {$lines}
        PROMPT;
    }

    /** @return array<string, mixed> */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Catalog ids, most relevant first'],
                'summary' => ['type' => 'string'],
                'in_stock_only' => ['type' => 'boolean'],
                'sort' => ['type' => 'string', 'enum' => [MarketplaceSearch::SORT_RELEVANCE, MarketplaceSearch::SORT_PRICE, MarketplaceSearch::SORT_NEAREST]],
                'on_topic' => ['type' => 'boolean'],
            ],
            'required' => ['product_ids', 'summary', 'in_stock_only', 'sort', 'on_topic'],
            'additionalProperties' => false,
        ];
    }

    /** @param  list<int>  $ids */
    protected function result(string $status, string $summary = '', array $ids = [], bool $inStock = false, string $sort = MarketplaceSearch::SORT_RELEVANCE): array
    {
        return [
            'status' => $status,
            'summary' => $summary,
            'product_ids' => $ids,
            'in_stock' => $inStock,
            'sort' => $sort,
        ];
    }
}
