<?php

namespace Tests\Unit;

use App\Services\OcrService;
use Tests\TestCase; // ← pakai TestCase Laravel, bukan PHPUnit\TestCase, agar config() resolver aktif

/**
 * Test UNIT untuk OcrService.
 *
 * Menggunakan Tesseract binary ASLI — membuktikan integrasi end-to-end nyata.
 * Gambar dibuat programatik via PHP GD (deterministik, kontras tinggi).
 * Seluruh test di-SKIP otomatis jika binary Tesseract tidak tersedia.
 */
class OcrServiceTest extends TestCase
{
    protected OcrService $ocr;
    protected array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ocr = new OcrService();

        if (!$this->ocr->isAvailable()) {
            $this->markTestSkipped(
                'Tesseract binary tidak ditemukan. Install Tesseract dan set TESSERACT_PATH di .env.'
            );
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function buatGambarDenganTeks(string $teks, int $width = 600, int $height = 120): string
    {
        $img   = imagecreatetruecolor($width, $height);
        $putih = imagecolorallocate($img, 255, 255, 255);
        $hitam = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $putih);
        imagestring($img, 5, 20, 50, $teks, $hitam);

        $path = tempnam(sys_get_temp_dir(), 'ocr_test_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Buat gambar yang mensimulasikan baris NIK di KTP.
     * Menggunakan ukuran yang lebih besar agar piksel huruf lebih jelas dibaca Tesseract.
     */
    private function buatGambarNik(string $nik): string
    {
        // Skala 4× agar font GD terbaca Tesseract dengan lebih baik
        $scale  = 4;
        $img    = imagecreatetruecolor(800 * $scale, 150 * $scale);
        $putih  = imagecolorallocate($img, 255, 255, 255);
        $hitam  = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $putih);

        // Gambar NIK dengan imagestring lalu scale-up supaya piksel lebih jelas
        // Font 5 di resolusi 4× = sekitar 60px tinggi — cukup besar untuk OCR
        imagestring($img, 5, 20 * $scale, 50 * $scale, $nik, $hitam);

        $path = tempnam(sys_get_temp_dir(), 'ocr_nik_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        $this->tempFiles[] = $path;
        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1: Binary tersedia
    // ─────────────────────────────────────────────────────────────────────────
    public function test_tesseract_binary_tersedia_dan_berjalan()
    {
        $this->assertTrue($this->ocr->isAvailable());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2: ekstrak() mengembalikan teks dari gambar
    // ─────────────────────────────────────────────────────────────────────────
    public function test_ekstrak_mengembalikan_teks_dari_gambar_sintetis()
    {
        $path  = $this->buatGambarDenganTeks('HELLO 12345');
        $hasil = $this->ocr->ekstrak($path);

        $this->assertNotNull($hasil);
        $this->assertNotEmpty(trim($hasil));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3: ekstrak() null jika file tidak ada
    // ─────────────────────────────────────────────────────────────────────────
    public function test_ekstrak_mengembalikan_null_jika_file_tidak_ditemukan()
    {
        $this->assertNull($this->ocr->ekstrak('/tidak/ada.png'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4–6: ekstrakNik() — pure unit, tidak butuh Tesseract
    // ─────────────────────────────────────────────────────────────────────────
    public function test_ekstrakNik_menemukan_16_digit_dari_teks_mentah()
    {
        $teks = "Provinsi Jawa Tengah\nNIK 3517231234560001\nNama BUDI";
        $this->assertEquals('3517231234560001', $this->ocr->ekstrakNik($teks));
    }

    public function test_ekstrakNik_mengembalikan_null_jika_tidak_ada_16_digit()
    {
        $this->assertNull($this->ocr->ekstrakNik("Nama BUDI SANTOSO\nAlamat Jl Merdeka"));
    }

    public function test_ekstrakNik_abaikan_angka_kurang_dari_16_digit()
    {
        $this->assertNull($this->ocr->ekstrakNik("Kode 12345\nNo HP 081234567890\nTahun 2025"));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 7: Pipeline FULL — gambar → Tesseract asli → ekstrakNik → 16 digit
    //
    // Menggunakan gambar upscaled (4×) agar font GD lebih jelas terbaca OCR.
    // Assertion hanya memeriksa FORMAT (16 digit angka), bukan nilai persis,
    // karena noise OCR pada font bitmap built-in GD bisa mengubah beberapa digit.
    // ─────────────────────────────────────────────────────────────────────────
    public function test_pipeline_full_ocr_dari_gambar_sintetis_berhasil_ekstrak_nik()
    {
        $nikAsli = '3517231234560001';
        $path    = $this->buatGambarNik($nikAsli);

        $teks = $this->ocr->ekstrak($path);
        $this->assertNotNull($teks, 'Tesseract harus bisa membaca gambar sintetis upscaled');

        $nikTerbaca = $this->ocr->ekstrakNik($teks);
        $this->assertNotNull($nikTerbaca,
            "Tesseract membaca: \"" . trim($teks) . "\"\n"
            . "Harus berhasil mengekstrak pola 16 digit"
        );
        $this->assertEquals($nikAsli, $nikTerbaca, "NIK yang dibaca OCR harus sama persis dengan yang digambar");
    }
}
