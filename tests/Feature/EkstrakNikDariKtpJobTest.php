<?php

namespace Tests\Feature;

use App\Jobs\EkstrakNikDariKtpJob;
use App\Models\DokumenPermohonan;
use App\Models\PercakapanState;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EkstrakNikDariKtpJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jika OCR berhasil membaca NIK, simpan ke nik_terbaca dengan status_ocr = ok.
     */
    public function test_ocr_berhasil_menyimpan_nik_terbaca()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('ktp.jpg');
        $path = $file->store('dokumen/6281357510843', 'local');

        $dokumen = DokumenPermohonan::create([
            'no_wa'         => '6281357510843',
            'jenis_dokumen' => 'fotokopi_ktp',
            'path_file'     => $path,
            'status'        => 'aktif',
        ]);

        $ocrMock = $this->mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')
                ->once()
                ->andReturn('NIK: 1207261511990013 Nama: Test User');
        $ocrMock->shouldReceive('ekstrakNik')
                ->once()
                ->withAnyArgs()
                ->andReturn('1207261511990013');

        EkstrakNikDariKtpJob::dispatchSync($dokumen->id, '6281357510843');

        $this->assertDatabaseHas('dokumen_permohonans', [
            'id'         => $dokumen->id,
            'nik_terbaca' => '1207261511990013',
            'status_ocr' => 'ok',
        ]);
    }

    /**
     * Jika OCR tidak menemukan NIK dalam teks (foto bukan KTP),
     * status_ocr = tidak_terbaca dan nik_terbaca tetap null.
     */
    public function test_ocr_tidak_menemukan_nik_set_tidak_terbaca()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('random.jpg');
        $path = $file->store('dokumen/6281357510843', 'local');

        $dokumen = DokumenPermohonan::create([
            'no_wa'         => '6281357510843',
            'jenis_dokumen' => 'fotokopi_ktp',
            'path_file'     => $path,
            'status'        => 'aktif',
        ]);

        $ocrMock = $this->mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')
                ->once()
                ->andReturn('Lorem ipsum dolor sit amet'); // Teks ada tapi bukan KTP
        $ocrMock->shouldReceive('ekstrakNik')
                ->once()
                ->withAnyArgs()
                ->andReturn(null); // Tidak ditemukan NIK

        EkstrakNikDariKtpJob::dispatchSync($dokumen->id, '6281357510843');

        $this->assertDatabaseHas('dokumen_permohonans', [
            'id'         => $dokumen->id,
            'status_ocr' => 'tidak_terbaca',
        ]);
        $this->assertDatabaseMissing('dokumen_permohonans', [
            'id'          => $dokumen->id,
            'nik_terbaca' => '1207261511990013',
        ]);
    }

    /**
     * Jika OCR mengembalikan null (error fatal, misal file corrupt),
     * status_ocr = tidak_terbaca.
     */
    public function test_ocr_error_fatal_set_tidak_terbaca()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('ktp.jpg');
        $path = $file->store('dokumen/6281357510843', 'local');

        $dokumen = DokumenPermohonan::create([
            'no_wa'         => '6281357510843',
            'jenis_dokumen' => 'fotokopi_ktp',
            'path_file'     => $path,
            'status'        => 'aktif',
        ]);

        $ocrMock = $this->mock(OcrService::class);
        $ocrMock->shouldReceive('ekstrak')
                ->once()
                ->andReturn(null); // Error fatal OCR

        EkstrakNikDariKtpJob::dispatchSync($dokumen->id, '6281357510843');

        $this->assertDatabaseHas('dokumen_permohonans', [
            'id'         => $dokumen->id,
            'status_ocr' => 'tidak_terbaca',
        ]);
    }
}
