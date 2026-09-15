<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test khusus untuk memvalidasi middleware VerifyAgentApiKey.
 * Membuktikan bahwa request tanpa atau dengan API key yang salah
 * ditolak sebelum logika controller berjalan.
 */
class ApiKeyAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_tanpa_api_key_ditolak_401()
    {
        // Kirim tanpa header X-Agent-Api-Key sama sekali
        $response = $this->postJson('/api/verifikasi/mulai', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(401)
                 ->assertJson(['status' => 'unauthorized']);
    }

    public function test_request_dengan_api_key_salah_ditolak_401()
    {
        $response = $this->withHeaders([
            'X-Agent-Api-Key' => 'kunci-yang-jelas-salah-sekali'
        ])->postJson('/api/verifikasi/mulai', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(401)
                 ->assertJson(['status' => 'unauthorized']);
    }

    public function test_request_dengan_api_key_valid_lolos()
    {
        // Alur baru: NIK berapapun diterima (self-registration), bukan 404
        // Fake HTTP agar tidak benar-benar menghubungi Fonnte saat testing
        \Illuminate\Support\Facades\Http::fake([
            'api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200),
        ]);

        // Gunakan helper agentPostJson dari base TestCase — ini bukti langsung
        // bahwa key yang benar menghasilkan respons bukan 401
        $response = $this->agentPostJson('/api/verifikasi/mulai', [
            'nik'   => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        // Middleware lolos, controller berjalan, NIK di-upsert → 200 pending OTP
        $response->assertStatus(200)
                 ->assertJson(['status' => 'pending']);
    }

    public function test_percobaan_unauthorized_dicatat_ke_log()
    {
        // Request tanpa key
        $this->postJson('/api/verifikasi/mulai', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        // Percobaan harus tercatat di log_akses_agent
        $this->assertDatabaseHas('log_akses_agent', [
            'nik'    => 'unknown',
            'hasil'  => 'unauthorized',
        ]);
    }
}
