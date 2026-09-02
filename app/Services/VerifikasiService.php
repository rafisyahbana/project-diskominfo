<?php

namespace App\Services;

use App\Contracts\WhatsAppNotifier;
use App\Models\Warga;
use App\Models\SesiVerifikasi;
use App\Models\LogAksesAgent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class VerifikasiService
{
    public function __construct(protected WhatsAppNotifier $notifier) {}

    /**
     * Memulai proses verifikasi NIK.
     * Mengembalikan array: ['status' => string, 'code' => int, 'data' => array|null]
     */
    public function mulai(string $nik, string $no_wa): array
    {
        $warga = Warga::find($nik);

        if (!$warga) {
            $this->logAkses($no_wa, $nik, 'denied', ['error' => 'nik_tidak_ditemukan']);
            return [
                'status' => 'nik_tidak_ditemukan',
                'code' => 404,
            ];
        }

        if (empty($warga->no_hp_terdaftar) || $warga->no_hp_terdaftar !== $no_wa) {
            $this->logAkses($no_wa, $nik, 'denied', ['error' => 'no_wa_tidak_cocok']);
            return [
                'status' => 'no_wa_tidak_cocok',
                'code' => 403,
            ];
        }

        $otp = sprintf('%06d', mt_rand(1, 999999));
        
        $sesi = SesiVerifikasi::create([
            'no_wa' => $no_wa,
            'nik' => $nik,
            'otp_hash' => Hash::make($otp),
            'status' => 'pending',
            'percobaan_gagal' => 0,
            'expired_at' => now()->addMinutes(5),
        ]);

        // Kirim OTP via WhatsApp (Fonnte)
        $pesan = "Kode OTP verifikasi Anda adalah: *{$otp}*\n\nKode berlaku selama 5 menit. Jangan bagikan kode ini kepada siapapun.";
        $terkirim = $this->notifier->kirim($no_wa, $pesan);

        Log::info("OTP untuk $no_wa adalah: $otp", ['terkirim' => $terkirim]);

        $this->logAkses($no_wa, $nik, 'success');

        return [
            'status' => 'pending',
            'code' => 200,
            'data' => [
                'sesi_id' => $sesi->id,
            ]
        ];
    }

    /**
     * Memeriksa OTP untuk sesi yang diberikan.
     * Mengembalikan array: ['status' => string, 'code' => int, 'data' => array|null]
     */
    public function cekOtp(string $sesiId, string $otp): array
    {
        $sesi = SesiVerifikasi::find($sesiId);

        if (!$sesi) {
            $this->logAkses('unknown', 'unknown', 'sesi_tidak_ditemukan', ['sesi_id' => $sesiId]);
            return [
                'status' => 'sesi_tidak_ditemukan',
                'code' => 404,
            ];
        }

        if ($sesi->status !== 'pending') {
            $this->logAkses($sesi->no_wa, $sesi->nik, $sesi->status, ['sesi_id' => $sesiId]);
            return [
                'status' => $sesi->status,
                'code' => 409,
            ];
        }

        if (now()->greaterThan($sesi->expired_at)) {
            $sesi->update(['status' => 'expired']);
            $this->logAkses($sesi->no_wa, $sesi->nik, 'kedaluwarsa', ['sesi_id' => $sesiId]);
            return [
                'status' => 'kedaluwarsa',
                'code' => 410,
            ];
        }

        if (!Hash::check($otp, $sesi->otp_hash)) {
            $sesi->increment('percobaan_gagal');
            
            if ($sesi->percobaan_gagal >= 3) {
                $sesi->update(['status' => 'failed']);
                $this->logAkses($sesi->no_wa, $sesi->nik, 'gagal_permanen', ['sesi_id' => $sesiId]);
                return [
                    'status' => 'gagal_permanen',
                    'code' => 429,
                ];
            }

            $sisa_percobaan = 3 - $sesi->percobaan_gagal;
            $this->logAkses($sesi->no_wa, $sesi->nik, 'salah', ['sesi_id' => $sesiId, 'sisa' => $sisa_percobaan]);
            return [
                'status' => 'salah',
                'code' => 401,
                'data' => [
                    'sisa_percobaan' => $sisa_percobaan
                ]
            ];
        }

        $sesi->update([
            'status' => 'verified',
            'berlaku_hingga' => now()->addMinutes(30)
        ]);

        $this->logAkses($sesi->no_wa, $sesi->nik, 'verified', ['sesi_id' => $sesiId]);
        
        return [
            'status' => 'verified',
            'code' => 200,
            'data' => [
                'berlaku_hingga' => $sesi->berlaku_hingga->toDateTimeString()
            ]
        ];
    }

    private function logAkses($no_wa, $nik, $hasil, $payload = null)
    {
        LogAksesAgent::create([
            'no_wa' => $no_wa,
            'nik' => $nik,
            'tool_dipanggil' => 'mulai_verifikasi',
            'payload' => $payload,
            'hasil' => $hasil,
            'created_at' => now(),
        ]);
    }
}
