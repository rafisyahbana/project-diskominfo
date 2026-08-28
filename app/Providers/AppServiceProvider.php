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
            \App\Services\FonnteService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa HTTPS jika aplikasi diakses melalui Reverse Proxy (yang mengirimkan X-Forwarded-Proto = https),
        // atau jika diakses menggunakan layanan tunnel ngrok, ATAU jika di production.
        $request = request();
        $isHttpsProxy = $request->header('x-forwarded-proto') === 'https';
        $isNgrok = str_contains($request->getHost(), 'ngrok-free.dev') || str_contains($request->getHost(), 'ngrok.app') || str_contains($request->getHost(), 'ngrok.io');
        $isProduction = app()->environment('production');

        if ($isHttpsProxy || $isNgrok || $isProduction) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
