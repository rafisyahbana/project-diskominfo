<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use App\Models\Petugas;
use App\Models\Permohonan;

class SuratPdfTest extends TestCase
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

    private function makePermohonan(string $status = 'diproses'): Permohonan
    {
        return Permohonan::create([
            'nik'         => '1234567890123456',
            'no_wa'       => '08111222333',
            'jenis_surat' => 'domisili',
            'data_form'   => [
                'nama_lengkap'   => 'Budi Santoso',
                'alamat_tinggal' => 'Jl. Merdeka No. 10',
                'lama_tinggal'   => '5 tahun',
                'keperluan'      => 'BPJS',
            ],
            'status'      => $status,
        ]);
    }

    // ── Terbitkan ─────────────────────────────────────────────────────────────

    public function test_terbitkan_surat_berhasil_dan_file_pdf_tersimpan()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('diproses');

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/terbitkan");

        $response->assertRedirect("/dashboard/permohonan/{$permohonan->id}");
        $response->assertSessionHas('success');

        $permohonan->refresh();
        $this->assertEquals('selesai', $permohonan->status);

        // Nomor surat sesuai format SURAT-YYYYMMDD-0001
        $this->assertMatchesRegularExpression(
            '/^SURAT-\d{8}-\d{4}$/',
            $permohonan->nomor_surat
        );

        // File PDF tersimpan di storage
        $this->assertNotNull($permohonan->file_surat_url);
        Storage::disk('local')->assertExists($permohonan->file_surat_url);
    }

    public function test_terbitkan_dua_surat_dihari_yang_sama_nomor_berurutan()
    {
        $petugas = $this->makePetugas();

        $p1 = Permohonan::create([
            'nik' => '1111111111111111', 'no_wa' => '081',
            'jenis_surat' => 'domisili',
            'data_form'   => ['nama_lengkap' => 'Warga A', 'alamat_tinggal' => 'Jl. A', 'lama_tinggal' => '1 tahun', 'keperluan' => 'Test'],
            'status' => 'diproses',
        ]);
        $p2 = Permohonan::create([
            'nik' => '2222222222222222', 'no_wa' => '082',
            'jenis_surat' => 'domisili',
            'data_form'   => ['nama_lengkap' => 'Warga B', 'alamat_tinggal' => 'Jl. B', 'lama_tinggal' => '2 tahun', 'keperluan' => 'Test'],
            'status' => 'diproses',
        ]);

        $this->actingAs($petugas, 'petugas')->post("/dashboard/permohonan/{$p1->id}/terbitkan");
        $this->actingAs($petugas, 'petugas')->post("/dashboard/permohonan/{$p2->id}/terbitkan");

        $n1 = $p1->fresh()->nomor_surat;
        $n2 = $p2->fresh()->nomor_surat;

        $this->assertNotNull($n1);
        $this->assertNotNull($n2);
        $this->assertNotEquals($n1, $n2);

        // Urutan terakhir n2 harus 1 lebih besar dari n1
        $seq1 = (int) substr($n1, -4);
        $seq2 = (int) substr($n2, -4);
        $this->assertEquals($seq1 + 1, $seq2);
    }

    public function test_terbitkan_untuk_status_bukan_diproses_ditolak()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('menunggu_verifikasi');

        $response = $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/terbitkan");

        $response->assertSessionHasErrors('status');
        $this->assertEquals('menunggu_verifikasi', $permohonan->fresh()->status);
        $this->assertNull($permohonan->fresh()->nomor_surat);
    }

    // ── Unduh via Signed URL ──────────────────────────────────────────────────

    public function test_unduh_surat_dengan_signed_url_berhasil()
    {
        $petugas    = $this->makePetugas();
        $permohonan = $this->makePermohonan('diproses');

        // Terbitkan dulu
        $this->actingAs($petugas, 'petugas')
            ->post("/dashboard/permohonan/{$permohonan->id}/terbitkan");

        $permohonan->refresh();
        $signedUrl = URL::temporarySignedRoute(
            'surat.unduh',
            now()->addDays(30),
            ['permohonan' => $permohonan->id]
        );

        // Unduh tanpa login petugas (anon)
        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unduh_surat_tanpa_signature_ditolak_403()
    {
        $permohonan = $this->makePermohonan('selesai');
        // Akses langsung tanpa signature
        $response = $this->get("/surat/{$permohonan->id}/unduh");
        $response->assertStatus(403);
    }

    public function test_unduh_surat_dengan_signature_palsu_ditolak_403()
    {
        $permohonan = $this->makePermohonan('selesai');
        $response = $this->get("/surat/{$permohonan->id}/unduh?signature=ini-palsu-sekali");
        $response->assertStatus(403);
    }
}
