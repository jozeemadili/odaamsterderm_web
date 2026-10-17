<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\ClaudeAiClient;
use App\Services\Ai\DemoAiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No API key yet (development): the free demo AI answers instead.
        $this->app->bind(AiClient::class, fn () => blank(config('services.anthropic.api_key')) && config('services.anthropic.demo')
            ? new DemoAiClient
            : new ClaudeAiClient(
                config('services.anthropic.api_key'),
                config('services.anthropic.model'),
                config('services.anthropic.effort'),
            ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
