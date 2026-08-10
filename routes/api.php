<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API Gateway — OpenClaw Agent Routes
|--------------------------------------------------------------------------
| Semua route di bawah ini dilindungi oleh dua lapisan:
|   1. agent.apikey   — memastikan hanya agen yang punya X-Agent-Api-Key valid
|   2. throttle:1000,1 — rate limit tinggi untuk mengakomodasi banyak sesi paralel
|
| Mengapa referensi/syarat IKUT dilindungi agent.apikey:
|   - Data ini dipanggil oleh OpenClaw untuk menentukan syarat sebelum
|     membimbing warga. Tidak ada alasan publik mengaksesnya langsung.
|   - Melindunginya juga mencegah scraping/abuse oleh pihak luar.
|   - Jika kelak dibutuhkan endpoint publik (misal: widget website desa),
|     buat route terpisah tanpa middleware dan tambahkan cache layer.
|
| Catatan throttle:
|   1000 req/menit dirancang untuk OpenClaw server yang berada di belakang
|   NAT/IP tunggal, melayani banyak sesi warga secara paralel. Jika masih
|   terbentur limit, lebih disarankan mengimplementasikan RateLimiter khusus
|   di AppServiceProvider yang melacak berdasarkan X-Agent-Api-Key dan bukan IP.
*/
Route::middleware(['agent.apikey', 'throttle:1000,1'])->group(function () {

    // ── Verifikasi Identitas Warga ────────────────────────────────────────────
    Route::post('/verifikasi/mulai', [\App\Http\Controllers\VerifikasiController::class, 'mulai']);
    Route::post('/verifikasi/cek',   [\App\Http\Controllers\VerifikasiController::class, 'cek']);

    // ── Permohonan Surat (semua butuh sesi terverifikasi di level controller) ─
    Route::post('/permohonan',                  [\App\Http\Controllers\PermohonanController::class, 'ajukan']);
    Route::get('/permohonan',                   [\App\Http\Controllers\PermohonanController::class, 'index']);
    Route::get('/permohonan/{id_permohonan}',   [\App\Http\Controllers\PermohonanController::class, 'show']);

    // ── Data Referensi (publik untuk agent, tetap dijaga dari luar) ───────────
    Route::get('/referensi/syarat/{jenis_surat}', [\App\Http\Controllers\ReferensiController::class, 'syarat']);
});
