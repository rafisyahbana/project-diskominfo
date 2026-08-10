<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\SesiVerifikasi;
use App\Models\Permohonan;
use App\Models\LogAksesAgent;

class AjukanSuratTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_tidak_ditemukan()
    {
        $response = $this->agentPostJson('/api/permohonan', [
            'sesi_id' => Str::uuid()->toString(),
            'jenis_surat' => 'domisili',
            'data_form' => ['keperluan' => 'KPR']
        ]);

        $response->assertStatus(404)
                 ->assertJson(['status' => 'sesi_tidak_ditemukan']);
    }

    public function test_sesi_belum_verified()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'status' => 'pending'
        ]);

        $response = $this->agentPostJson('/api/permohonan', [
            'sesi_id' => $sesi->id,
            'jenis_surat' => 'domisili',
            'data_form' => ['keperluan' => 'KPR']
        ]);

        $response->assertStatus(403)
                 ->assertJson(['status' => 'sesi_tidak_valid']);
    }

    public function test_sesi_sudah_lewat_berlaku_hingga()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'status' => 'verified',
            'berlaku_hingga' => now()->subMinutes(5)
        ]);

        $response = $this->agentPostJson('/api/permohonan', [
            'sesi_id' => $sesi->id,
            'jenis_surat' => 'domisili',
            'data_form' => ['keperluan' => 'KPR']
        ]);

        $response->assertStatus(403)
                 ->assertJson(['status' => 'sesi_tidak_valid']);
    }

    public function test_sesi_valid_berhasil_insert()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'nik' => '1234567890123456',
            'no_wa' => '081234567890',
            'status' => 'verified',
            'berlaku_hingga' => now()->addMinutes(10)
        ]);

        $response = $this->agentPostJson('/api/permohonan', [
            'sesi_id' => $sesi->id,
            'jenis_surat' => 'sktm',
            'data_form' => ['alasan' => 'Kurang mampu']
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['id_permohonan', 'status']);

        // Assert permohonan menggunakan NIK dan no_wa dari sesi, bukan dari payload request
        $this->assertDatabaseHas('permohonan', [
            'id' => $response->json('id_permohonan'),
            'nik' => '1234567890123456',
            'no_wa' => '081234567890',
            'jenis_surat' => 'sktm',
            'status' => 'menunggu_verifikasi'
        ]);

        $this->assertDatabaseHas('log_akses_agent', [
            'nik' => '1234567890123456',
            'no_wa' => '081234567890',
            'tool_dipanggil' => 'ajukan_surat',
            'hasil' => 'success'
        ]);
    }
}
