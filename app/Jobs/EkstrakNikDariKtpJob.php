<?php

namespace App\Jobs;

use App\Models\DokumenPermohonan;
use App\Models\PercakapanState;
use App\Services\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EkstrakNikDariKtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    protected string $dokumenId;
    protected string $noWa;

    /**
     * Create a new job instance.
     */
    public function __construct(string $dokumenId, string $noWa)
    {
        $this->dokumenId = $dokumenId;
        $this->noWa = $noWa;
    }

    /**
     * Execute the job.
     */
    public function handle(OcrService $ocrService): void
    {
        $dokumen = DokumenPermohonan::find($this->dokumenId);

        if (!$dokumen || !Storage::disk('local')->exists($dokumen->path_file)) {
            Log::warning('[EkstrakNikDariKtpJob] Dokumen tidak ditemukan atau sudah dihapus', [
                'dokumen_id' => $this->dokumenId,
            ]);
            return;
        }

        // Jalankan OCR
        $pathAbsolut = Storage::disk('local')->path($dokumen->path_file);
        $teks = $ocrService->ekstrak($pathAbsolut);

        if ($teks === null) {
            // Error OCR (misal file corrupted)
            $dokumen->update([
                'status_ocr'      => 'tidak_terbaca',
                'ocr_diproses_at' => now(),
            ]);

            Log::info('[EkstrakNikDariKtpJob] OCR gagal membaca file', ['dokumen_id' => $this->dokumenId]);
            return;
        }

        // Coba ekstrak NIK dari teks
        $nikTerbaca = $ocrService->ekstrakNik($teks);
        $nikBersih = $nikTerbaca ? preg_replace('/\D/', '', $nikTerbaca) : null;

        if ($nikBersih) {
            // NIK ditemukan, simpan ke DokumenPermohonan
            $dokumen->update([
                'status_ocr'      => 'ok', // Sementara ok, verifikasi dilakukan oleh user
                'nik_terbaca'     => $nikBersih,
                'ocr_diproses_at' => now(),
            ]);
        } else {
             // NIK tidak ditemukan dalam teks, dokumen ditandai gagal dibaca
             $dokumen->update([
                'status_ocr'      => 'tidak_terbaca',
                'ocr_diproses_at' => now(),
            ]);
        }

        Log::info('[EkstrakNikDariKtpJob] OCR selesai dijalankan', [
            'dokumen_id' => $this->dokumenId,
            'nik_terbaca' => $nikBersih,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('[EkstrakNikDariKtpJob] Job gagal setelah semua percobaan', [
            'dokumen_id' => $this->dokumenId,
            'error'      => $exception->getMessage(),
        ]);

        DokumenPermohonan::where('id', $this->dokumenId)->update([
            'status_ocr'      => 'tidak_terbaca',
            'ocr_diproses_at' => now(),
        ]);
    }
}
