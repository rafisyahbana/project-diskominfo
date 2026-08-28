<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\Warga;
use App\Models\PercakapanState;
use App\Models\SesiVerifikasi;

class ConversationOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_warga_baru_mendapat_menu_utama()
    {
        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'Halo, saya mau buat surat',
        ]);

        $response->assertStatus(200);

        // State harusnya tetap di awal
        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('awal', $state->langkah);
        $this->assertNull($state->sesi_id);
    }

    public function test_warga_pilih_menu_1_diarahkan_ke_menunggu_nik()
    {
        PercakapanState::create([
            'no_wa' => '08111222333',
            'langkah' => 'awal',
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '1',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_nik', $state->langkah);
    }

    public function test_warga_pilih_menu_lain_mendapat_info_statis()
    {
        PercakapanState::create([
            'no_wa' => '08111222333',
            'langkah' => 'awal',
        ]);

        // Kirim '2' (Cek Status)
        $response1 = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '2',
        ]);
        $response1->assertStatus(200);
        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('awal', $state->langkah); // State tidak berubah

        // Kirim '3' (Bantuan)
        $response2 = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '3',
        ]);
        $response2->assertStatus(200);
        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('awal', $state->langkah); // State tidak berubah
    }

    public function test_kirim_nik_valid_diarahkan_ke_menunggu_otp()
    {
        // Setup warga terdaftar
        Warga::factory()->create([
            'nik' => '1234567890123456',
            'no_hp_terdaftar' => '08111222333'
        ]);

        // Setup state sebelumnya = menunggu_nik
        PercakapanState::create([
            'no_wa' => '08111222333',
            'langkah' => 'menunggu_nik',
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'Ini NIK saya: 1234567890123456',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_otp', $state->langkah);
        $this->assertNotNull($state->sesi_id);

        // Pastikan SesiVerifikasi terbuat
        $this->assertDatabaseHas('sesi_verifikasi', [
            'id' => $state->sesi_id,
            'nik' => '1234567890123456',
            'no_wa' => '08111222333',
            'status' => 'pending'
        ]);
    }

    public function test_kirim_nik_tidak_valid_tetap_menunggu_nik()
    {
        // Setup state sebelumnya = menunggu_nik
        PercakapanState::create([
            'no_wa' => '08111222333',
            'langkah' => 'menunggu_nik',
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'NIK 9999999999999999',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_nik', $state->langkah);
    }

    public function test_kirim_otp_benar_diarahkan_ke_pilih_surat()
    {
        // Setup sesi
        $sesi = SesiVerifikasi::factory()->create([
            'nik' => '1234567890123456',
            'no_wa' => '08111222333',
            'otp_hash' => Hash::make('654321'),
            'status' => 'pending',
            'expired_at' => now()->addMinutes(5)
        ]);

        // Setup state sebelumnya = menunggu_otp dengan sesi_id di-bind
        PercakapanState::create([
            'no_wa' => '08111222333',
            'sesi_id' => $sesi->id,
            'langkah' => 'menunggu_otp',
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'Kode OTP saya adalah 654321, tolong diproses',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_pilihan_surat', $state->langkah);
        $this->assertEquals('verified', $sesi->fresh()->status);
    }

    public function test_pilih_surat_valid_diarahkan_ke_mengisi_form()
    {
        PercakapanState::create([
            'no_wa' => '08111222333',
            'langkah' => 'menunggu_pilihan_surat',
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '1',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('mengisi_form', $state->langkah);
        $this->assertEquals('domisili', $state->jenis_surat_dipilih);
    }

    public function test_pilih_surat_ambigu_tetap_menunggu_pilihan()
    {
        PercakapanState::create([
            'no_wa' => '08111222333',
            'langkah' => 'menunggu_pilihan_surat',
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '15',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_pilihan_surat', $state->langkah);
        $this->assertNull($state->jenis_surat_dipilih);
    }

    public function test_verifikasi_otp_berhasil_dengan_draft_form_sebagian()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'nik' => '1234567890123456',
            'no_wa' => '08111222333',
            'otp_hash' => Hash::make('654321'),
            'status' => 'pending',
            'expired_at' => now()->addMinutes(5)
        ]);

        PercakapanState::create([
            'no_wa' => '08111222333',
            'sesi_id' => $sesi->id,
            'langkah' => 'menunggu_otp',
            'jenis_surat_dipilih' => 'domisili',
            'form_sementara' => ['nama_lengkap' => 'Budi']
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '654321',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('mengisi_form', $state->langkah);
    }

    public function test_verifikasi_otp_berhasil_dengan_draft_lengkap_langsung_konfirmasi()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'nik' => '1234567890123456',
            'no_wa' => '08111222333',
            'otp_hash' => Hash::make('654321'),
            'status' => 'pending',
            'expired_at' => now()->addMinutes(5)
        ]);

        PercakapanState::create([
            'no_wa' => '08111222333',
            'sesi_id' => $sesi->id,
            'langkah' => 'menunggu_otp',
            'jenis_surat_dipilih' => 'domisili',
            'form_sementara' => [
                'nama_lengkap' => 'Budi Santoso',
                'alamat_tinggal' => 'Jl. Merdeka No. 10',
                'lama_tinggal' => '5 tahun',
                'keperluan' => 'BPJS',
            ],
            'dokumen_diterima' => [
                'fotokopi_ktp' => 'uuid-1',
                'fotokopi_kk' => 'uuid-2'
            ]
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '654321',
        ]);

        $response->assertStatus(200);

        $state = PercakapanState::where('no_wa', '08111222333')->first();
        $this->assertEquals('menunggu_konfirmasi', $state->langkah);
    }

    // ── Tests: mengisi_form ──────────────────────────────────────────────────

    /** State: mengisi_form, form_sementara kosong. Warga membalas dengan jawaban pertama.
     *  Jawaban ini harus langsung tersimpan di field pertama dan meminta field kedua. */
    public function test_masuk_mengisi_form_pertama_kali_langsung_simpan_jawaban()
    {
        PercakapanState::create([
            'no_wa'               => '08111222333',
            'langkah'             => 'mengisi_form',
            'jenis_surat_dipilih' => 'domisili',
            'form_sementara'      => [],
        ]);

        $response = $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'Budi Santoso', // Jawaban untuk nama_lengkap
        ]);

        $response->assertStatus(200);
        $state = PercakapanState::where('no_wa', '08111222333')->first();

        // Jawaban harus tersimpan
        $this->assertEquals('Budi Santoso', $state->form_sementara['nama_lengkap']);
        // Masih mengisi form untuk field berikutnya
        $this->assertEquals('mengisi_form', $state->langkah);
    }


    /** Field pertama sedang ditunggu, warga kirim pesan kosong/spasi — field tidak boleh terisi. */
    public function test_jawaban_field_kosong_ditolak_dan_diminta_ulang()
    {
        PercakapanState::create([
            'no_wa'               => '08111222333',
            'langkah'             => 'mengisi_form',
            'jenis_surat_dipilih' => 'domisili',
            'form_sementara'      => ['nama_lengkap' => 'Budi'], // field ke-1 sudah ada
        ]);

        // Field ke-2 sedang ditunggu (alamat_tinggal). Warga kirim hanya spasi.
        $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '   ',
        ]);

        $state = PercakapanState::where('no_wa', '08111222333')->first();

        // form_sementara tidak berubah — field alamat_tinggal masih kosong
        $this->assertArrayNotHasKey('alamat_tinggal', $state->form_sementara);
        $this->assertEquals('mengisi_form', $state->langkah);
    }

    /** Warga mengisi semua 4 field domisili satu per satu — setelah field terakhir
     *  langkah pindah ke menunggu_dokumen dan semua field tersimpan dengan benar. */
    public function test_isi_semua_field_domisili_pindah_ke_menunggu_dokumen()
    {
        PercakapanState::create([
            'no_wa'               => '08111222333',
            'langkah'             => 'mengisi_form',
            'jenis_surat_dipilih' => 'domisili',
            'form_sementara'      => ['nama_lengkap' => 'Budi'], // field ke-1 sudah terisi
        ]);

        // Isi field ke-2: alamat_tinggal
        $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'Jl. Merdeka No. 10',
        ]);

        // Isi field ke-3: lama_tinggal
        $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => '5 tahun',
        ]);

        // Isi field ke-4 (terakhir): keperluan
        $this->postJson('/api/dev/simulasi-chat', [
            'no_wa' => '08111222333',
            'pesan' => 'Keperluan administrasi BPJS',
        ]);

        $state = PercakapanState::where('no_wa', '08111222333')->first();

        // Semua field harus terisi dengan value yang benar
        $this->assertEquals('Jl. Merdeka No. 10',       $state->form_sementara['alamat_tinggal']);
        $this->assertEquals('5 tahun',                   $state->form_sementara['lama_tinggal']);
        $this->assertEquals('Keperluan administrasi BPJS', $state->form_sementara['keperluan']);

        // Langkah harus pindah ke menunggu_dokumen
        $this->assertEquals('menunggu_dokumen', $state->langkah);
    }
}
