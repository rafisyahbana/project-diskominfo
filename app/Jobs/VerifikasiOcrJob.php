<?php

namespace App\Jobs;

use App\Models\DokumenPermohonan;
use App\Models\SesiVerifikasi;
use App\Services\OcrService;
use App\Contracts\WhatsAppNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VerifikasiOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan jika job gagal (misal Tesseract crash).
     * Setelah 3 kali gagal, job masuk ke tabel failed_jobs.
     */
    public int $tries = 3;

    /**
     * Timeout per percobaan dalam detik.
     * OCR foto KTP asli paling lama ~30 detik di mesin lambat.
     */
    public int $timeout = 60;

    public function __construct(
        protected string $dokumenId,
        protected string $sesiId,
        protected string $noWa
    ) {}

    public function handle(OcrService $ocr, WhatsAppNotifier $notifier): void
    {
        $dokumen = DokumenPermohonan::find($this->dokumenId);

        if (!$dokumen) {
            Log::warning('[VerifikasiOcrJob] Dokumen tidak ditemukan', ['id' => $this->dokumenId]);
            return;
        }

        // Hanya proses dokumen KTP — jenis lain tidak mengandung NIK
        if ($dokumen->jenis_dokumen !== 'fotokopi_ktp') {
            $dokumen->update([
                'status_ocr'      => 'ok',   // non-KTP selalu dianggap "ok" (tidak ada NIK untuk dicek)
                'ocr_diproses_at' => now(),
            ]);
            return;
        }

        $pathAbsolut = Storage::disk('local')->path($dokumen->path_file);

        // Jalankan OCR
        $teks = $ocr->ekstrak($pathAbsolut);

        if ($teks === null) {
            // Tesseract error atau output kosong → flag tidak_terbaca untuk audit petugas
            $dokumen->update([
                'status_ocr'      => 'tidak_terbaca',
                'nik_terbaca'     => null,
                'ocr_diproses_at' => now(),
            ]);

            Log::info('[VerifikasiOcrJob] OCR tidak dapat membaca dokumen, ditandai untuk verifikasi manual', [
                'dokumen_id' => $this->dokumenId,
                'no_wa'      => $this->noWa,
            ]);

            // Tidak kirim notifikasi khusus — warga sudah dapat pesan "sukses upload" dari UploadController
            return;
        }

        // Ekstrak NIK dari teks OCR
        $nikTerbaca = $ocr->ekstrakNik($teks);

        // Ambil NIK referensi dari SesiVerifikasi
        $sesi    = SesiVerifikasi::find($this->sesiId);
        $nikAsli = $sesi?->nik;

        // Bandingkan (normalisasi: hapus spasi, hanya angka)
        $nikBersih = $nikTerbaca ? preg_replace('/\D/', '', $nikTerbaca) : null;
        $nikCocok  = $nikAsli && $nikBersih && $nikBersih === $nikAsli;

        $statusOcr = $nikCocok ? 'ok' : 'gagal';

        $dokumen->update([
            'status_ocr'      => $statusOcr,
            'nik_terbaca'     => $nikBersih,
            'ocr_diproses_at' => now(),
        ]);

        Log::info('[VerifikasiOcrJob] OCR selesai', [
            'dokumen_id'  => $this->dokumenId,
            'no_wa'       => $this->noWa,
            'status_ocr'  => $statusOcr,
            'nik_terbaca' => $nikBersih,
            'nik_asli'    => $nikAsli,
            // Detail ketidakcocokan HANYA di log internal, TIDAK pernah ke warga
        ]);

        // Kirim notifikasi WA yang SAMA untuk semua kasus (ok maupun gagal):
        // Warga tidak perlu tahu detail verifikasi internal — itu urusan petugas.
        // Pesan ini hanya dikrim jika jobnya adalah KTP (non-KTP tidak kirim notif).
        $notifier->kirim(
            $this->noWa,
            "ℹ Dokumen KTP Anda sudah diterima dan sedang diproses oleh petugas."
        );
    }

    /**
     * Dipanggil jika semua percobaan habis (failed_jobs).
     * Update dokumen ke status tidak_terbaca agar petugas bisa audit.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[VerifikasiOcrJob] Job gagal setelah semua percobaan', [
            'dokumen_id' => $this->dokumenId,
            'error'      => $exception->getMessage(),
        ]);

        DokumenPermohonan::where('id', $this->dokumenId)->update([
            'status_ocr'      => 'tidak_terbaca',
            'ocr_diproses_at' => now(),
        ]);
    }
}
