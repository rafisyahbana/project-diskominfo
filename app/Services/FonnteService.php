<?php

namespace App\Services;

use App\Contracts\WhatsAppNotifier;
use App\Models\NotifikasiTerkirim;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService implements WhatsAppNotifier
{
    public function kirim(string $noWa, string $pesan): bool
    {
        try {
            $token = config('services.fonnte.token');

            if (!$token) {
                Log::warning('[FonnteService] Token API belum dikonfigurasi. Pesan tidak dikirim.', [
                    'no_wa' => $noWa,
                ]);
                return false;
            }

            // Fonnte menggunakan Authorization: <token> tanpa prefix "Bearer"
            $response = Http::withHeaders(['Authorization' => $token])
                ->post('https://api.fonnte.com/send', [
                    'target'  => $noWa,
                    'message' => $pesan,
                ]);

            $body = $response->json();
            // Fonnte selalu return HTTP 200, cek status di body JSON
            $sukses = $response->successful() && ($body['status'] ?? false) === true;

            NotifikasiTerkirim::create([
                'no_wa'          => $noWa,
                'pesan'          => $pesan,
                'status_terkirim' => $sukses,
                'error_pesan'    => $sukses ? null : ($body['reason'] ?? 'Unknown error'),
            ]);

            if ($sukses) {
                Log::info('[FonnteService] Pesan terkirim via Fonnte', [
                    'no_wa'   => $noWa,
                    'preview' => mb_strimwidth($pesan, 0, 120, '…'),
                    'response' => $body,
                ]);
                return true;
            }

            Log::warning('[FonnteService] Fonnte menolak pesan', [
                'no_wa'    => $noWa,
                'reason'   => $body['reason'] ?? 'Unknown',
                'response' => $body,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('[FonnteService] Exception saat mengirim pesan via Fonnte', [
                'no_wa'  => $noWa,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }
}
