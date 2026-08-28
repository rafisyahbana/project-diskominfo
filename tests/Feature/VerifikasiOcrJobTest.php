<?php

namespace Tests\Feature;

use App\Jobs\VerifikasiOcrJob;
use App\Models\DokumenPermohonan;
use App\Models\SesiVerifikasi;
use App\Services\OcrService;
use App\Contracts\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

/**
 * Test FEATURE untuk VerifikasiOcrJob.
 *
 * OcrService di-mock agar test tidak bergantung pada Tesseract binary
 * dan berjalan cepat. Test ini membuktikan LOGIKA BISNIS job:
 * - Update kolom status_ocr di DB sesuai hasil perbandingan NIK
 * - Kirim notifikasi WA yang NETRAL (tidak memblokir warga, tidak membocorkan detail teknis)
 * - Status ok/gagal/tidak_terbaca tersimpan dengan benar untuk audit petugas
 */
class VerifikasiOcrJobTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumenDanSesi(
        string $nikSesi,
        string $noWa = '628111',
        string $jenisDokumen = 'fotokopi_ktp'
    ): array {
        $sesi = SesiVerifikasi::create([
            'no_wa'           => $noWa,
            'nik'             => $nikSesi,
            'otp_hash'        => 'dummy',
            'status'          => 'verified',
            'percobaan_gagal' => 0,
            'expired_at'      => now()->addMinutes(5),
            'berlaku_hingga'  => now()->addMinutes(90),
        ]);

        Storage::fake('local');
        $dokumen = DokumenPermohonan::create([
            'no_wa'         => $noWa,
            'jenis_dokumen' => $jenisDokumen,
            'path_file'     => 'dokumen/' . $noWa . '/ktp.png',
            'id_permohonan' => null,
            'status'        => 'aktif',
        ]);

        return [$sesi, $dokumen];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1: NIK cocok → status_ocr = 'ok', pesan WA netral dikirim
    // ─────────────────────────────────────────────────────────────────────────
    public function test_nik_cocok_set_status_ok_dan_kirim_notifikasi_netral()
    {
        $nik = '3517231234560001';
        [$sesi, $dokumen] = $this->buatDokumenDanSesi($nik);

        // Mock OcrService: ekstrak() mengembalikan teks dengan NIK yang benar
        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')->once()->andReturn("NIK {$nik}");
        $ocrMock->shouldReceive('ekstrakNik')->once()->andReturn($nik);
        $this->app->instance(OcrService::class, $ocrMock);

        // Mock notifier: harus dipanggil 1×, pesannya netral
        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')
            ->once()
            ->with('628111', Mockery::on(function ($pesan) {
                // Pesan HARUS netral — tidak menyebut "cocok", "verifikasi berhasil",
                // maupun hint apapun yang bisa membingungkan jika NIK sebenarnya tidak cocok
                return str_contains($pesan, 'sedang diproses');
            }));
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync($dokumen->id, $sesi->id, '628111');

        $dokumen->refresh();
        $this->assertEquals('ok', $dokumen->status_ocr);
        $this->assertEquals($nik, $dokumen->nik_terbaca);
        $this->assertNotNull($dokumen->ocr_diproses_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2: NIK TIDAK cocok → status_ocr = 'gagal', pesan WA TETAP netral
    //
    // Ini adalah test kunci: membuktikan warga tidak pernah mendapat pesan
    // "dokumen tidak cocok" atau instruksi apapun untuk upload ulang.
    // Hanya flag di DB untuk petugas admin.
    // ─────────────────────────────────────────────────────────────────────────
    public function test_nik_tidak_cocok_set_status_gagal_pesan_wa_tetap_netral()
    {
        $nikAsli   = '3517231234560001';
        $nikSalah  = '9999991234560001'; // NIK berbeda dari KTP palsu
        [$sesi, $dokumen] = $this->buatDokumenDanSesi($nikAsli);

        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')->once()->andReturn("NIK {$nikSalah}");
        $ocrMock->shouldReceive('ekstrakNik')->once()->andReturn($nikSalah);
        $this->app->instance(OcrService::class, $ocrMock);

        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')
            ->once()
            ->with('628111', Mockery::on(function ($pesan) {
                // Pesan harus netral — tidak menyebut "tidak cocok", "upload ulang", "salah"
                $bolehAda     = str_contains($pesan, 'sedang diproses');
                $tidakBolehAda = str_contains($pesan, 'tidak cocok')
                             || str_contains($pesan, 'upload ulang')
                             || str_contains($pesan, 'coba lagi')
                             || str_contains($pesan, 'salah')
                             || str_contains($pesan, 'ditolak');
                return $bolehAda && !$tidakBolehAda;
            }));
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync($dokumen->id, $sesi->id, '628111');

        $dokumen->refresh();
        $this->assertEquals('gagal', $dokumen->status_ocr,
            'status_ocr harus "gagal" saat NIK tidak cocok — untuk flag petugas admin');
        $this->assertEquals($nikSalah, $dokumen->nik_terbaca,
            'nik_terbaca harus berisi NIK dari OCR (bukan NIK asli) — untuk audit');
        $this->assertNotNull($dokumen->ocr_diproses_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3: OCR gagal baca (ekstrak returns null) → status = tidak_terbaca,
    //         TIDAK ada notifikasi WA (tidak mengganggu warga dengan pesan teknis)
    // ─────────────────────────────────────────────────────────────────────────
    public function test_ocr_gagal_baca_set_status_tidak_terbaca_tanpa_notifikasi()
    {
        [$sesi, $dokumen] = $this->buatDokumenDanSesi('3517231234560001');

        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')->once()->andReturn(null); // Tesseract error
        $ocrMock->shouldNotReceive('ekstrakNik');
        $this->app->instance(OcrService::class, $ocrMock);

        // Notifier TIDAK boleh dipanggil sama sekali
        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldNotReceive('kirim');
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync($dokumen->id, $sesi->id, '628111');

        $dokumen->refresh();
        $this->assertEquals('tidak_terbaca', $dokumen->status_ocr);
        $this->assertNull($dokumen->nik_terbaca);
        $this->assertNotNull($dokumen->ocr_diproses_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4: Dokumen bukan fotokopi_ktp → langsung status 'ok', tidak ada OCR
    // ─────────────────────────────────────────────────────────────────────────
    public function test_dokumen_non_ktp_langsung_ok_tanpa_ocr()
    {
        [$sesi, $dokumen] = $this->buatDokumenDanSesi('3517231234560001', '628111', 'fotokopi_kk');

        // OcrService tidak boleh dipanggil sama sekali untuk dokumen non-KTP
        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldNotReceive('ekstrak');
        $ocrMock->shouldNotReceive('ekstrakNik');
        $this->app->instance(OcrService::class, $ocrMock);

        // Notifier juga tidak dipanggil untuk non-KTP
        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldNotReceive('kirim');
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync($dokumen->id, $sesi->id, '628111');

        $dokumen->refresh();
        $this->assertEquals('ok', $dokumen->status_ocr);
        $this->assertNotNull($dokumen->ocr_diproses_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 5: Dokumen sudah terhapus (race condition) → job gracefully return
    // ─────────────────────────────────────────────────────────────────────────
    public function test_job_gracefully_return_jika_dokumen_tidak_ditemukan()
    {
        // Dispatch dengan ID yang tidak ada di DB
        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldNotReceive('ekstrak');
        $this->app->instance(OcrService::class, $ocrMock);

        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldNotReceive('kirim');
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        // Job harus selesai tanpa exception — kita verifikasi tidak ada record baru
        VerifikasiOcrJob::dispatchSync(
            'id-yang-tidak-ada-sama-sekali',
            'sesi-id-dummy',
            '628111'
        );

        // Tidak ada DokumenPermohonan yang tersimpan atau diubah
        $this->assertDatabaseCount('dokumen_permohonans', 0);
    }
}
