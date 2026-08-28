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
            'sesi_id'             => 'dummy-sesi-123',
        ]);
    }

    /** Warga kirim pesan teks biasa (tanpa file) → tetap di langkah yang sama,
     *  mendapatkan tautan upload. */
    public function test_pesan_teks_tanpa_file_mendapatkan_link_upload()
    {
        $this->buatStateMenungguDokumen();

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'ini saya kirim fotonya',
        ]);

        $state = PercakapanState::where('no_wa', '08111222333')->first();

        $this->assertEquals('menunggu_dokumen', $state->langkah);
        $this->assertEmpty($state->dokumen_diterima);
        $this->assertDatabaseCount('dokumen_permohonans', 0);
        
        $response->assertStatus(200);
        $response->assertSee('Mohon klik tautan berikut dan selesaikan unggahan di browser Anda');
    }


}
