<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\Petugas;
use App\Models\Permohonan;

class ApproveRejectTest extends TestCase
{
    use RefreshDatabase;

    private function makePetugas(): Petugas
    {
        return Petugas::create([
            'nama'     => 'Admin Test',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);
    }

    private function makePermohonan(string $status = 'menunggu_verifikasi'): Permohonan
    {
        return Permohonan::create([
            'nik'         => '1234567890123456',
            'no_wa'       => '08111222333',
            'jenis_surat' => 'domisili',
            'data_form'   => ['nama_lengkap' => 'Budi'],
            'status'      => $status,
        ]);
    }

    // ── Approve ──────────────────────────────────────────────────────────────

    public function test_approve_permohonan_menunggu_verifikasi_berhasil()
    {
        $petugas     = $this->makePetugas();
        $permohonan  = $this->makePermohonan('menunggu_verifikasi');

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/approve");

        $response->assertRedirect("/dashboard/permohonan/{$permohonan->id}");
        $response->assertSessionHas('success');

        $permohonan->refresh();
        $this->assertEquals('diproses', $permohonan->status);
        $this->assertEquals($petugas->id, $permohonan->diproses_oleh);
    }

    public function test_approve_permohonan_sudah_diproses_ditolak_race_condition()
    {
        $petugas    = $this->makePetugas();
        // Sudah diproses oleh petugas lain
        $permohonan = $this->makePermohonan('diproses');

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/approve");

        $response->assertSessionHasErrors('status');

        // Status harus tetap 'diproses', tidak berubah lagi
        $this->assertEquals('diproses', $permohonan->fresh()->status);
    }

    // ── Reject ───────────────────────────────────────────────────────────────

    public function test_reject_tanpa_catatan_gagal_validasi()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('menunggu_verifikasi');

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/reject", [
                'catatan_petugas' => '',
            ]);

        $response->assertSessionHasErrors('catatan_petugas');
        $this->assertEquals('menunggu_verifikasi', $permohonan->fresh()->status);
    }

    public function test_reject_catatan_terlalu_pendek_gagal_validasi()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('menunggu_verifikasi');

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/reject", [
                'catatan_petugas' => 'Singkat',
            ]);

        $response->assertSessionHasErrors('catatan_petugas');
        $this->assertEquals('menunggu_verifikasi', $permohonan->fresh()->status);
    }

    public function test_reject_dengan_catatan_berhasil()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('menunggu_verifikasi');

        $alasan = 'Dokumen yang dilampirkan tidak terbaca dengan jelas.';

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/reject", [
                'catatan_petugas' => $alasan,
            ]);

        $response->assertRedirect("/dashboard/permohonan/{$permohonan->id}");
        $response->assertSessionHas('success');

        $permohonan->refresh();
        $this->assertEquals('ditolak', $permohonan->status);
        $this->assertEquals($alasan, $permohonan->catatan_petugas);
        $this->assertEquals($petugas->id, $permohonan->diproses_oleh);
    }

    public function test_reject_permohonan_sudah_diproses_ditolak_race_condition()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('diproses');

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/reject", [
                'catatan_petugas' => 'Dokumen tidak sesuai persyaratan yang diminta.',
            ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('diproses', $permohonan->fresh()->status);
    }
}
