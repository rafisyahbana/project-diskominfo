<?php

namespace App\Services;

use App\Models\Permohonan;
use App\Models\SesiVerifikasi;
use App\Models\LogAksesAgent;

class PermohonanService
{
    /**
     * Mengajukan permohonan surat baru.
     */
    public function ajukan(string $sesiId, string $jenisSurat, array $dataForm): array
    {
        ['sesi' => $sesi, 'error' => $error] = $this->resolveAndValidateSesi($sesiId, 'ajukan_surat');

        if ($error) return $error;

        $permohonan = Permohonan::create([
            'nik'          => $sesi->nik,
            'no_wa'        => $sesi->no_wa,
            'jenis_surat'  => $jenisSurat,
            'data_form'    => $dataForm,
            'status'       => 'menunggu_verifikasi',
        ]);

        $this->logAkses($sesi->no_wa, $sesi->nik, 'success', [
            'sesi_id'       => $sesiId,
            'id_permohonan' => $permohonan->id,
        ], 'ajukan_surat');

        return [
            'status' => 'menunggu_verifikasi',
            'code' => 201,
            'data' => [
                'id_permohonan' => $permohonan->id,
            ]
        ];
    }

    /**
     * Melihat detail permohonan.
     */
    public function show(string $sesiId, string $idPermohonan): array
    {
        ['sesi' => $sesi, 'error' => $error] = $this->resolveAndValidateSesi($sesiId, 'cek_permohonan');

        if ($error) return $error;

        $permohonan = Permohonan::find($idPermohonan);

        if (!$permohonan || $permohonan->nik !== $sesi->nik) {
            $this->logAkses($sesi->no_wa, $sesi->nik, 'denied', [
                'id_permohonan' => $idPermohonan,
                'alasan'        => !$permohonan ? 'not_found' : 'nik_mismatch',
            ], 'cek_permohonan');
            return [
                'status' => 'permohonan_tidak_ditemukan',
                'code' => 404,
            ];
        }

        $this->logAkses($sesi->no_wa, $sesi->nik, 'success', [
            'id_permohonan' => $idPermohonan,
        ], 'cek_permohonan');

        return [
            'status' => 'success',
            'code' => 200,
            'data' => [
                'id_permohonan'   => $permohonan->id,
                'jenis_surat'     => $permohonan->jenis_surat,
                'status'          => $permohonan->status,
                'catatan_petugas' => $permohonan->catatan_petugas,
                'file_surat_url'  => $permohonan->file_surat_url,
                'created_at'      => $permohonan->created_at->toDateTimeString(),
            ]
        ];
    }

    /**
     * Mendapatkan daftar permohonan.
     */
    public function index(?string $sesiId): array
    {
        ['sesi' => $sesi, 'error' => $error] = $this->resolveAndValidateSesi($sesiId, 'daftar_permohonan');

        if ($error) return $error;

        $permohonan = Permohonan::where('nik', $sesi->nik)
            ->orderByDesc('created_at')
            ->get(['id', 'jenis_surat', 'status', 'created_at']);

        $this->logAkses($sesi->no_wa, $sesi->nik, 'success', [
            'jumlah' => $permohonan->count(),
        ], 'daftar_permohonan');

        return [
            'status' => 'success',
            'code' => 200,
            'data' => [
                'data' => $permohonan
            ]
        ];
    }

    /**
     * Cari dan validasi sesi. Return array ['sesi' => ..., 'error' => result array]
     */
    private function resolveAndValidateSesi(?string $sesiId, string $tool): array
    {
        if (!$sesiId) {
            $this->logAkses('unknown', 'unknown', 'sesi_tidak_ditemukan', ['sesi_id' => null], $tool);
            return ['sesi' => null, 'error' => ['status' => 'sesi_tidak_ditemukan', 'code' => 404]];
        }

        $sesi = SesiVerifikasi::find($sesiId);

        if (!$sesi) {
            $this->logAkses('unknown', 'unknown', 'sesi_tidak_ditemukan', ['sesi_id' => $sesiId], $tool);
            return ['sesi' => null, 'error' => ['status' => 'sesi_tidak_ditemukan', 'code' => 404]];
        }

        if (!$sesi->isVerifiedAndActive()) {
            $this->logAkses($sesi->no_wa, $sesi->nik, 'sesi_tidak_valid', ['sesi_id' => $sesiId], $tool);
            return ['sesi' => null, 'error' => ['status' => 'sesi_tidak_valid', 'code' => 403]];
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
}
