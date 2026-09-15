<?php

namespace Tests\Feature;

use App\Jobs\VerifikasiOcrJob;
use App\Models\DokumenPermohonan;
use App\Models\PercakapanState;
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
 * OcrService di-mock agar test tidak bergantung pada Tesseract binary.
 * Perilaku baru:
 * - NIK cocok      → status 'ok', notif sukses ke warga
 * - NIK tidak cocok→ status 'gagal', dokumen ditolak, link upload ulang dikirim
 * - OCR null       → status 'tidak_terbaca', link upload ulang dikirim
 * - non-KTP        → status 'ok' langsung, tanpa OCR
 */
class VerifikasiOcrJobTest extends TestCase
{
    use RefreshDatabase;

    private function buatDokumenDanSesi(
        string $nikSesi,
        string $noWa = '628111',
        string $jenisDokumen = 'fotokopi_ktp'
    ): array {
        Storage::fake('local');

        // Buat file dummy agar Storage::delete() tidak error
        Storage::disk('local')->put('dokumen/' . $noWa . '/ktp.png', 'dummy');

        $sesi = SesiVerifikasi::create([
            'no_wa'           => $noWa,
            'nik'             => $nikSesi,
            'otp_hash'        => 'dummy',
            'status'          => 'verified',
            'percobaan_gagal' => 0,
            'expired_at'      => now()->addMinutes(5),
            'berlaku_hingga'  => now()->addMinutes(90),
        ]);

        $dokumen = DokumenPermohonan::create([
            'no_wa'         => $noWa,
            'jenis_dokumen' => $jenisDokumen,
            'path_file'     => 'dokumen/' . $noWa . '/ktp.png',
            'id_permohonan' => null,
            'status'        => 'aktif',
        ]);

        // Buat PercakapanState dengan fotokopi_ktp terdaftar
        PercakapanState::create([
            'no_wa'            => $noWa,
            'langkah'          => 'menunggu_dokumen',
            'dokumen_diterima' => ['fotokopi_ktp' => $dokumen->id],
        ]);

        return [$sesi, $dokumen];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1: NIK cocok → status_ocr = 'ok', notifikasi sukses dikirim
    // ─────────────────────────────────────────────────────────────────────────
    public function test_nik_cocok_set_status_ok_dan_kirim_notifikasi_sukses()
    {
        $nik = '3517231234560001';
        [$sesi, $dokumen] = $this->buatDokumenDanSesi($nik);

        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')->once()->andReturn("NIK {$nik}");
        $ocrMock->shouldReceive('ekstrakNik')->once()->andReturn($nik);
        $this->app->instance(OcrService::class, $ocrMock);

        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')
            ->once()
            ->with('628111', Mockery::on(function ($pesan) {
                return str_contains($pesan, 'berhasil diverifikasi');
            }));
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync($dokumen->id, $sesi->id, '628111');

        $dokumen->refresh();
        $this->assertEquals('ok', $dokumen->status_ocr);
        $this->assertEquals($nik, $dokumen->nik_terbaca);
        $this->assertNotNull($dokumen->ocr_diproses_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2: NIK TIDAK cocok → status 'gagal', ditolak, link upload ulang dikirim
    // ─────────────────────────────────────────────────────────────────────────
    public function test_nik_tidak_cocok_tolak_dokumen_dan_kirim_link_upload_ulang()
    {
        $nikAsli  = '3517231234560001';
        $nikSalah = '9999991234560001';
        [$sesi, $dokumen] = $this->buatDokumenDanSesi($nikAsli);

        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')->once()->andReturn("NIK {$nikSalah}");
        $ocrMock->shouldReceive('ekstrakNik')->once()->andReturn($nikSalah);
        $this->app->instance(OcrService::class, $ocrMock);

        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')
            ->once()
            ->with('628111', Mockery::on(function ($pesan) {
                return str_contains($pesan, 'ditolak')
                    && str_contains($pesan, 'upload ulang');
            }));
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync($dokumen->id, $sesi->id, '628111');

        $dokumen->refresh();
        $this->assertEquals('gagal', $dokumen->status_ocr);
        $this->assertEquals($nikSalah, $dokumen->nik_terbaca);
        $this->assertEquals('ditolak', $dokumen->status);
        $this->assertNotNull($dokumen->ocr_diproses_at);

        // Slot fotokopi_ktp harus dihapus dari state agar warga bisa upload ulang
        $state = PercakapanState::where('no_wa', '628111')->first();
        $this->assertArrayNotHasKey('fotokopi_ktp', $state->dokumen_diterima ?? []);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3: OCR gagal baca (null) → status 'tidak_terbaca', link upload ulang dikirim
    // ─────────────────────────────────────────────────────────────────────────
    public function test_ocr_gagal_baca_set_status_tidak_terbaca_dan_kirim_link_upload_ulang()
    {
        [$sesi, $dokumen] = $this->buatDokumenDanSesi('3517231234560001');

        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')->once()->andReturn(null);
        $ocrMock->shouldNotReceive('ekstrakNik');
        $this->app->instance(OcrService::class, $ocrMock);

        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')
            ->once()
            ->with('628111', Mockery::on(function ($pesan) {
                return str_contains($pesan, 'ditolak')
                    && str_contains($pesan, 'upload ulang');
            }));
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync($dokumen->id, $sesi->id, '628111');

        $dokumen->refresh();
        $this->assertEquals('tidak_terbaca', $dokumen->status_ocr);
        $this->assertNull($dokumen->nik_terbaca);
        $this->assertEquals('ditolak', $dokumen->status);
        $this->assertNotNull($dokumen->ocr_diproses_at);

        // Slot fotokopi_ktp harus dihapus dari state agar warga bisa upload ulang
        $state = PercakapanState::where('no_wa', '628111')->first();
        $this->assertArrayNotHasKey('fotokopi_ktp', $state->dokumen_diterima ?? []);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4: Dokumen bukan fotokopi_ktp → langsung status 'ok', tidak ada OCR
    // ─────────────────────────────────────────────────────────────────────────
    public function test_dokumen_non_ktp_langsung_ok_tanpa_ocr()
    {
        [$sesi, $dokumen] = $this->buatDokumenDanSesi('3517231234560001', '628111', 'fotokopi_kk');

        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldNotReceive('ekstrak');
        $ocrMock->shouldNotReceive('ekstrakNik');
        $this->app->instance(OcrService::class, $ocrMock);

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
        Storage::fake('local');

        $ocrMock = Mockery::mock(OcrService::class);
        $ocrMock->shouldNotReceive('ekstrak');
        $this->app->instance(OcrService::class, $ocrMock);

        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldNotReceive('kirim');
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        VerifikasiOcrJob::dispatchSync(
            'id-yang-tidak-ada-sama-sekali',
            'sesi-id-dummy',
            '628111'
        );

        $this->assertDatabaseCount('dokumen_permohonans', 0);
    }
}
