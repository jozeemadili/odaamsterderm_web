<?php

namespace App\Livewire\Admin\Settings;

use App\Models\AiRequest;
use App\Models\PlatformSetting;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Switches for the customer "Ask AI" assistant, and its usage. */
#[Layout('layouts.admin', ['title' => 'AI Assistant'])]
class Ai extends Component
{
    /** USD per million tokens [input, output], for the cost estimate. */
    private const PRICES = [
        'claude-opus-5-5' => [4.0, 20.0],
        'claude-sonnet-5-5' => [2.0, 10.0],
        'claude-haiku-5-5' => [0.10, 0.50],
    ];

    public bool $searchEnabled = false;

    public bool $guestsAllowed = true;

    public int $dailyLimit = 500;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('manage-platform-settings'), 403);

        $this->searchEnabled = (bool) PlatformSetting::get(PlatformSetting::AI_SEARCH_ENABLED);
        $this->guestsAllowed = (bool) PlatformSetting::get(PlatformSetting::AI_SEARCH_GUESTS);
        $this->dailyLimit = (int) PlatformSetting::get(PlatformSetting::AI_DAILY_LIMIT);
    }

    /** The on/off switch saves at once, so the button appears or disappears immediately. */
    public function updatedSearchEnabled(bool $value): void
    {
        $this->store(PlatformSetting::AI_SEARCH_ENABLED, $value);
        session()->flash('status', $value ? 'The Ask AI button is now shown to customers.' : 'The Ask AI button is now hidden from customers.');
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

    public function render(AiClient $ai)
    {
        $usage = fn ($since) => AiRequest::where('created_at', '>=', $since)
            ->selectRaw('model, COUNT(*) as requests, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->groupBy('model')->get();

        $summarise = function ($rows) {
            $cost = $rows->sum(function ($r) {
                [$in, $out] = self::PRICES[$r->model] ?? [0, 0];

                return ($r->input_tokens * $in + $r->output_tokens * $out) / 1_000_000;
            });

            return ['requests' => (int) $rows->sum('requests'), 'tokens' => (int) ($rows->sum('input_tokens') + $rows->sum('output_tokens')), 'cost' => $cost];
        };

        return view('livewire.admin.settings.ai', [
            'configured' => $ai->isConfigured(),
            'model' => config('services.anthropic.model'),
            'today' => $summarise($usage(today())),
            'month' => $summarise($usage(now()->subDays(30))),
            'recent' => AiRequest::with('user')->latest()->limit(20)->get(),
        ]);
    }
}
