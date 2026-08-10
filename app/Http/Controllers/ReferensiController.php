<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReferensiController extends Controller
{
    /**
     * GET /api/referensi/syarat/{jenis_surat}
     *
     * Mengembalikan daftar syarat dan estimasi waktu penerbitan surat.
     *
     * TODO: Pindahkan data ini ke tabel referensi_syarat di database agar
     *       petugas bisa mengubah syarat tanpa perlu deploy ulang aplikasi.
     *       Gunakan cache (misalnya Cache::remember) untuk menghindari query
     *       berulang ke DB untuk data yang jarang berubah.
     */
    public function syarat(string $jenis_surat)
    {
        $katalog = [
            'domisili' => [
                'jenis_surat'     => 'domisili',
                'nama'            => 'Surat Keterangan Domisili',
                'estimasi_waktu'  => '1-2 hari kerja',
                'syarat' => [
                    'Fotokopi KTP',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat pengantar RT/RW',
                    'Materai Rp10.000',
                ],
            ],
            'sktm' => [
                'jenis_surat'     => 'sktm',
                'nama'            => 'Surat Keterangan Tidak Mampu',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => [
                    'Fotokopi KTP',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat pengantar RT/RW',
                    'Surat permohonan bermaterai',
                    'Dokumen pendukung (tagihan, kondisi rumah, dll)',
                ],
            ],
            'pengantar' => [
                'jenis_surat'     => 'pengantar',
                'nama'            => 'Surat Pengantar',
                'estimasi_waktu'  => '1 hari kerja',
                'syarat' => [
                    'Fotokopi KTP',
                    'Surat pengantar RT/RW',
                    'Keterangan keperluan surat',
                ],
            ],
            'lainnya' => [
                'jenis_surat'     => 'lainnya',
                'nama'            => 'Surat Keterangan Lainnya',
                'estimasi_waktu'  => '2-5 hari kerja',
                'syarat' => [
                    'Fotokopi KTP',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat pengantar RT/RW',
                    'Dokumen pendukung sesuai keperluan',
                    'Surat permohonan bermaterai',
                ],
            ],
        ];

        if (!array_key_exists($jenis_surat, $katalog)) {
            return response()->json(['status' => 'jenis_surat_tidak_dikenal'], 404);
        }

        return response()->json($katalog[$jenis_surat]);
    }
}
