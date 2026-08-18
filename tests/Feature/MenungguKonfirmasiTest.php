<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\PercakapanState;
use App\Models\DokumenPermohonan;
use App\Models\SesiVerifikasi;
use App\Models\Warga;
use App\Models\Permohonan;

class MenungguKonfirmasiTest extends TestCase
{
    use RefreshDatabase;

    private function setupSesiDanState(string $statusSesi = 'verified')
    {
        $noWa = '08111222333';
        $nik  = '1234567890123456';

        Warga::factory()->create([
            'nik' => $nik,
            'no_hp_terdaftar' => $noWa
        ]);

        $sesi = SesiVerifikasi::factory()->create([
            'nik'            => $nik,
            'no_wa'          => $noWa,
            'otp_hash'       => Hash::make('654321'),
            'status'         => $statusSesi,
            'expired_at'     => ($statusSesi === 'expired') ? now()->subMinutes(10) : now()->addMinutes(10),
            'berlaku_hingga' => ($statusSesi === 'expired') ? now()->subMinutes(10) : now()->addMinutes(10),
        ]);

        $doc1 = DokumenPermohonan::create([
            'no_wa'         => $noWa,
            'jenis_dokumen' => 'fotokopi_ktp',
            'path_file'     => 'fake/path/ktp.jpg',
            'status'        => 'aktif',
        ]);
        
        $doc2 = DokumenPermohonan::create([
            'no_wa'         => $noWa,
            'jenis_dokumen' => 'fotokopi_kk',
            'path_file'     => 'fake/path/kk.jpg',
            'status'        => 'aktif',
        ]);

        PercakapanState::create([
            'no_wa'               => $noWa,
            'sesi_id'             => $sesi->id,
            'langkah'             => 'menunggu_konfirmasi',
            'jenis_surat_dipilih' => 'domisili',
            'form_sementara'      => [
                'nama_lengkap'   => 'Budi Santoso',
                'alamat_tinggal' => 'Jl. Merdeka No. 10',
                'lama_tinggal'   => '5 tahun',
                'keperluan'      => 'BPJS',
            ],
            'dokumen_diterima'    => [
                'fotokopi_ktp' => $doc1->id,
                'fotokopi_kk'  => $doc2->id,
            ],
        ]);

        return $sesi;
    }

    public function test_konfirmasi_ya_membuat_permohonan_dan_selesai()
    {
        $this->setupSesiDanState('verified');

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'ya',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'no_wa' => '08111222333'
        ]);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('selesai_mengajukan', $state->langkah);

        $permohonan = Permohonan::where('nik', '1234567890123456')->first();
        $this->assertNotNull($permohonan);
        $this->assertEquals('domisili', $permohonan->jenis_surat);
        $this->assertEquals('Budi Santoso', $permohonan->data_form['nama_lengkap']);

        $docs = DokumenPermohonan::where('no_wa', '08111222333')->get();
        foreach ($docs as $doc) {
            $this->assertEquals($permohonan->id, $doc->id_permohonan);
            $this->assertEquals('aktif', $doc->status);
        }
    }

    public function test_konfirmasi_batal_kembali_pilih_surat_dan_abandon_dokumen()
    {
        $sesi = $this->setupSesiDanState('verified');

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'BATAL',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_pilihan_surat', $state->langkah);
        $this->assertEquals($sesi->id, $state->sesi_id); // sesi tidak hilang
        $this->assertNull($state->jenis_surat_dipilih);
        $this->assertNull($state->form_sementara);
        $this->assertNull($state->dokumen_diterima);

        $permohonan = Permohonan::where('nik', '1234567890123456')->first();
        $this->assertNull($permohonan); // Tidak ada permohonan yang dibuat

        $docs = DokumenPermohonan::where('no_wa', '08111222333')->get();
        foreach ($docs as $doc) {
            $this->assertNull($doc->id_permohonan);
            $this->assertEquals('abandoned', $doc->status);
        }
    }

    public function test_konfirmasi_ambigu_tetap_menunggu_konfirmasi()
    {
        $sesi = $this->setupSesiDanState('verified');

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'oke siap laksanakan',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_konfirmasi', $state->langkah);
        $this->assertEquals('domisili', $state->jenis_surat_dipilih);
        $this->assertNotNull($state->form_sementara);
    }

    public function test_konfirmasi_ya_tetapi_sesi_expired()
    {
        // Set sesi menjadi expired
        $this->setupSesiDanState('expired');

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'ya',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_nik', $state->langkah); // reset ke awal
        $this->assertNull($state->sesi_id); // sesi hilang

        // Draft form & dokumen TETAP dipertahankan
        $this->assertEquals('domisili', $state->jenis_surat_dipilih);
        $this->assertNotNull($state->form_sementara);
        $this->assertEquals('Budi Santoso', $state->form_sementara['nama_lengkap']);
        $this->assertNotNull($state->dokumen_diterima);
        $this->assertCount(2, $state->dokumen_diterima);

        $permohonan = Permohonan::where('nik', '1234567890123456')->first();
        $this->assertNull($permohonan); // Tidak ada permohonan dibuat

        $docs = DokumenPermohonan::where('no_wa', '08111222333')->get();
        foreach ($docs as $doc) {
            $this->assertNull($doc->id_permohonan);
            $this->assertEquals('aktif', $doc->status); // status masih aktif, menunggu diajukan ulang
        }
    }
}
