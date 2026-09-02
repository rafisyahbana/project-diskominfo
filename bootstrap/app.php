<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai semua reverse proxy (Ngrok, dsb) agar X-Forwarded-Proto dibaca
        // dengan benar saat memverifikasi signed URL maupun generate URL.
        // Di production, ganti '*' dengan IP spesifik load balancer untuk keamanan lebih.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'agent.apikey' => \App\Http\Middleware\VerifyAgentApiKey::class,
            'prevent.back' => \App\Http\Middleware\PreventBackHistory::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\PreventBackHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, Request $request) {
            return response()->view('errors.custom', [
                'message' => 'Tautan sudah kedaluwarsa atau tidak valid. Silakan ketik apa saja di WhatsApp chatbot kami untuk mendapatkan tautan unggah yang baru.'
            ], 403);
        });
    })->create();
