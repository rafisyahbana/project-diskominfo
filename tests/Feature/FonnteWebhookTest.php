<?php

namespace Tests\Feature;

use App\Models\PercakapanState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FonnteWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Bersihkan konfigurasi fonnte di awal test
        Config::set('services.fonnte.secret', null);
        Config::set('services.fonnte.device', null);
        Config::set('services.fonnte.token', 'dummy-token-test');

        Http::preventStrayRequests();
    }

    public function test_webhook_menolak_request_jika_secret_salah()
    {
        Config::set('services.fonnte.secret', 'secret-rahasia');

        $response = $this->postJson('/api/webhook/fonnte?secret=salah', [
            'sender' => '628111',
            'message' => 'Halo',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_menolak_request_jika_device_salah()
    {
        Config::set('services.fonnte.secret', 'secret-rahasia');
        Config::set('services.fonnte.device', 'device123');

        $response = $this->postJson('/api/webhook/fonnte?secret=secret-rahasia', [
            'sender' => '628111',
            'message' => 'Halo',
            'device' => 'device-lain',
        ]);

        $response->assertStatus(401); // Diubah ke 401 sesuai permintaan
    }

    public function test_webhook_berhasil_jika_semua_validasi_lulus()
    {
        Config::set('services.fonnte.secret', 'secret-rahasia');
        Config::set('services.fonnte.device', 'device123');

        Http::fake([
            'https://api.fonnte.com/send' => Http::response(['status' => true], 200),
        ]);

        $response = $this->postJson('/api/webhook/fonnte?secret=secret-rahasia', [
            'sender' => '628111',
            'message' => 'Halo',
            'device' => 'device123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('percakapan_states', [
            'no_wa' => '628111',
        ]);

        // Verifikasi notifikasi tercatat — status_terkirim ditentukan respons Fonnte
        $this->assertDatabaseCount('notifikasi_terkirim', 1);
        $notif = \App\Models\NotifikasiTerkirim::first();
        $this->assertEquals('628111', $notif->no_wa);

        // Verifikasi Http::fake mencegat request ke api.fonnte.com
        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'api.fonnte.com/send')
        );
    }

    public function test_notifikasi_terkirim_mencatat_gagal_jika_fonnte_tolak_token()
    {
        // Test ini memverifikasi logika internal FonnteService:
        // ketika Fonnte API merespons {status:false, reason:"invalid token"},
        // NotifikasiTerkirim harus disimpan dengan status_terkirim=false dan error_pesan terisi.
        Config::set('services.fonnte.token', 'dummy-token-test');

        Http::fake([
            'https://api.fonnte.com/send' => Http::response(
                ['status' => false, 'reason' => 'invalid token'],
                200
            ),
        ]);

        $service = new \App\Services\FonnteService();
        $result = $service->kirim('628111', 'Test pesan');

        $this->assertFalse($result);

        // Notifikasi harus tercatat sebagai GAGAL dengan error_pesan yang jelas
        $this->assertDatabaseHas('notifikasi_terkirim', [
            'no_wa'           => '628111',
            'status_terkirim' => 0,
            'error_pesan'     => 'invalid token',
        ]);
    }


    public function test_webhook_memanggil_notifier_setelah_berhasil_memproses_pesan()
    {
        Config::set('services.fonnte.secret', 'secret-rahasia');
        Config::set('services.fonnte.device', 'device123');

        // Mock WhatsAppNotifier untuk memastikan kirim dipanggil
        $mockNotifier = \Mockery::mock(\App\Contracts\WhatsAppNotifier::class);
        $this->app->instance(\App\Contracts\WhatsAppNotifier::class, $mockNotifier);

        // Expectation: kirim harus dipanggil 1 kali untuk sender 628111
        $mockNotifier->shouldReceive('kirim')
            ->once()
            ->with('628111', \Mockery::type('string'));

        Http::fake([
            'https://api.fonnte.com/send' => Http::response(['status' => true], 200),
        ]);

        $response = $this->postJson('/api/webhook/fonnte?secret=secret-rahasia', [
            'sender' => '628111',
            'message' => 'Halo',
            'device' => 'device123',
        ]);

        $response->assertStatus(200);
    }
}
