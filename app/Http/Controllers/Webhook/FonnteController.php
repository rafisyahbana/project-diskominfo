<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\ConversationOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FonnteController extends Controller
{
    protected ConversationOrchestrator $orchestrator;
    protected \App\Contracts\WhatsAppNotifier $notifier;

    public function __construct(ConversationOrchestrator $orchestrator, \App\Contracts\WhatsAppNotifier $notifier)
    {
        $this->orchestrator = $orchestrator;
        $this->notifier = $notifier;
    }

    public function handle(Request $request)
    {
        // LOG SEMENTARA UNTUK DEBUG PAYLOAD GAMBAR FONNTE
        Log::info('[Fonnte Webhook] Raw Payload Masuk:', $request->all());

        // 1. Validasi Token/Secret & Device
        $fonnteSecret = config('services.fonnte.secret');
        $fonnteDevice = config('services.fonnte.device');

        // Validasi secret via Query String (karena Fonnte tidak mengirim di body/header)
        $requestSecret = $request->query('secret');
        if ($fonnteSecret && $requestSecret !== $fonnteSecret) {
            Log::warning('[Fonnte Webhook] Secret tidak valid.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validasi device via Body Payload
        $requestDevice = $request->input('device');
        if ($fonnteDevice && $requestDevice !== $fonnteDevice) {
            Log::warning('[Fonnte Webhook] Device tidak cocok.', ['device' => $requestDevice]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Ambil data pesan
        $sender = $request->input('sender');
        $message = $request->input('message') ?? '';
        $url = $request->input('url');

        if (!$sender) {
            return response()->json(['error' => 'Sender is required'], 400);
        }

        // 3. Handle File Media dari URL jika ada
        $uploadedFile = null;
        $tempPath = null;
        if ($url) {
            try {
                $response = Http::get($url);
                if ($response->successful()) {
                    $filename = basename(parse_url($url, PHP_URL_PATH));
                    if (!$filename || $filename === '') {
                        $filename = 'media.jpg'; // fallback
                    }
                    $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '_' . $filename;
                    file_put_contents($tempPath, $response->body());
                    
                    // $test parameter set to true allows arbitrary paths for UploadedFile
                    $uploadedFile = new UploadedFile($tempPath, $filename, null, null, true);
                } else {
                    Log::error('[Fonnte Webhook] Gagal mengunduh file media', ['url' => $url, 'status' => $response->status()]);
                }
            } catch (\Exception $e) {
                Log::error('[Fonnte Webhook] Exception saat mengunduh media', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        // 4. Proses via Orchestrator & Kirim Balasan
        try {
            $responsString = $this->orchestrator->tangani($sender, $message, $uploadedFile);
            
            // 5. Kirim pesan balasan via WhatsAppNotifier
            try {
                $this->notifier->kirim($sender, $responsString);
            } catch (\Exception $e) {
                Log::error('[Fonnte Webhook] Gagal mengirim balasan via notifier', [
                    'sender' => $sender,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[Fonnte Webhook] Error saat memproses pesan', [
                'sender' => $sender,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // Hapus file temp jika ada
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        // 5. Selalu return 200 OK agar Fonnte tahu webhook sukses
        return response()->json(['status' => 'success']);
    }
}
