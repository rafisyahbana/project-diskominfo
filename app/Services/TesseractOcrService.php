<?php

namespace App\Services;

use RuntimeException;

/**
 * TesseractOcrService
 *
 * Service untuk mengintegrasikan Tesseract OCR ke dalam project Laravel.
 * Mendukung OCR untuk dokumen Indonesia (KTP, NPWP, Ijazah, dll).
 *
 * Prasyarat:
 *  - Tesseract OCR sudah terinstal di Windows (lihat panduan_tesseract_ocr.md)
 *  - Path Tesseract sudah ditambahkan ke Environment Variables
 *  - File bahasa `ind.traineddata` dan `eng.traineddata` tersedia
 *
 * Konfigurasi di .env:
 *  TESSERACT_PATH="C:\Program Files\Tesseract-OCR\tesseract.exe"
 *  TESSDATA_PREFIX="C:\Program Files\Tesseract-OCR\tessdata"
 *  TESSERACT_LANG=ind+eng
 */
class TesseractOcrService
{
    /** Path absolut ke executable tesseract.exe */
    protected string $tesseractPath;

    /** Path ke folder tessdata (berisi file bahasa .traineddata) */
    protected string $tessdataPrefix;

    /** Kode bahasa OCR, contoh: 'ind', 'eng', 'ind+eng' */
    protected string $defaultLang;

    public function __construct()
    {
        $this->tesseractPath = env(
            'TESSERACT_PATH',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'
        );

        $this->tessdataPrefix = env(
            'TESSDATA_PREFIX',
            'C:\\Program Files\\Tesseract-OCR\\tessdata'
        );

        $this->defaultLang = env('TESSERACT_LANG', 'ind+eng');
    }

    // -------------------------------------------------------------------------
    // PUBLIC API
    // -------------------------------------------------------------------------

    /**
     * Baca teks dari file gambar menggunakan Tesseract OCR.
     *
     * @param  string      $imagePath  Path absolut ke file gambar (jpg, png, bmp, tiff)
     * @param  string|null $lang       Kode bahasa, contoh: 'ind', 'eng', 'ind+eng'
     * @param  int         $oem        OCR Engine Mode: 0=Legacy, 1=LSTM (default), 3=Auto
     * @param  int         $psm        Page Segmentation Mode: 6=Block teks, 7=Single line
     * @return string                  Teks hasil OCR (sudah di-trim)
     *
     * @throws RuntimeException Jika file tidak ditemukan atau Tesseract gagal
     */
    public function readText(
        string $imagePath,
        ?string $lang = null,
        int $oem = 1,
        int $psm = 6
    ): string {
        $this->validateImageFile($imagePath);

        $lang   = $lang ?? $this->defaultLang;
        $cmd    = $this->buildCommand($imagePath, $lang, $oem, $psm);
        $output = [];
        $exitCode = 0;

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $detail = implode(' | ', $output);
            throw new RuntimeException(
                "Tesseract OCR gagal (exit code: {$exitCode}). Detail: {$detail}"
            );
        }

        return trim(implode(PHP_EOL, $output));
    }

    /**
     * Ekstrak NIK dari gambar KTP.
     *
     * Menggunakan PSM 6 (blok teks) dengan regex untuk menemukan 16 digit NIK.
     *
     * @param  string $imagePath Path ke file gambar KTP
     * @return string|null       NIK 16 digit, atau null jika tidak ditemukan
     */
    public function extractNik(string $imagePath): ?string
    {
        $rawText = $this->readText($imagePath, 'ind+eng', oem: 1, psm: 6);

        // Cari pola 16 digit angka berurutan (NIK Indonesia)
        if (preg_match('/\b(\d{16})\b/', $rawText, $matches)) {
            return $matches[1];
        }

        // Fallback: cari angka 16 digit dengan spasi atau titik di antara
        $cleaned = preg_replace('/[\s.\-]/', '', $rawText);
        if (preg_match('/(\d{16})/', $cleaned, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Baca teks dari satu baris (cocok untuk NIK, nomor KK, NPWP, dsb).
     *
     * @param  string $imagePath Path ke file gambar
     * @return string            Teks hasil OCR (single line)
     */
    public function readSingleLine(string $imagePath): string
    {
        return $this->readText($imagePath, lang: 'ind+eng', oem: 1, psm: 7);
    }

    /**
     * Cek apakah Tesseract terinstal dan dapat diakses oleh PHP.
     *
     * @return array{ok: bool, version: string|null, message: string}
     */
    public function checkInstallation(): array
    {
        $escapedPath = $this->escapeArg($this->tesseractPath);
        $cmd         = "{$escapedPath} --version 2>&1";
        $output      = [];
        $exitCode    = 0;

        exec($cmd, $output, $exitCode);

        if ($exitCode === 0) {
            return [
                'ok'      => true,
                'version' => $output[0] ?? 'unknown',
                'message' => 'Tesseract OCR terdeteksi dan berfungsi normal.',
            ];
        }

        return [
            'ok'      => false,
            'version' => null,
            'message' => 'Tesseract OCR tidak ditemukan. Pastikan sudah terinstal dan PATH sudah dikonfigurasi.',
        ];
    }

    /**
     * Daftar bahasa yang tersedia di instalasi Tesseract.
     *
     * @return string[] Array kode bahasa, contoh: ['eng', 'ind']
     */
    public function listAvailableLanguages(): array
    {
        $escapedPath = $this->escapeArg($this->tesseractPath);
        $cmd         = "{$escapedPath} --list-langs 2>&1";
        $output      = [];

        exec($cmd, $output);

        // Baris pertama adalah header "List of available..."
        return array_values(array_filter(
            array_slice($output, 1),
            fn($line) => trim($line) !== ''
        ));
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Bangun command Tesseract yang aman untuk dieksekusi.
     */
    private function buildCommand(
        string $imagePath,
        string $lang,
        int $oem,
        int $psm
    ): string {
        $tessExe  = $this->escapeArg($this->tesseractPath);
        $imgArg   = escapeshellarg($imagePath);
        $tessdata = escapeshellarg($this->tessdataPrefix);

        // Set TESSDATA_PREFIX via environment agar Tesseract menemukan file bahasa
        $envPrefix = "TESSDATA_PREFIX={$tessdata}";

        // Di Windows, set environment variable berbeda caranya
        if (PHP_OS_FAMILY === 'Windows') {
            return "{$tessExe} {$imgArg} stdout -l {$lang} --oem {$oem} --psm {$psm} 2>&1";
        }

        return "TESSDATA_PREFIX={$this->tessdataPrefix} {$tessExe} {$imgArg} stdout -l {$lang} --oem {$oem} --psm {$psm} 2>&1";
    }

    /**
     * Validasi file gambar sebelum dikirim ke Tesseract.
     *
     * @throws RuntimeException
     */
    private function validateImageFile(string $imagePath): void
    {
        if (! file_exists($imagePath)) {
            throw new RuntimeException("File gambar tidak ditemukan: {$imagePath}");
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif', 'gif', 'webp'];
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        if (! in_array($ext, $allowedExtensions, strict: true)) {
            throw new RuntimeException(
                "Format file tidak didukung: .{$ext}. "
                . 'Format yang didukung: ' . implode(', ', $allowedExtensions)
            );
        }
    }

    /**
     * Escape path untuk digunakan dalam shell command di Windows.
     * Menggunakan tanda kutip ganda karena path Windows sering mengandung spasi.
     */
    private function escapeArg(string $path): string
    {
        // Normalisasi: ganti backslash ke forward slash untuk konsistensi
        $normalized = str_replace('\\', '/', $path);
        return '"' . $normalized . '"';
    }
}
