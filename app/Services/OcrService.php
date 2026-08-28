<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class OcrService
{
    protected string $tesseractPath;
    protected string $lang;
    protected ?string $tessdata;

    public function __construct()
    {
        $this->tesseractPath = config('services.tesseract.path', 'tesseract');
        $this->lang          = config('services.tesseract.lang', 'ind+eng');
        $this->tessdata      = config('services.tesseract.tessdata');
    }

    /**
     * Jalankan OCR pada file gambar dan kembalikan teks hasil ekstraksi.
     * Return null jika Tesseract tidak ditemukan, error, atau output kosong.
     *
     * @param  string $pathFile  Path absolut ke file gambar di storage
     * @return string|null       Teks hasil OCR (bisa berisi banyak baris), atau null jika gagal
     */
    public function ekstrak(string $pathFile): ?string
    {
        if (!file_exists($pathFile)) {
            Log::warning('[OcrService] File tidak ditemukan', ['path' => $pathFile]);
            return null;
        }

        // Tesseract menulis hasilnya ke file output, kita pakai stdout langsung
        // dengan menentukan output name 'stdout'
        $cmd = $this->buildCommand($pathFile);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin (tidak dipakai)
            1 => ['pipe', 'w'],  // stdout: hasil OCR
            2 => ['pipe', 'w'],  // stderr: pesan error Tesseract
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            Log::error('[OcrService] Gagal membuka proses Tesseract', ['cmd' => $cmd]);
            return null;
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::warning('[OcrService] Tesseract keluar dengan kode error', [
                'exit_code' => $exitCode,
                'stderr'    => trim($stderr),
                'path'      => $pathFile,
            ]);
            return null;
        }

        $hasil = trim($stdout);

        if ($hasil === '') {
            Log::info('[OcrService] OCR menghasilkan teks kosong', ['path' => $pathFile]);
            return null;
        }

        Log::debug('[OcrService] OCR berhasil', [
            'path'   => $pathFile,
            'chars'  => strlen($hasil),
            'preview' => mb_strimwidth($hasil, 0, 100, '…'),
        ]);

        return $hasil;
    }

    /**
     * Ekstrak NIK (16 digit angka berurutan) dari teks hasil OCR.
     * NIK pada KTP Indonesia selalu 16 digit, di dalam blok teks yang lebih panjang.
     *
     * @param  string $teks  Output mentah dari ekstrak()
     * @return string|null   NIK 16 digit pertama yang ditemukan, atau null
     */
    public function ekstrakNik(string $teks): ?string
    {
        // Hapus semua spasi/newline di dalam teks terlebih dulu
        // karena OCR kadang memisahkan digit NIK dengan spasi
        $bersih = preg_replace('/\s+/', '', $teks);

        // Cari pola 16 digit angka berurutan
        if (preg_match('/\b(\d{16})\b/', $bersih, $matches)) {
            return $matches[1];
        }

        // Fallback: cari tanpa word boundary (untuk kasus OCR menggabungkan dengan karakter lain)
        if (preg_match('/(\d{16})/', $bersih, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Periksa apakah binary Tesseract tersedia dan bisa dijalankan.
     * Berguna untuk unit test yang perlu skip jika Tesseract tidak terpasang.
     */
    public function isAvailable(): bool
    {
        $cmd = escapeshellarg($this->tesseractPath) . ' --version 2>&1';
        exec($cmd, $output, $exitCode);
        return $exitCode === 0;
    }

    private function buildCommand(string $pathFile): string
    {
        $binary  = escapeshellarg($this->tesseractPath);
        $input   = escapeshellarg($pathFile);
        $lang    = escapeshellarg($this->lang);

        $cmd = "{$binary} {$input} stdout -l {$lang} --oem 1 --psm 6";

        if ($this->tessdata) {
            $tessdata = escapeshellarg($this->tessdata);
            $cmd .= " --tessdata-dir {$tessdata}";
        }

        return $cmd;
    }
}
