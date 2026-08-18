<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\PercakapanState;
use App\Models\DokumenPermohonan;

class MenungguDokumenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function buatStateMenungguDokumen(string $noWa = '08111222333'): PercakapanState
    {
        return PercakapanState::create([
            'no_wa'               => $noWa,
            'langkah'             => 'menunggu_dokumen',
            'jenis_surat_dipilih' => 'domisili',   // dokumen_wajib: fotokopi_ktp, fotokopi_kk
            'form_sementara'      => [
                'nama_lengkap'   => 'Budi Santoso',
                'alamat_tinggal' => 'Jl. Merdeka No. 10',
                'lama_tinggal'   => '5 tahun',
                'keperluan'      => 'BPJS',
            ],
            'dokumen_diterima'    => [],
        ]);
    }

    /** Warga kirim pesan teks biasa (tanpa file) → tetap di langkah yang sama,
     *  dokumen_diterima tidak berubah. */
    public function test_pesan_teks_tanpa_file_tidak_mengubah_dokumen()
    {
        $this->buatStateMenungguDokumen();

        $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'ini saya kirim fotonya',
        ]);

        $state = PercakapanState::where('no_wa', '08111222333')->first();

        $this->assertEquals('menunggu_dokumen', $state->langkah);
        $this->assertEmpty($state->dokumen_diterima);
        $this->assertDatabaseCount('dokumen_permohonans', 0);
    }

    /** Kirim 1 file → tersimpan sebagai fotokopi_ktp (slot pertama),
     *  langkah masih menunggu_dokumen (fotokopi_kk belum dikirim). */
    public function test_kirim_satu_file_disimpan_sebagai_dokumen_pertama()
    {
        $this->buatStateMenungguDokumen();

        $foto = UploadedFile::fake()->image('ktp.jpg');

        $this->call('POST', '/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '.',
        ], [], ['file' => $foto]);

        $state = PercakapanState::where('no_wa', '08111222333')->first();

        // Langkah masih menunggu_dokumen
        $this->assertEquals('menunggu_dokumen', $state->langkah);

        // dokumen_diterima harus punya fotokopi_ktp
        $this->assertArrayHasKey('fotokopi_ktp', $state->dokumen_diterima);
        $this->assertArrayNotHasKey('fotokopi_kk', $state->dokumen_diterima);

        // Record di DB harus ada dengan jenis_dokumen yang benar
        $this->assertDatabaseHas('dokumen_permohonans', [
            'no_wa'         => '08111222333',
            'jenis_dokumen' => 'fotokopi_ktp',
            'status'        => 'aktif',
            'id_permohonan' => null,    // belum diisi — permohonan belum dibuat
        ]);

        // File harus tersimpan di storage lokal
        $doc = DokumenPermohonan::first();
        Storage::disk('local')->assertExists($doc->path_file);
    }

    /** Kirim 2 file berurutan → kedua slot terisi dengan jenis yang benar,
     *  setelah file ke-2 langkah pindah ke menunggu_konfirmasi. */
    public function test_kirim_dua_file_berurutan_pindah_ke_menunggu_konfirmasi()
    {
        $this->buatStateMenungguDokumen();

        // Kirim file 1 → fotokopi_ktp
        $foto1 = UploadedFile::fake()->image('ktp.jpg');
        $this->call('POST', '/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '.',
        ], [], ['file' => $foto1]);

        // Kirim file 2 → fotokopi_kk
        $foto2 = UploadedFile::fake()->image('kk.jpg');
        $this->call('POST', '/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '.',
        ], [], ['file' => $foto2]);

        $state = PercakapanState::where('no_wa', '08111222333')->first();

        // Langkah harus pindah ke menunggu_konfirmasi
        $this->assertEquals('menunggu_konfirmasi', $state->langkah);

        // Kedua slot harus terisi dengan ID record yang valid
        $this->assertArrayHasKey('fotokopi_ktp', $state->dokumen_diterima);
        $this->assertArrayHasKey('fotokopi_kk', $state->dokumen_diterima);

        // Ada 2 record di DB dengan jenis berbeda
        $this->assertDatabaseCount('dokumen_permohonans', 2);

        $this->assertDatabaseHas('dokumen_permohonans', [
            'no_wa'         => '08111222333',
            'jenis_dokumen' => 'fotokopi_ktp',
            'status'        => 'aktif',
        ]);
        $this->assertDatabaseHas('dokumen_permohonans', [
            'no_wa'         => '08111222333',
            'jenis_dokumen' => 'fotokopi_kk',
            'status'        => 'aktif',
        ]);
    }
}
