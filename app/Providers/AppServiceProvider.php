<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\LlmResponder::class,
            \App\Services\StubLlmResponder::class
        );

        $this->app->bind(
            \App\Contracts\WhatsAppNotifier::class,
            \App\Services\StubWhatsAppNotifier::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
