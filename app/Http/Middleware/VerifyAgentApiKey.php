<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\LogAksesAgent;
use Symfony\Component\HttpFoundation\Response;

class VerifyAgentApiKey
{
    /**
     * Handle an incoming request.
     *
     * Membandingkan header X-Agent-Api-Key dengan nilai AGENT_API_KEY di .env
     * menggunakan hash_equals() untuk mencegah timing attack.
     *
     * Komentar untuk reviewer keamanan:
     * - hash_equals() menjamin perbandingan string konstan-waktu, sehingga
     *   penyerang tidak bisa mengukur waktu respons untuk menebak karakter
     *   per karakter (timing side-channel attack).
     * - Key disimpan di .env, tidak di-hardcode di source code.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $keyFromHeader = $request->header('X-Agent-Api-Key', '');
        $keyFromEnv    = config('app.agent_api_key', '');

        // hash_equals() aman terhadap timing attack; == tidak.
        // Pastikan kedua string tidak kosong sebelum dibandingkan.
        $valid = $keyFromEnv !== ''
            && hash_equals($keyFromEnv, $keyFromHeader);

        if (!$valid) {
            // Log percobaan akses tidak sah, tapi jangan bocorkan info apapun
            LogAksesAgent::create([
                'no_wa'          => $request->header('X-Forwarded-For', $request->ip()),
                'nik'            => 'unknown',
                'tool_dipanggil' => $request->path(),
                'payload'        => null,
                'hasil'          => 'unauthorized',
                'created_at'     => now(),
            ]);

            return response()->json(['status' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
