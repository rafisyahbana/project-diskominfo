<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use App\Models\Petugas;
use App\Models\Permohonan;
use App\Models\DokumenPermohonan;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createDummyPetugas()
    {
        return Petugas::create([
            'nama'     => 'Admin Test',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);
    }

    public function test_akses_permohonan_tanpa_login_redirect_ke_login()
    {
        $response = $this->get('/dashboard/permohonan');
        $response->assertRedirect('/dashboard/login');
    }

    public function test_akses_permohonan_dengan_login_berhasil()
    {
        $petugas = $this->createDummyPetugas();

        $response = $this->actingAs($petugas, 'petugas')->get('/dashboard/permohonan');
        $response->assertStatus(200);
    }

    public function test_filter_menunggu_verifikasi_di_list_permohonan()
    {
        $petugas = $this->createDummyPetugas();

        // Buat dummy permohonan
        Permohonan::create([
            'nik'         => '111',
            'no_wa'       => '081',
            'jenis_surat' => 'domisili',
            'data_form'   => [],
            'status'      => 'menunggu_verifikasi'
        ]);

        Permohonan::create([
            'nik'         => '222',
            'no_wa'       => '082',
            'jenis_surat' => 'sktm',
            'data_form'   => [],
            'status'      => 'diproses' // bukan menunggu_verifikasi
        ]);

        $response = $this->actingAs($petugas, 'petugas')->get('/dashboard/permohonan');
        $response->assertStatus(200);
        $response->assertSee('111'); // NIK menunggu_verifikasi tampil
        $response->assertDontSee('222'); // NIK diproses tidak tampil di default view
    }

    public function test_akses_register_dinonaktifkan()
    {
        // Harus 404 karena route register sudah dihapus
        $response = $this->get('/dashboard/register');
        $response->assertStatus(404);

        $response = $this->get('/register');
        $response->assertStatus(404);
    }

    public function test_akses_preview_dokumen_tanpa_login_redirect()
    {
        Storage::fake('local');
        $doc = DokumenPermohonan::create([
            'no_wa' => '081',
            'jenis_dokumen' => 'fotokopi_ktp',
            'path_file' => 'test.jpg',
            'status' => 'aktif'
        ]);

        $response = $this->get("/dashboard/dokumen/{$doc->id}/preview");
        $response->assertRedirect('/dashboard/login');
    }

    public function test_akses_preview_dokumen_berhasil_dengan_login()
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('test.jpg');
        $path = $file->storeAs('dokumen/081', 'test.jpg', 'local');

        $doc = DokumenPermohonan::create([
            'no_wa' => '081',
            'jenis_dokumen' => 'fotokopi_ktp',
            'path_file' => $path,
            'status' => 'aktif'
        ]);

        $petugas = $this->createDummyPetugas();
        $response = $this->actingAs($petugas, 'petugas')->get("/dashboard/dokumen/{$doc->id}/preview");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
}
