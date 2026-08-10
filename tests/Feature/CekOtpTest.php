<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\SesiVerifikasi;

class CekOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_tidak_ditemukan()
    {
        $response = $this->agentPostJson('/api/verifikasi/cek', [
            'sesi_id' => Str::uuid()->toString(),
            'otp' => '123456'
        ]);

        $response->assertStatus(404)
                 ->assertJson(['status' => 'sesi_tidak_ditemukan']);
    }

    public function test_otp_salah_tambah_percobaan()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'otp_hash' => Hash::make('123456'),
            'status' => 'pending',
            'percobaan_gagal' => 0,
            'expired_at' => now()->addMinutes(5)
        ]);

        $response = $this->agentPostJson('/api/verifikasi/cek', [
            'sesi_id' => $sesi->id,
            'otp' => '654321'
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'status' => 'salah',
                     'sisa_percobaan' => 2
                 ]);

        $this->assertEquals(1, $sesi->fresh()->percobaan_gagal);
    }

    public function test_otp_salah_3_kali_gagal_permanen()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'otp_hash' => Hash::make('123456'),
            'status' => 'pending',
            'percobaan_gagal' => 2,
            'expired_at' => now()->addMinutes(5)
        ]);

        $response = $this->agentPostJson('/api/verifikasi/cek', [
            'sesi_id' => $sesi->id,
            'otp' => '654321'
        ]);

        $response->assertStatus(429)
                 ->assertJson(['status' => 'gagal_permanen']);

        $this->assertEquals('failed', $sesi->fresh()->status);
        $this->assertEquals(3, $sesi->fresh()->percobaan_gagal);
    }

    public function test_sesi_sudah_expired()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'otp_hash' => Hash::make('123456'),
            'status' => 'pending',
            'expired_at' => now()->subMinutes(1)
        ]);

        $response = $this->agentPostJson('/api/verifikasi/cek', [
            'sesi_id' => $sesi->id,
            'otp' => '123456'
        ]);

        $response->assertStatus(410)
                 ->assertJson(['status' => 'kedaluwarsa']);

        $this->assertEquals('expired', $sesi->fresh()->status);
    }

    public function test_otp_benar_jadi_verified()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'otp_hash' => Hash::make('123456'),
            'status' => 'pending',
            'expired_at' => now()->addMinutes(5)
        ]);

        $response = $this->agentPostJson('/api/verifikasi/cek', [
            'sesi_id' => $sesi->id,
            'otp' => '123456'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => 'verified']);

        $this->assertEquals('verified', $sesi->fresh()->status);
        $this->assertNotNull($sesi->fresh()->berlaku_hingga);
        $this->assertTrue($sesi->fresh()->isVerifiedAndActive());
    }
}
