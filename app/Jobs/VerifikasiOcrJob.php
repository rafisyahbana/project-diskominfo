<?php

namespace App\Jobs;

use App\Models\DokumenPermohonan;
use App\Models\PercakapanState;
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
use Illuminate\Support\Facades\URL;

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

        // Ekstrak NIK dari teks OCR (null jika teks kosong)
        $nikTerbaca = $teks ? $ocr->ekstrakNik($teks) : null;

        // Ambil NIK referensi dari SesiVerifikasi
        $sesi    = SesiVerifikasi::find($this->sesiId);
        $nikAsli = $sesi?->nik;

        // Normalisasi: hapus semua karakter non-digit
        $nikBersih = $nikTerbaca ? preg_replace('/\D/', '', $nikTerbaca) : null;
        $nikCocok  = $nikAsli && $nikBersih && $nikBersih === $nikAsli;

        if ($nikCocok) {
            // ✅ NIK cocok — dokumen diterima
            $dokumen->update([
                'status_ocr'      => 'ok',
                'nik_terbaca'     => $nikBersih,
                'ocr_diproses_at' => now(),
            ]);

            Log::info('[VerifikasiOcrJob] OCR KTP berhasil — NIK cocok', [
                'dokumen_id' => $this->dokumenId,
                'no_wa'      => $this->noWa,
            ]);

            $notifier->kirim(
                $this->noWa,
                "✅ Foto KTP Anda berhasil diverifikasi."
            );

            return;
        }

        // ❌ NIK tidak terbaca atau tidak cocok — tolak dan minta upload ulang
        $statusOcr = ($teks === null) ? 'tidak_terbaca' : 'gagal';

        $dokumen->update([
            'status_ocr'      => $statusOcr,
            'nik_terbaca'     => $nikBersih,
            'ocr_diproses_at' => now(),
            'status'          => 'ditolak',
        ]);

        Log::info('[VerifikasiOcrJob] OCR KTP gagal — minta upload ulang', [
            'dokumen_id'  => $this->dokumenId,
            'no_wa'       => $this->noWa,
            'status_ocr'  => $statusOcr,
            'nik_terbaca' => $nikBersih,
            'nik_asli'    => $nikAsli,
        ]);

        // Hapus file yang ditolak dari storage
        Storage::disk('local')->delete($dokumen->path_file);

        // Reset slot fotokopi_ktp di state warga agar bisa upload ulang
        $state = PercakapanState::where('no_wa', $this->noWa)->first();
        if ($state) {
            $dokumenDiterima = $state->dokumen_diterima ?? [];
            unset($dokumenDiterima['fotokopi_ktp']);
            
            // Jika state sudah masuk konfirmasi tapi KTP ditolak, kembalikan ke menunggu dokumen
            $langkah = $state->langkah;
            if ($langkah === 'menunggu_konfirmasi') {
                $langkah = 'menunggu_dokumen';
            }
            
            $state->update([
                'dokumen_diterima' => $dokumenDiterima,
                'langkah'          => $langkah
            ]);
        }

        // Generate link upload ulang (berlaku 60 menit)
        $linkUploadUlang = URL::temporarySignedRoute(
            'upload.form',
            now()->addMinutes(60),
            ['no_wa' => $this->noWa, 'sesi_id' => $this->sesiId, 'jenis_dokumen' => 'fotokopi_ktp']
        );

        $alasan = ($statusOcr === 'tidak_terbaca')
            ? 'Foto tidak terbaca dengan jelas (mungkin buram, gelap, atau bukan foto KTP).'
            : 'NIK pada foto KTP tidak sesuai dengan data yang terdaftar.';

        $notifier->kirim(
            $this->noWa,
            "⚠️ *Foto KTP ditolak*\n\n{$alasan}\n\n"
            . "Silakan upload ulang foto KTP yang jelas dan sesuai:\n"
            . "{$linkUploadUlang}\n"
            . "(Tautan berlaku selama 60 menit)"
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
