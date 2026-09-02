<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Warga;
use App\Models\LogAksesAgent;
use App\Models\SesiVerifikasi;

class MulaiVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_nik_tidak_ditemukan()
    {
        $response = $this->agentPostJson('/api/verifikasi/mulai', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(404)
                 ->assertJson(['status' => 'nik_tidak_ditemukan']);

        $this->assertDatabaseHas('log_akses_agent', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890',
            'hasil' => 'denied'
        ]);
    }

    public function test_no_wa_tidak_cocok()
    {
        $warga = Warga::factory()->create([
            'nik' => '1234567890123456',
            'no_hp_terdaftar' => '08999999999'
        ]);

        $response = $this->agentPostJson('/api/verifikasi/mulai', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(403)
                 ->assertJson(['status' => 'no_wa_tidak_cocok']);

        $this->assertDatabaseHas('log_akses_agent', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890',
            'hasil' => 'denied'
        ]);
    }

    public function test_berhasil_generate_otp()
    {
        // Fake HTTP agar tidak benar-benar menghubungi Fonnte saat testing
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        $warga = Warga::factory()->create([
            'nik' => '1234567890123456',
            'no_hp_terdaftar' => '081234567890'
        ]);

        $response = $this->agentPostJson('/api/verifikasi/mulai', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['sesi_id', 'status'])
                 ->assertJson(['status' => 'pending']);

        $this->assertDatabaseHas('sesi_verifikasi', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890',
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('log_akses_agent', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890',
            'hasil' => 'success'
        ]);
    }
}
