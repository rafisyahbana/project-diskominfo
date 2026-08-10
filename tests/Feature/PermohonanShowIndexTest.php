<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\SesiVerifikasi;
use App\Models\Permohonan;

class PermohonanShowIndexTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper untuk membuat sesi aktif ──────────────────────────────────────

    private function buatSesiAktif(string $nik, string $no_wa): SesiVerifikasi
    {
        return SesiVerifikasi::factory()->create([
            'nik'            => $nik,
            'no_wa'          => $no_wa,
            'status'         => 'verified',
            'berlaku_hingga' => now()->addMinutes(30),
        ]);
    }

    private function buatPermohonan(string $nik, string $no_wa, string $jenis = 'domisili'): Permohonan
    {
        return Permohonan::factory()->create([
            'nik'         => $nik,
            'no_wa'       => $no_wa,
            'jenis_surat' => $jenis,
            'status'      => 'menunggu_verifikasi',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /api/permohonan/{id} — show
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_show_sesi_tidak_ditemukan()
    {
        $randomId = Str::uuid()->toString();
        $response = $this->agentGetJson("/api/permohonan/$randomId?sesi_id=" . Str::uuid()->toString());
        $response->assertStatus(404)
                 ->assertJson(['status' => 'sesi_tidak_ditemukan']);
    }

    public function test_show_sesi_belum_verified()
    {
        $sesi = SesiVerifikasi::factory()->create(['status' => 'pending']);
        $permohonan = $this->buatPermohonan($sesi->nik, $sesi->no_wa);

        $response = $this->agentGetJson("/api/permohonan/{$permohonan->id}?sesi_id={$sesi->id}");
        $response->assertStatus(403)
                 ->assertJson(['status' => 'sesi_tidak_valid']);
    }

    public function test_show_sesi_expired_berlaku_hingga()
    {
        $sesi = SesiVerifikasi::factory()->create([
            'status'         => 'verified',
            'berlaku_hingga' => now()->subMinutes(5),
        ]);
        $permohonan = $this->buatPermohonan($sesi->nik, $sesi->no_wa);

        $response = $this->agentGetJson("/api/permohonan/{$permohonan->id}?sesi_id={$sesi->id}");
        $response->assertStatus(403)
                 ->assertJson(['status' => 'sesi_tidak_valid']);
    }

    public function test_show_permohonan_ditemukan()
    {
        $sesi = $this->buatSesiAktif('1111111111111111', '08111111111');
        $permohonan = $this->buatPermohonan('1111111111111111', '08111111111', 'sktm');

        $response = $this->agentGetJson("/api/permohonan/{$permohonan->id}?sesi_id={$sesi->id}");
        $response->assertStatus(200)
                 ->assertJsonStructure(['id_permohonan', 'jenis_surat', 'status', 'created_at'])
                 ->assertJson([
                     'id_permohonan' => $permohonan->id,
                     'jenis_surat'   => 'sktm',
                 ]);
    }

    /**
     * TEST PALING PENTING: Sesi A mencoba mengintip permohonan milik sesi B.
     * Harus mendapat 404 (bukan 403), sehingga penyerang tidak bisa
     * membedakan "ID tidak ada" vs "ID ada tapi bukan milikmu".
     */
    public function test_show_sesi_a_tidak_bisa_lihat_permohonan_sesi_b()
    {
        // Sesi A dengan NIK berbeda dari sesi B
        $sesiA = $this->buatSesiAktif('1111111111111111', '08111111111');
        $sesiB = $this->buatSesiAktif('2222222222222222', '08222222222');

        // Permohonan milik B
        $permohonanB = $this->buatPermohonan('2222222222222222', '08222222222', 'pengantar');

        // Sesi A mencoba mengakses permohonan B dengan sesi_id milik A
        $response = $this->agentGetJson("/api/permohonan/{$permohonanB->id}?sesi_id={$sesiA->id}");

        // Harus 404 — bukan 403 — agar tidak bocorkan bahwa ID itu valid milik orang lain
        $response->assertStatus(404)
                 ->assertJson(['status' => 'permohonan_tidak_ditemukan']);

        // Pastikan response TIDAK mengandung data apapun dari permohonan B
        $responseData = $response->json();
        $this->assertArrayNotHasKey('nik', $responseData);
        $this->assertArrayNotHasKey('no_wa', $responseData);
        $this->assertArrayNotHasKey('data_form', $responseData);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /api/permohonan?sesi_id= — index
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_index_sesi_tidak_ditemukan()
    {
        $response = $this->agentGetJson("/api/permohonan?sesi_id=" . Str::uuid()->toString());
        $response->assertStatus(404)
                 ->assertJson(['status' => 'sesi_tidak_ditemukan']);
    }

    public function test_index_hanya_tampilkan_permohonan_sendiri()
    {
        // Dua pengguna berbeda
        $sesiA = $this->buatSesiAktif('1111111111111111', '08111111111');
        $sesiB = $this->buatSesiAktif('2222222222222222', '08222222222');

        // Masing-masing punya permohonan
        $permohonanA1 = $this->buatPermohonan('1111111111111111', '08111111111', 'domisili');
        $permohonanA2 = $this->buatPermohonan('1111111111111111', '08111111111', 'sktm');
        $permohonanB  = $this->buatPermohonan('2222222222222222', '08222222222', 'pengantar');

        // Sesi A meminta daftar permohonannya
        $response = $this->agentGetJson("/api/permohonan?sesi_id={$sesiA->id}");
        $response->assertStatus(200)
                 ->assertJsonStructure(['data'])
                 ->assertJsonCount(2, 'data');

        // Pastikan ID permohonan B TIDAK muncul di response A
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($permohonanA1->id, $ids);
        $this->assertContains($permohonanA2->id, $ids);
        $this->assertNotContains($permohonanB->id, $ids);
    }

    public function test_index_urutan_terbaru_dulu()
    {
        // Sesi dibuat dengan berlaku_hingga 2 jam ke depan agar tidak
        // kadaluwarsa saat waktu dimajukan 1 jam oleh $this->travel()
        $sesi = SesiVerifikasi::factory()->create([
            'nik'            => '1111111111111111',
            'no_wa'          => '08111111111',
            'status'         => 'verified',
            'berlaku_hingga' => now()->addHours(2),
        ]);

        // Buat dua permohonan dengan selisih waktu
        $lama = $this->buatPermohonan('1111111111111111', '08111111111', 'domisili');
        $this->travel(1)->hours(); // maju waktu
        $baru = $this->buatPermohonan('1111111111111111', '08111111111', 'sktm');

        $response = $this->agentGetJson("/api/permohonan?sesi_id={$sesi->id}");
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        // Index 0 = paling baru
        $this->assertEquals($baru->id, $ids[0]);
        $this->assertEquals($lama->id, $ids[1]);
    }
}

