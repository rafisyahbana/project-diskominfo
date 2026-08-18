<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Petugas;
use App\Models\Permohonan;
use App\Models\NotifikasiTerkirim;

class WhatsAppNotifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makePetugas(): Petugas
    {
        return Petugas::create([
            'nama'     => 'Admin Test',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);
    }

    private function makePermohonan(string $status, string $noWa = '08111222333'): Permohonan
    {
        return Permohonan::create([
            'nik'         => '1234567890123456',
            'no_wa'       => $noWa,
            'jenis_surat' => 'domisili',
            'data_form'   => [
                'nama_lengkap'   => 'Budi Santoso',
                'alamat_tinggal' => 'Jl. Merdeka No. 10',
                'lama_tinggal'   => '5 tahun',
                'keperluan'      => 'BPJS',
            ],
            'status' => $status,
        ]);
    }

    public function test_terbitkan_surat_mengirim_notifikasi_ke_warga()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('diproses');

        $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/terbitkan");

        // Harus ada tepat 1 record notifikasi
        $this->assertDatabaseCount('notifikasi_terkirim', 1);

        $notif = NotifikasiTerkirim::first();
        $this->assertEquals($permohonan->no_wa, $notif->no_wa);

        // Pesan harus mengandung nomor_surat
        $nomorSurat = $permohonan->fresh()->nomor_surat;
        $this->assertStringContainsString($nomorSurat, $notif->pesan);

        // Pesan harus mengandung link unduh — URL mengandung path '/surat/'
        $this->assertStringContainsString('/surat/', $notif->pesan);
        // dan ID permohonan
        $this->assertStringContainsString($permohonan->id, $notif->pesan);
        // dan signature query param
        $this->assertStringContainsString('signature=', $notif->pesan);
    }

    public function test_reject_permohonan_mengirim_notifikasi_dengan_alasan()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('menunggu_verifikasi');
        $alasan     = 'Dokumen yang dilampirkan tidak terbaca dengan jelas.';

        $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/reject", [
                'catatan_petugas' => $alasan,
            ]);

        $this->assertDatabaseCount('notifikasi_terkirim', 1);

        $notif = NotifikasiTerkirim::first();
        $this->assertEquals($permohonan->no_wa, $notif->no_wa);

        // Alasan penolakan harus ada di pesan
        $this->assertStringContainsString($alasan, $notif->pesan);

        // Kata kunci "ditolak" harus ada
        $this->assertStringContainsStringIgnoringCase('ditolak', $notif->pesan);
    }

    public function test_approve_saja_tidak_mengirim_notifikasi()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('menunggu_verifikasi');

        $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/approve");

        // Approve ke 'diproses' adalah status antara — TIDAK ada notif
        $this->assertDatabaseCount('notifikasi_terkirim', 0);
    }
}
