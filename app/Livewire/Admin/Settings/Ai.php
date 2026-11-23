<?php

namespace App\Livewire\Admin\Settings;

use App\Models\AiRequest;
use App\Models\PlatformSetting;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiPricing;
use App\Services\Ai\DemoAiClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Switches for the customer "Ask AI" assistant, and its usage. */
#[Layout('layouts.admin', ['title' => 'AI Assistant'])]
class Ai extends Component
{
    use WithPagination;

    public bool $searchEnabled = false;

    public bool $orderEnabled = false;

    public bool $guestsAllowed = true;

    public int $dailyLimit = 500;

    public bool $showPrices = false;

    /** Shillings per US dollar for the cost figures. */
    public string $usdRate = '';

    /** Recent requests filters. */
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $feature = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('manage-platform-settings'), 403);

        $this->searchEnabled = (bool) PlatformSetting::get(PlatformSetting::AI_SEARCH_ENABLED);
        $this->orderEnabled = (bool) PlatformSetting::get(PlatformSetting::AI_ORDER_ENABLED);
        $this->guestsAllowed = (bool) PlatformSetting::get(PlatformSetting::AI_SEARCH_GUESTS);
        $this->dailyLimit = (int) PlatformSetting::get(PlatformSetting::AI_DAILY_LIMIT);
        $this->showPrices = PlatformSetting::showPrices();
        $this->usdRate = (string) PlatformSetting::get(PlatformSetting::AI_USD_TZS);
    }

    public function saveRate(): void
    {
        $this->validate(['usdRate' => 'required|numeric|min:100|max:100000'], [], ['usdRate' => 'exchange rate']);
        $this->store(PlatformSetting::AI_USD_TZS, (float) $this->usdRate);
        session()->flash('status', 'Exchange rate saved: 1 USD = '.number_format((float) $this->usdRate).' TZS.');
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['from', 'to', 'search', 'feature', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['from', 'to', 'search', 'feature', 'status']);
        $this->resetPage();
    }

    public function updatedShowPrices(bool $value): void
    {
        $this->store(PlatformSetting::MARKET_SHOW_PRICES, $value);
        session()->flash('status', $value ? 'Product prices are now shown to customers.' : 'Product prices are now hidden from customers.');
    }

    /** The on/off switch saves at once, so the button appears or disappears immediately. */
    public function updatedSearchEnabled(bool $value): void
    {
        $this->store(PlatformSetting::AI_SEARCH_ENABLED, $value);
        session()->flash('status', $value ? 'The Ask AI button is now shown to customers.' : 'The Ask AI button is now hidden from customers.');
    }

    public function updatedOrderEnabled(bool $value): void
    {
        $this->store(PlatformSetting::AI_ORDER_ENABLED, $value);
        session()->flash('status', $value ? 'The "Use AI to place order" button is now shown to customers.' : 'The "Use AI to place order" button is now hidden from customers.');
    }

    public function save(): void
    {
        $this->validate(['dailyLimit' => 'required|integer|min:0|max:100000']);

        $this->store(PlatformSetting::AI_SEARCH_GUESTS, $this->guestsAllowed);
        $this->store(PlatformSetting::AI_DAILY_LIMIT, $this->dailyLimit);
        session()->flash('status', 'AI settings saved.');
    }

    protected function store(string $key, mixed $value): void
    {
        abort_unless(Auth::user()->can('manage-platform-settings'), 403);

        $old = PlatformSetting::get($key);
        PlatformSetting::set($key, $value, Auth::user());

        activity('settings')->causedBy(Auth::user())
            ->withProperties(['key' => $key, 'old' => $old, 'new' => $value])
            ->log("Changed setting {$key}");
    }

    /** Requests, tokens and estimated cost for rows since a moment. */
    protected function summary(\DateTimeInterface $since, ?\DateTimeInterface $until = null): array
    {
        $rows = AiRequest::where('created_at', '>=', $since)->when($until, fn ($q) => $q->where('created_at', '<', $until))
            ->selectRaw('model, COUNT(*) as requests, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed, SUM(input_tokens) as input, SUM(output_tokens) as output, SUM(cache_read_tokens) as cache_read, SUM(cache_write_tokens) as cache_write', [AiRequest::STATUS_FAILED])
            ->groupBy('model')->get();
        $usd = $rows->sum(fn ($r) => AiPricing::usd($r->model, (int) $r->input, (int) $r->output, (int) $r->cache_read, (int) $r->cache_write));
        $requests = (int) $rows->sum('requests');

        return [
            'requests' => $requests,
            'failed' => (int) $rows->sum('failed'),
            'tokens' => (int) ($rows->sum('input') + $rows->sum('output') + $rows->sum('cache_read') + $rows->sum('cache_write')),
            'usd' => $usd,
            'per_request' => $requests ? $usd / $requests : 0,
        ];
    }

    public function render(AiClient $ai)
    {
        $rate = (float) PlatformSetting::get(PlatformSetting::AI_USD_TZS) ?: 2660;

        // Daily cost for the last 30 days, for the chart.
        $daily = AiRequest::where('created_at', '>=', today()->subDays(29))
            ->selectRaw('DATE(created_at) as day, model, COUNT(*) as requests, SUM(input_tokens) as input, SUM(output_tokens) as output, SUM(cache_read_tokens) as cache_read, SUM(cache_write_tokens) as cache_write')
            ->groupBy('day', 'model')->get()
            ->groupBy('day')->map(fn ($rows) => [
                'requests' => (int) $rows->sum('requests'),
                'usd' => $rows->sum(fn ($r) => AiPricing::usd($r->model, (int) $r->input, (int) $r->output, (int) $r->cache_read, (int) $r->cache_write)),
            ]);
        $days = collect(range(29, 0))->map(fn ($i) => today()->subDays($i)->toDateString())
            ->mapWithKeys(fn ($d) => [$d => $daily[$d] ?? ['requests' => 0, 'usd' => 0.0]]);

        $search = trim($this->search);
        $recent = AiRequest::with('user')
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->feature !== '', fn ($q) => $q->where('feature', $this->feature))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('query', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest()->paginate(25);

        return view('livewire.admin.settings.ai', [
            'configured' => $ai->isConfigured(),
            'demo' => $ai instanceof DemoAiClient,
            'model' => $ai instanceof DemoAiClient ? 'demo (free, no real AI)' : config('services.anthropic.model'),
            'rate' => $rate,
            'today' => $this->summary(today()),
            'thisMonth' => $this->summary(now()->startOfMonth()),
            'month' => $this->summary(now()->subDays(30)),
            'days' => $days,
            'byFeature' => AiRequest::where('created_at', '>=', now()->subDays(30))->selectRaw('feature, COUNT(*) as total')->groupBy('feature')->pluck('total', 'feature'),
            'recent' => $recent,
            'filtered' => $this->from !== '' || $this->to !== '' || $search !== '' || $this->feature !== '' || $this->status !== '',
            'cacheSince' => AiRequest::where('cache_read_tokens', '>', 0)->min('created_at'),
        ]);
    }
}
