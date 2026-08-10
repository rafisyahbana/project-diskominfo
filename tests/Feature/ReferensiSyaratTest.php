<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferensiSyaratTest extends TestCase
{
    use RefreshDatabase;

    public function test_jenis_surat_valid_return_200()
    {
        foreach (['domisili', 'sktm', 'pengantar', 'lainnya'] as $jenis) {
            $response = $this->agentGetJson("/api/referensi/syarat/$jenis");
            $response->assertStatus(200)
                     ->assertJsonStructure(['jenis_surat', 'nama', 'estimasi_waktu', 'syarat'])
                     ->assertJson(['jenis_surat' => $jenis]);
        }
    }

    public function test_jenis_surat_tidak_dikenal_return_404()
    {
        $response = $this->agentGetJson('/api/referensi/syarat/tidak_ada');
        $response->assertStatus(404)
                 ->assertJson(['status' => 'jenis_surat_tidak_dikenal']);
    }
}
