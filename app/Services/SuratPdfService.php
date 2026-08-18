<?php

namespace App\Services;

use App\Models\Permohonan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SuratPdfService
{
    /**
     * Generate nomor surat dengan format SURAT-{YYYYMMDD}-{urutan 4 digit}.
     * Query MAX+1 di dalam transaksi + lock untuk mencegah race condition.
     */
    private function generateNomorSurat(): string
    {
        return DB::transaction(function () {
            $tanggal = now()->format('Ymd');
            $prefix  = "SURAT-{$tanggal}-";

            // Lock baris terakhir dengan awalan prefix hari ini agar atomic
            $last = DB::table('permohonan')
                ->where('nomor_surat', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByDesc('nomor_surat')
                ->value('nomor_surat');

            if ($last === null) {
                $urutan = 1;
            } else {
                // Ambil 4 digit terakhir setelah prefix
                $urutanStr = substr($last, strlen($prefix));
                $urutan    = (int) $urutanStr + 1;
            }

            return $prefix . str_pad($urutan, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Pilih view template Blade berdasarkan jenis surat.
     */
    private function resolveTemplate(string $jenisSurat): array
    {
        return match ($jenisSurat) {
            'domisili'  => ['view' => 'surat.domisili',    'judul' => 'Surat Keterangan Domisili'],
            'sktm'      => ['view' => 'surat.placeholder', 'judul' => 'Surat Keterangan Tidak Mampu'],
            'pengantar' => ['view' => 'surat.placeholder', 'judul' => 'Surat Pengantar'],
            default     => ['view' => 'surat.placeholder', 'judul' => 'Surat Keterangan'],
        };
    }

    /**
     * Generate PDF surat untuk permohonan, simpan ke storage 'local'.
     *
     * @param  Permohonan $permohonan  Harus sudah berstatus 'diproses'
     * @return string  Path file tersimpan (relatif ke disk 'local')
     */
    public function generate(Permohonan $permohonan): string
    {
        $nomorSurat  = $this->generateNomorSurat();
        $tanggalTerbit = now()->translatedFormat('d F Y');

        // Simpan nomor_surat dulu ke DB sebelum render, agar bisa tampil di template
        $permohonan->update(['nomor_surat' => $nomorSurat]);

        $template = $this->resolveTemplate($permohonan->jenis_surat);

        $pdf = Pdf::loadView($template['view'], [
            'permohonan'    => $permohonan->fresh(),
            'judulSurat'    => $template['judul'],
            'tanggalTerbit' => $tanggalTerbit,
        ])
        ->setPaper('a4', 'portrait');

        $relativePath = "surat/{$permohonan->id}.pdf";
        Storage::disk('local')->put($relativePath, $pdf->output());

        return $relativePath;
    }
}
