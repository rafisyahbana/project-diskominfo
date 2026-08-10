<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjukanSuratRequest;
use App\Models\Permohonan;
use App\Models\SesiVerifikasi;
use App\Models\LogAksesAgent;
use Illuminate\Http\Request;

class PermohonanController extends Controller
{
    // ─── Shared Utilities ─────────────────────────────────────────────────────

    /**
     * Cari dan validasi sesi. Return array ['sesi' => ..., 'error' => response]
     * Error response sudah include logging — caller cukup return error-nya saja.
     */
    private function resolveAndValidateSesi(string|null $sesiId, string $tool): array
    {
        if (!$sesiId) {
            $this->logAkses('unknown', 'unknown', 'sesi_tidak_ditemukan', ['sesi_id' => null], $tool);
            return ['sesi' => null, 'error' => response()->json(['status' => 'sesi_tidak_ditemukan'], 404)];
        }

        $sesi = SesiVerifikasi::find($sesiId);

        if (!$sesi) {
            $this->logAkses('unknown', 'unknown', 'sesi_tidak_ditemukan', ['sesi_id' => $sesiId], $tool);
            return ['sesi' => null, 'error' => response()->json(['status' => 'sesi_tidak_ditemukan'], 404)];
        }

        if (!$sesi->isVerifiedAndActive()) {
            $this->logAkses($sesi->no_wa, $sesi->nik, 'sesi_tidak_valid', ['sesi_id' => $sesiId], $tool);
            return ['sesi' => null, 'error' => response()->json(['status' => 'sesi_tidak_valid'], 403)];
        }

        return ['sesi' => $sesi, 'error' => null];
    }

    private function logAkses(string $no_wa, string $nik, string $hasil, ?array $payload = null, string $tool = 'ajukan_surat')
    {
        LogAksesAgent::create([
            'no_wa'          => $no_wa,
            'nik'            => $nik,
            'tool_dipanggil' => $tool,
            'payload'        => $payload,
            'hasil'          => $hasil,
            'created_at'     => now(),
        ]);
    }

    // ─── POST /api/permohonan ─────────────────────────────────────────────────

    public function ajukan(AjukanSuratRequest $request)
    {
        $sesiId = $request->input('sesi_id');
        ['sesi' => $sesi, 'error' => $error] = $this->resolveAndValidateSesi($sesiId, 'ajukan_surat');

        if ($error) return $error;

        $permohonan = Permohonan::create([
            'nik'          => $sesi->nik,   // nik WAJIB berasal dari sesi
            'no_wa'        => $sesi->no_wa,
            'jenis_surat'  => $request->input('jenis_surat'),
            'data_form'    => $request->input('data_form'),
            'status'       => 'menunggu_verifikasi',
        ]);

        $this->logAkses($sesi->no_wa, $sesi->nik, 'success', [
            'sesi_id'       => $sesiId,
            'id_permohonan' => $permohonan->id,
        ], 'ajukan_surat');

        return response()->json([
            'id_permohonan' => $permohonan->id,
            'status'        => 'menunggu_verifikasi',
        ], 201);
    }

    // ─── GET /api/permohonan/{id_permohonan}?sesi_id= ────────────────────────

    public function show(Request $request, string $id_permohonan)
    {
        $sesiId = $request->query('sesi_id');
        ['sesi' => $sesi, 'error' => $error] = $this->resolveAndValidateSesi($sesiId, 'cek_permohonan');

        if ($error) return $error;

        // Sengaja tidak pakai findOrFail — kita ingin kontrol penuh atas response
        $permohonan = Permohonan::find($id_permohonan);

        // Jika tidak ditemukan ATAU nik tidak cocok → 404 tanpa bocorkan detail.
        // Perlakuan yang sama mencegah enumeration attack: penyerang tidak bisa
        // membedakan "ID tidak ada" vs "ID ada tapi bukan milikmu".
        if (!$permohonan || $permohonan->nik !== $sesi->nik) {
            $this->logAkses($sesi->no_wa, $sesi->nik, 'denied', [
                'id_permohonan' => $id_permohonan,
                'alasan'        => !$permohonan ? 'not_found' : 'nik_mismatch',
            ], 'cek_permohonan');
            return response()->json(['status' => 'permohonan_tidak_ditemukan'], 404);
        }

        $this->logAkses($sesi->no_wa, $sesi->nik, 'success', [
            'id_permohonan' => $id_permohonan,
        ], 'cek_permohonan');

        return response()->json([
            'id_permohonan'   => $permohonan->id,
            'jenis_surat'     => $permohonan->jenis_surat,
            'status'          => $permohonan->status,
            'catatan_petugas' => $permohonan->catatan_petugas,
            'file_surat_url'  => $permohonan->file_surat_url,
            'created_at'      => $permohonan->created_at->toDateTimeString(),
        ]);
    }

    // ─── GET /api/permohonan?sesi_id= ────────────────────────────────────────

    public function index(Request $request)
    {
        $sesiId = $request->query('sesi_id');
        ['sesi' => $sesi, 'error' => $error] = $this->resolveAndValidateSesi($sesiId, 'daftar_permohonan');

        if ($error) return $error;

        $permohonan = Permohonan::where('nik', $sesi->nik)
            ->orderByDesc('created_at')
            ->get(['id', 'jenis_surat', 'status', 'created_at']);

        $this->logAkses($sesi->no_wa, $sesi->nik, 'success', [
            'jumlah' => $permohonan->count(),
        ], 'daftar_permohonan');

        return response()->json(['data' => $permohonan]);
    }
}
