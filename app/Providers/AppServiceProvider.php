<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\ClaudeAiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiClient::class, fn () => new ClaudeAiClient(
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
