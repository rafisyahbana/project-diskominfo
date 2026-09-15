<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppNotifier;
use App\Models\DokumenPermohonan;
use App\Models\PercakapanState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UploadRegistrasiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GET form registrasi KTP menampilkan form saat state benar.
     */
    public function test_show_form_registrasi_berhasil()
    {
        $state = PercakapanState::create([
            'no_wa'   => '6281357510843',
            'langkah' => 'menunggu_upload_ktp_registrasi',
        ]);

        $url = URL::temporarySignedRoute(
            'upload.ktp_registrasi',
            now()->addMinutes(60),
            ['no_wa' => '6281357510843']
        );

        $response = $this->get($url);
        $response->assertStatus(200);
    }

    /**
     * GET form registrasi KTP ditolak (403) jika state bukan menunggu_upload_ktp_registrasi.
     */
    public function test_show_form_registrasi_ditolak_jika_state_salah()
    {
        PercakapanState::create([
            'no_wa'   => '6281357510843',
            'langkah' => 'menunggu_otp', // Salah state
        ]);

        $url = URL::temporarySignedRoute(
            'upload.ktp_registrasi',
            now()->addMinutes(60),
            ['no_wa' => '6281357510843']
        );

        $response = $this->get($url);
        $response->assertStatus(403);
    }

    /**
     * POST upload KTP registrasi berhasil:
     * - Buat record DokumenPermohonan dengan id_permohonan = null
     * - Simpan ID di PercakapanState.dokumen_diterima['fotokopi_ktp']
     * - State berpindah ke menunggu_nik_registrasi
     * - Kirim notifikasi WA
     */
    public function test_process_registrasi_berhasil()
    {
        Storage::fake('local');

        $notifierMock = $this->mock(WhatsAppNotifier::class);
        $notifierMock->shouldReceive('kirim')->once();

        $state = PercakapanState::create([
            'no_wa'   => '6281357510843',
            'langkah' => 'menunggu_upload_ktp_registrasi',
        ]);

        $url = URL::temporarySignedRoute(
            'upload.process_registrasi',
            now()->addMinutes(60),
            ['no_wa' => '6281357510843']
        );

        $response = $this->post($url, [
            'file' => UploadedFile::fake()->image('ktp.jpg', 400, 300)
        ]);

        $response->assertStatus(200); // success view

        // State harus berpindah ke menunggu_nik_registrasi
        $state->refresh();
        $this->assertEquals('menunggu_nik_registrasi', $state->langkah);

        // ID dokumen KTP harus tersimpan di state
        $dokumenDiterima = $state->dokumen_diterima;
        $this->assertArrayHasKey('fotokopi_ktp', $dokumenDiterima);

        // Record DokumenPermohonan harus ada dengan id_permohonan = null
        $this->assertDatabaseHas('dokumen_permohonans', [
            'no_wa'          => '6281357510843',
            'jenis_dokumen'  => 'fotokopi_ktp',
            'id_permohonan'  => null,
            'status'         => 'aktif',
        ]);

        // Counter retry harus di-reset ke 0
        $formSementara = $state->form_sementara;
        $this->assertEquals(0, $formSementara['percobaan_gagal_nik']);
    }

    /**
     * POST upload KTP registrasi ditolak jika state bukan menunggu_upload_ktp_registrasi.
     */
    public function test_process_registrasi_ditolak_jika_state_salah()
    {
        Storage::fake('local');

        PercakapanState::create([
            'no_wa'   => '6281357510843',
            'langkah' => 'menunggu_otp',
        ]);

        $url = URL::temporarySignedRoute(
            'upload.process_registrasi',
            now()->addMinutes(60),
            ['no_wa' => '6281357510843']
        );

        $response = $this->post($url, [
            'file' => UploadedFile::fake()->image('ktp.jpg')
        ]);

        $response->assertStatus(403);
    }
}
