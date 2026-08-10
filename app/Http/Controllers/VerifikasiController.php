<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\MulaiVerifikasiRequest;
use App\Http\Requests\CekOtpRequest;
use App\Models\Warga;
use App\Models\SesiVerifikasi;
use App\Models\LogAksesAgent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VerifikasiController extends Controller
{
    public function mulai(MulaiVerifikasiRequest $request)
    {
        $nik = $request->input('nik');
        $no_wa = $request->input('no_wa');

        $warga = Warga::find($nik);

        if (!$warga) {
            $this->logAkses($no_wa, $nik, 'denied', ['error' => 'nik_tidak_ditemukan']);
            return response()->json(['status' => 'nik_tidak_ditemukan'], 404);
        }

        if (empty($warga->no_hp_terdaftar) || $warga->no_hp_terdaftar !== $no_wa) {
            $this->logAkses($no_wa, $nik, 'denied', ['error' => 'no_wa_tidak_cocok']);
            return response()->json(['status' => 'no_wa_tidak_cocok'], 403);
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

        // TODO: Integrasi WhatsApp API untuk mengirim OTP ke user
        Log::info("OTP untuk $no_wa adalah: $otp");

        $this->logAkses($no_wa, $nik, 'success');

        return response()->json([
            'sesi_id' => $sesi->id,
            'status' => 'pending'
        ]);
    }

    public function cek(CekOtpRequest $request)
    {
        $sesiId = $request->input('sesi_id');
        $otp = $request->input('otp');

        $sesi = SesiVerifikasi::find($sesiId);

        if (!$sesi) {
            $this->logAkses('unknown', 'unknown', 'sesi_tidak_ditemukan', ['sesi_id' => $sesiId]);
            return response()->json(['status' => 'sesi_tidak_ditemukan'], 404);
        }

        if ($sesi->status !== 'pending') {
            $this->logAkses($sesi->no_wa, $sesi->nik, $sesi->status, ['sesi_id' => $sesiId]);
            return response()->json(['status' => $sesi->status], 409);
        }

        if (now()->greaterThan($sesi->expired_at)) {
            $sesi->update(['status' => 'expired']);
            $this->logAkses($sesi->no_wa, $sesi->nik, 'kedaluwarsa', ['sesi_id' => $sesiId]);
            return response()->json(['status' => 'kedaluwarsa'], 410);
        }

        if (!Hash::check($otp, $sesi->otp_hash)) {
            $sesi->increment('percobaan_gagal');
            
            if ($sesi->percobaan_gagal >= 3) {
                $sesi->update(['status' => 'failed']);
                $this->logAkses($sesi->no_wa, $sesi->nik, 'gagal_permanen', ['sesi_id' => $sesiId]);
                return response()->json(['status' => 'gagal_permanen'], 429);
            }

            $sisa_percobaan = 3 - $sesi->percobaan_gagal;
            $this->logAkses($sesi->no_wa, $sesi->nik, 'salah', ['sesi_id' => $sesiId, 'sisa' => $sisa_percobaan]);
            return response()->json(['status' => 'salah', 'sisa_percobaan' => $sisa_percobaan], 401);
        }

        $sesi->update([
            'status' => 'verified',
            'berlaku_hingga' => now()->addMinutes(30)
        ]);

        $this->logAkses($sesi->no_wa, $sesi->nik, 'verified', ['sesi_id' => $sesiId]);
        
        return response()->json([
            'status' => 'verified',
            'berlaku_hingga' => $sesi->berlaku_hingga->toDateTimeString()
        ], 200);
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
