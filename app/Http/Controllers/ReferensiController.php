<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ReferensiService;

class ReferensiController extends Controller
{
    protected ReferensiService $referensiService;

    public function __construct(ReferensiService $referensiService)
    {
        $this->referensiService = $referensiService;
    }

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
        $data = $this->referensiService->syarat($jenis_surat);

        if (!$data) {
            return response()->json(['status' => 'jenis_surat_tidak_dikenal'], 404);
        }

        return response()->json($data);
    }
}
