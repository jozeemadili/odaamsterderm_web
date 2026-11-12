<?php

namespace App\Livewire\Market;

use App\Models\AnimalType;
use App\Models\Producer;
use App\Models\ProductCategory;
use App\Services\Ai\AiProductSearch;
use App\Services\MarketplaceSearch;
use App\Support\MarketLocation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Public marketplace: search, filter and sort animal products near you. */
#[Layout('layouts.market')]
class Home extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    /** Plain-language "Ask AI" question; the AI answer is fetched after first render. */
    #[Url]
    public string $ask = '';

    /** @var array{status:string, summary:string, product_ids:list<int>, in_stock:bool, sort:string}|null */
    #[Locked]
    public ?array $ai = null;

    #[Locked]
    public string $aiFor = '';

    /**
     * Earlier turns of the Ask AI conversation, oldest first; the current
     * question is $ask and its answer $ai.
     *
     * @var list<array{question:string, answer:string}>
     */
    public array $aiThread = [];

    /** The follow-up question being typed under the AI answer. */
    public string $followUp = '';

    #[Url]
    public string $animal = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $brand = '';

    #[Url]
    public string $seller = '';

    #[Url]
    public string $sort = MarketplaceSearch::SORT_NEAREST;

    #[Url(as: 'in_stock')]
    public bool $inStock = false;

    #[Url(as: 'min')]
    public string $minPrice = '';

    #[Url(as: 'max')]
    public string $maxPrice = '';

    public function updating(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function updatedAsk(): void
    {
        // A new question from the search bar starts a new conversation.
        $this->ai = null;
        $this->aiFor = '';
        $this->aiThread = [];
    }

    /** Continues the Ask AI conversation with a follow-up question. */
    public function askFollowUp(): void
    {
        $this->validate(['followUp' => 'required|string|max:'.AiProductSearch::MAX_QUERY_LENGTH], [], ['followUp' => 'question']);

        if ($this->ai && trim((string) ($this->ai['summary'] ?? '')) !== '') {
            $this->aiThread[] = ['question' => trim($this->ask), 'answer' => $this->ai['summary']];
            $this->aiThread = array_slice($this->aiThread, -AiProductSearch::MAX_TURNS);
        }

        $this->ask = trim($this->followUp);
        $this->followUp = '';
        $this->ai = null;
        $this->aiFor = '';
        $this->resetPage();

        // Show the question at once; the answer follows.
        $this->js('$wire.runAi()');
    }

    /** Runs the AI search for the current question (called by wire:init so the page shows at once). */
    public function runAi(AiProductSearch $aiSearch): void
    {
        $ask = trim($this->ask);
        if ($ask === '' || $this->aiFor === $ask || ! AiProductSearch::enabled()) {
            return;
        }

        if (! AiProductSearch::availableTo(Auth::user())) {
            $this->ai = ['status' => 'sign_in', 'summary' => '', 'product_ids' => [], 'in_stock' => false, 'sort' => MarketplaceSearch::SORT_RELEVANCE];
        } else {
            $this->ai = $aiSearch->search($ask, Auth::user(), request()->ip(), $this->aiThread);
            $this->sort = $this->ai['sort'];
            $this->inStock = $this->inStock || $this->ai['in_stock'];
        }

        $this->aiFor = $ask;
        $this->resetPage();
    }

    public function clearAi(): void
    {
        $this->reset(['ask', 'ai', 'aiFor', 'aiThread', 'followUp']);
        $this->sort = MarketLocation::current() ? MarketplaceSearch::SORT_NEAREST : MarketplaceSearch::SORT_PRICE;
        $this->resetPage();
    }

    public function chooseAnimal(?int $id): void
    {
        $this->animal = $id ? (string) $id : '';
        $this->category = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['animal', 'category', 'brand', 'seller', 'inStock', 'minPrice', 'maxPrice']);
        $this->resetPage();
    }

    #[On('market-location-updated')]
    public function locationUpdated(): void
    {
        // A freshly chosen location is what customers want ranked by.
        $this->sort = MarketLocation::current() ? MarketplaceSearch::SORT_NEAREST : MarketplaceSearch::SORT_PRICE;
        $this->resetPage();
    }

    public function render(MarketplaceSearch $search)
    {
        $from = MarketLocation::current();

        // Ask AI: pending until runAi() answers; the AI only picks product ids.
        $askAi = $this->ask !== '' && AiProductSearch::enabled();
        $aiPending = $askAi && $this->aiFor !== trim($this->ask);
        $aiStatus = $askAi && ! $aiPending ? $this->ai['status'] : null;
        $aiFallback = in_array($aiStatus, [AiProductSearch::STATUS_UNAVAILABLE, AiProductSearch::STATUS_LIMITED], true);

        if (! $askAi && $this->sort === MarketplaceSearch::SORT_RELEVANCE) {
            $this->sort = MarketplaceSearch::SORT_PRICE;
        }

        // "Nearest" means nothing without a location — show what's really applied.
        if (! $from && $this->sort === MarketplaceSearch::SORT_NEAREST) {
            $this->sort = MarketplaceSearch::SORT_PRICE;
        }

        $filters = [
            'search' => $this->q,
            'animal_type_id' => $this->animal,
            'category_id' => $this->category,
            'brand' => $this->brand,
            'merchant_id' => $this->seller,
            'in_stock' => $this->inStock,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
        ];

        if ($askAi && ! $aiFallback) {
            $filters['product_ids'] = $aiPending ? [] : $this->ai['product_ids'];
        }

        // No AI answer (switched off, unavailable, busy): match any meaningful word of the question.
        if ($this->ask !== '' && (! $askAi || $aiFallback)) {
            $filters['any_words'] = AiProductSearch::fallbackWords($this->ask);
        }

        $hasFilters = $this->ask !== '' || $this->q !== '' || array_filter([$this->animal, $this->category, $this->brand, $this->seller, $this->minPrice, $this->maxPrice]) || $this->inStock;

        // Amsterdam Group's own catalogue, featured above everything else,
        // then a partner range (Silverlands) in a block of its own.
        $featuredBrand = Producer::where('code', config('hub.catalogue_producer'))->value('name');
        $featured = $featuredBrand && ! $hasFilters
            ? $search->products(['brand' => $featuredBrand], $from, MarketplaceSearch::SORT_NAME, 8, 'featured_page')
            : null;
        $partnerBrand = Producer::where('code', config('hub.partner_producer'))->where('on_marketplace', true)->value('name');
        $partner = $partnerBrand && ! $hasFilters
            ? $search->products(['brand' => $partnerBrand], $from, MarketplaceSearch::SORT_NAME, 4, 'partner_page')
            : null;
        $featuredItems = $featured?->getCollection() ?? collect();

        return view('livewire.market.home', [
            'from' => $from,
            'featured' => $featuredItems,
            // The hero's product: a featured one customers can see and buy.
            'spotlight' => $featuredItems->first(fn ($p) => $p->image() && $p->min_price !== null),
            'partner' => $partner?->getCollection() ?? collect(),
            'partnerTotal' => $partner?->total() ?? 0,
            'partnerBrand' => $partnerBrand,
            'featuredTotal' => $featured?->total() ?? 0,
            'featuredBrand' => $featuredBrand,
            'products' => $search->products($filters, $from, $this->sort, 24),
            'stores' => $hasFilters ? collect() : $search->stores($from, 6),
            'animalGroups' => AnimalType::active()->whereNull('parent_id')->with(['children' => fn ($q) => $q->active()])->orderBy('sort_order')->get(),
            'categories' => ProductCategory::active()->whereNull('parent_id')
                ->withProductsFor($this->animal ? (int) $this->animal : null)
                ->with(['children' => fn ($q) => $q->active()->withProductsFor($this->animal ? (int) $this->animal : null)])
                ->orderBy('sort_order')->get(),
            'brands' => $search->brands(),
            'sellers' => $search->sellers(),
            'hasFilters' => $hasFilters,
            'askAi' => $askAi,
            'aiPending' => $aiPending,
            'aiStatus' => $aiStatus,
        ])->title($this->ask !== '' ? 'Ask AI: '.$this->ask : ($this->q !== '' ? "Search: {$this->q}" : 'Marketplace'));
    }
}
