<?php

namespace App\Services;

use App\Contracts\WhatsAppNotifier;
use App\Models\NotifikasiTerkirim;
use Illuminate\Support\Facades\Log;

/**
 * Implementasi stub untuk development/testing.
 * Tidak mengirim pesan WA asli — hanya:
 *  1. Mencatat ke tabel notifikasi_terkirim (dapat di-assert di test).
 *  2. Menulis log info agar mudah dimonitor.
 *
 * Ganti class ini dengan implementasi nyata (misal: Fonnte/WA Cloud API)
 * tanpa mengubah kode yang memanggil interface WhatsAppNotifier.
 */
class StubWhatsAppNotifier implements WhatsAppNotifier
{
    public function kirim(string $noWa, string $pesan): bool
    {
        try {
            NotifikasiTerkirim::create([
                'no_wa'   => $noWa,
                'pesan'   => $pesan,
            ]);

            Log::info('[StubWhatsAppNotifier] Pesan "terkirim"', [
                'no_wa'    => $noWa,
                'preview'  => mb_strimwidth($pesan, 0, 120, '…'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[StubWhatsAppNotifier] Gagal menyimpan notifikasi', [
                'no_wa' => $noWa,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
