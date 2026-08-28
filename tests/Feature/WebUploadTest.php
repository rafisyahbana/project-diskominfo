<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\PercakapanState;
use App\Models\SesiVerifikasi;
use App\Models\DokumenPermohonan;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Contracts\WhatsAppNotifier;
use Carbon\Carbon;
use Mockery;

class WebUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ── Helper: buat sesi verified + state menunggu_dokumen ───────────────────
    private function buatSesiDanState(
        string $noWa,
        string $jenisSurat,
        array  $formSementara,
        array  $dokumenDiterima = []
    ): array {
        $sesi = SesiVerifikasi::create([
            'no_wa'           => $noWa,
            'nik'             => '1234567890123456',
            'otp_hash'        => 'dummy',
            'status'          => 'verified',
            'percobaan_gagal' => 0,
            'expired_at'      => now()->addMinutes(5),
            'berlaku_hingga'  => now()->addMinutes(90),
        ]);

        $state = PercakapanState::create([
            'no_wa'               => $noWa,
            'langkah'             => 'menunggu_dokumen',
            'sesi_id'             => $sesi->id,
            'jenis_surat_dipilih' => $jenisSurat,
            'form_sementara'      => $formSementara,
            'dokumen_diterima'    => $dokumenDiterima,
        ]);

        return [$sesi, $state];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1: Akses tanpa signature valid ditolak
    // ─────────────────────────────────────────────────────────────────────────
    public function test_akses_form_upload_tanpa_signature_valid_ditolak()
    {
        $response = $this->get('/upload/dokumen/628111/some-id/fotokopi_ktp');
        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2: Akses ditolak jika sesi tidak verified
    // ─────────────────────────────────────────────────────────────────────────
    public function test_akses_form_ditolak_jika_sesi_tidak_valid()
    {
        $sesi = SesiVerifikasi::create([
            'no_wa'           => '628111',
            'nik'             => '1234567890123456',
            'otp_hash'        => 'dummy',
            'status'          => 'pending', // NOT verified
            'percobaan_gagal' => 0,
            'expired_at'      => now()->addMinutes(5),
            'berlaku_hingga'  => null,
        ]);

        $url = URL::temporarySignedRoute(
            'upload.form',
            now()->addMinutes(60),
            ['no_wa' => '628111', 'sesi_id' => $sesi->id, 'jenis_dokumen' => 'fotokopi_ktp']
        );

        $response = $this->get($url);
        $response->assertStatus(403);
        $response->assertSee('Sesi Anda tidak valid atau telah kedaluwarsa');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3: Akses ditolak jika dokumen sudah diunggah (409)
    // ─────────────────────────────────────────────────────────────────────────
    public function test_akses_form_ditolak_jika_dokumen_sudah_diunggah()
    {
        [$sesi] = $this->buatSesiDanState(
            '628111', 'domisili',
            ['nama_lengkap' => 'Budi'],
            ['fotokopi_ktp' => 'some-doc-id'] // KTP sudah ada
        );

        $url = URL::temporarySignedRoute(
            'upload.form',
            now()->addMinutes(60),
            ['no_wa' => '628111', 'sesi_id' => $sesi->id, 'jenis_dokumen' => 'fotokopi_ktp']
        );

        $response = $this->get($url);
        $response->assertStatus(409);
        $response->assertSee('Dokumen ini sudah diunggah sebelumnya');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4: Upload sukses memicu notifikasi WA untuk dokumen berikutnya
    // ─────────────────────────────────────────────────────────────────────────
    public function test_sukses_upload_memicu_whatsapp_lanjutan()
    {
        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')
            ->once()
            ->with('628111', Mockery::on(function ($pesan) {
                return str_contains($pesan, 'berhasil diterima') && str_contains($pesan, 'Selanjutnya');
            }));
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        [$sesi] = $this->buatSesiDanState(
            '628111', 'sktm',
            ['keperluan' => 'Sekolah']
        );

        $url = URL::temporarySignedRoute(
            'upload.process',
            now()->addMinutes(60),
            ['no_wa' => '628111', 'sesi_id' => $sesi->id, 'jenis_dokumen' => 'fotokopi_ktp']
        );

        $response = $this->post($url, ['file' => UploadedFile::fake()->image('ktp.jpg')]);

        $response->assertStatus(200);
        $response->assertSee('Upload Berhasil');

        $this->assertDatabaseHas('dokumen_permohonans', [
            'no_wa'         => '628111',
            'jenis_dokumen' => 'fotokopi_ktp',
        ]);

        $state = PercakapanState::where('no_wa', '628111')->first();
        $this->assertArrayHasKey('fotokopi_ktp', $state->dokumen_diterima);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 5 (BARU – gap #1): Full 2-dokumen flow → state pindah ke menunggu_konfirmasi
    //
    // Skenario: domisili butuh fotokopi_ktp + fotokopi_kk.
    // Upload KTP → state MASIH menunggu_dokumen, KTP tersimpan.
    // Upload KK  → state PINDAH ke menunggu_konfirmasi, keduanya tersimpan di DB.
    // ─────────────────────────────────────────────────────────────────────────
    public function test_upload_dua_dokumen_berurutan_pindahkan_state_ke_menunggu_konfirmasi()
    {
        // Notifier dipanggil 2×: setelah KTP (link KK), setelah KK (ringkasan)
        $notifierMock = Mockery::mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')->twice();
        $this->app->instance(WhatsAppNotifier::class, $notifierMock);

        [$sesi] = $this->buatSesiDanState(
            '628333', 'domisili', // domisili: [fotokopi_ktp, fotokopi_kk]
            ['nama_lengkap' => 'Siti', 'alamat_tinggal' => 'Jl. A', 'lama_tinggal' => '2 tahun', 'keperluan' => 'BPJS']
        );

        // ── UPLOAD 1: fotokopi_ktp ────────────────────────────────────────────
        $urlKtp = URL::temporarySignedRoute(
            'upload.process',
            now()->addMinutes(60),
            ['no_wa' => '628333', 'sesi_id' => $sesi->id, 'jenis_dokumen' => 'fotokopi_ktp']
        );

        $this->post($urlKtp, ['file' => UploadedFile::fake()->image('ktp.jpg')])
             ->assertStatus(200);

        // Setelah KTP: state MASIH menunggu_dokumen, hanya KTP yang tersimpan
        $state = PercakapanState::where('no_wa', '628333')->first();
        $this->assertEquals('menunggu_dokumen', $state->langkah,
            'Langkah harus MASIH menunggu_dokumen setelah dokumen pertama');
        $this->assertArrayHasKey('fotokopi_ktp', $state->dokumen_diterima);
        $this->assertArrayNotHasKey('fotokopi_kk', $state->dokumen_diterima);
        $this->assertDatabaseCount('dokumen_permohonans', 1);

        // ── UPLOAD 2: fotokopi_kk ─────────────────────────────────────────────
        $urlKk = URL::temporarySignedRoute(
            'upload.process',
            now()->addMinutes(60),
            ['no_wa' => '628333', 'sesi_id' => $sesi->id, 'jenis_dokumen' => 'fotokopi_kk']
        );

        $this->post($urlKk, ['file' => UploadedFile::fake()->image('kk.jpg')])
             ->assertStatus(200);

        // Setelah KK: state HARUS berpindah ke menunggu_konfirmasi
        $state->refresh();
        $this->assertEquals('menunggu_konfirmasi', $state->langkah,
            'Langkah harus berpindah ke menunggu_konfirmasi setelah semua dokumen lengkap');

        // Kedua slot harus terisi dengan ID record yang berbeda dan valid
        $this->assertArrayHasKey('fotokopi_ktp', $state->dokumen_diterima);
        $this->assertArrayHasKey('fotokopi_kk', $state->dokumen_diterima);
        $this->assertNotEquals(
            $state->dokumen_diterima['fotokopi_ktp'],
            $state->dokumen_diterima['fotokopi_kk'],
            'ID record dua dokumen berbeda harus berbeda'
        );

        // Kedua record harus ada di tabel dokumen_permohonans
        $this->assertDatabaseCount('dokumen_permohonans', 2);
        $this->assertDatabaseHas('dokumen_permohonans', [
            'no_wa'         => '628333',
            'jenis_dokumen' => 'fotokopi_ktp',
            'status'        => 'aktif',
            'id_permohonan' => null,
        ]);
        $this->assertDatabaseHas('dokumen_permohonans', [
            'no_wa'         => '628333',
            'jenis_dokumen' => 'fotokopi_kk',
            'status'        => 'aktif',
            'id_permohonan' => null,
        ]);

        // Kedua file harus tersimpan secara fisik di storage fake
        $ktpRecord = DokumenPermohonan::find($state->dokumen_diterima['fotokopi_ktp']);
        $kkRecord  = DokumenPermohonan::find($state->dokumen_diterima['fotokopi_kk']);
        Storage::disk('local')->assertExists($ktpRecord->path_file);
        Storage::disk('local')->assertExists($kkRecord->path_file);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 6 (BARU – gap #2a): Mengubah parameter URL setelah signing → ditolak 403
    //
    // Skenario: URL yang valid dibuat untuk 'fotokopi_ktp', lalu
    // parameter jenis_dokumen diubah menjadi 'fotokopi_kk' secara manual.
    // Laravel harus mendeteksi signature sudah tidak cocok → 403 + halaman custom.
    // ─────────────────────────────────────────────────────────────────────────
    public function test_parameter_url_ditamper_setelah_signing_ditolak_403()
    {
        [$sesi] = $this->buatSesiDanState('628444', 'domisili', ['nama_lengkap' => 'Andi']);

        // Buat URL sah untuk fotokopi_ktp
        $urlSah = URL::temporarySignedRoute(
            'upload.form',
            now()->addMinutes(60),
            ['no_wa' => '628444', 'sesi_id' => $sesi->id, 'jenis_dokumen' => 'fotokopi_ktp']
        );

        // Tamper: ganti jenis_dokumen di path dari 'fotokopi_ktp' ke 'fotokopi_kk'
        // Signature lama menjadi tidak cocok dengan path baru ini
        $urlDitamper = str_replace('fotokopi_ktp', 'fotokopi_kk', $urlSah);

        // Self-check: pastikan URL memang berubah agar test tidak false-positive
        $this->assertStringContainsString('fotokopi_kk', $urlDitamper);
        $this->assertStringNotContainsString('fotokopi_ktp', $urlDitamper);

        $response = $this->get($urlDitamper);

        // Harus ditolak 403 — signature tidak cocok
        $response->assertStatus(403);
        // Harus tampil halaman error CUSTOM kita, bukan Laravel exception mentah
        $response->assertSee('Tautan sudah kedaluwarsa atau tidak valid');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 7 (BARU – gap #2b): URL yang expired (> 60 menit) ditolak dengan halaman error custom
    //
    // Menggunakan $this->travel() untuk memajukan waktu tanpa menunggu sungguhan.
    // ─────────────────────────────────────────────────────────────────────────
    public function test_url_yang_sudah_expired_ditolak_dengan_halaman_error_custom()
    {
        [$sesi] = $this->buatSesiDanState('628555', 'domisili', ['nama_lengkap' => 'Dewi']);

        // Buat URL yang valid SEKARANG, berlaku 60 menit
        $url = URL::temporarySignedRoute(
            'upload.form',
            now()->addMinutes(60),
            ['no_wa' => '628555', 'sesi_id' => $sesi->id, 'jenis_dokumen' => 'fotokopi_ktp']
        );

        // Majukan waktu 61 menit ke depan → URL pasti sudah expired
        $this->travel(61)->minutes();

        $response = $this->get($url);

        // Harus ditolak 403
        $response->assertStatus(403);
        // Harus tampil halaman error CUSTOM (bukan stack trace teknis Laravel)
        $response->assertSee('Tautan sudah kedaluwarsa atau tidak valid');
        $response->assertSee('Silakan ketik apa saja di WhatsApp chatbot kami');
        // Pastikan bukan error teknis Laravel (tidak ada stack trace / class name)
        $response->assertDontSee('Illuminate\\');
        $response->assertDontSee('InvalidSignatureException');
    }
}
