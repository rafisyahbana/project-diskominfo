<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Warga;
use App\Models\LogAksesAgent;
use App\Models\SesiVerifikasi;

class MulaiVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Alur baru: warga dengan NIK yang BELUM PERNAH ADA di DB
     * tetap bisa generate OTP (self-registration).
     */
    public function test_nik_baru_langsung_generate_otp()
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        $response = $this->agentPostJson('/api/verifikasi/mulai', [
            'nik'   => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['sesi_id', 'status'])
                 ->assertJson(['status' => 'pending']);

        // Warga harus otomatis terdaftar (upsert)
        $this->assertDatabaseHas('warga', [
            'nik'            => '1234567890123456',
            'no_hp_terdaftar' => '081234567890',
        ]);

        $this->assertDatabaseHas('sesi_verifikasi', [
            'nik'   => '1234567890123456',
            'no_wa' => '081234567890',
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('log_akses_agent', [
            'nik'   => '1234567890123456',
            'no_wa' => '081234567890',
            'hasil' => 'success'
        ]);
    }

    /**
     * Alur upsert: no_wa sudah terdaftar dengan NIK LAIN
     * → NIK di-overwrite, audit log menyimpan NIK lama dan NIK baru.
     */
    public function test_upsert_overwrite_nik_lama_dan_catat_audit()
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        // Buat warga lama dengan NIK berbeda
        Warga::create([
            'nik'            => '9999999999999999',
            'nama'           => 'Warga Lama',
            'no_hp_terdaftar' => '081234567890',
        ]);

        // Sekarang no_wa yang sama coba daftar dengan NIK baru
        $response = $this->agentPostJson('/api/verifikasi/mulai', [
            'nik'   => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(200);

        // NIK harus ter-overwrite ke yang baru
        $this->assertDatabaseHas('warga', [
            'nik'            => '1234567890123456',
            'no_hp_terdaftar' => '081234567890',
        ]);

        // NIK lama tidak boleh ada lagi untuk no_wa ini
        $this->assertDatabaseMissing('warga', [
            'nik'            => '9999999999999999',
            'no_hp_terdaftar' => '081234567890',
        ]);

        // Audit log harus mencatat NIK lama dan baru secara eksplisit
        $log = LogAksesAgent::where('hasil', 'nik_overwritten')->first();
        $this->assertNotNull($log);
        $this->assertEquals('081234567890', $log->no_wa);
        $payload = is_array($log->payload) ? $log->payload : json_decode($log->payload, true);
        $this->assertEquals('9999999999999999', $payload['nik_lama']);
        $this->assertEquals('1234567890123456', $payload['nik_baru']);
        $this->assertArrayHasKey('timestamp', $payload);
    }

    /**
     * Alur upsert: NIK sama, no_wa sama → hanya update data, tidak ada overwrite log.
     */
    public function test_berhasil_generate_otp_warga_sudah_terdaftar()
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        // Buat warga yang sudah terdaftar
        Warga::factory()->create([
            'nik'            => '1234567890123456',
            'no_hp_terdaftar' => '081234567890'
        ]);

        $response = $this->agentPostJson('/api/verifikasi/mulai', [
            'nik'   => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['sesi_id', 'status'])
                 ->assertJson(['status' => 'pending']);

        $this->assertDatabaseHas('sesi_verifikasi', [
            'nik'    => '1234567890123456',
            'no_wa'  => '081234567890',
            'status' => 'pending'
        ]);

        // Tidak ada overwrite log jika NIK sama
        $this->assertDatabaseMissing('log_akses_agent', [
            'hasil' => 'nik_overwritten',
        ]);
    }
}
