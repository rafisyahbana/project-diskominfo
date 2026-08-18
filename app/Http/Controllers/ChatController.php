<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ConversationOrchestrator;

class ChatController extends Controller
{
    protected ConversationOrchestrator $orchestrator;

    public function __construct(ConversationOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Endpoint HANYA untuk development/testing manual.
     * HARUS dihapus atau dinonaktifkan di production.
     * Nanti akan digantikan oleh Webhook WhatsApp asli.
     *
     * Menerima multipart/form-data:
     *   - no_wa  : string (required)
     *   - pesan  : string (required)
     *   - file   : file   (optional) — untuk mensimulasikan kiriman foto dokumen
     */
    public function simulasi(Request $request)
    {
        $request->validate([
            'no_wa' => 'required|string',
            'pesan' => 'required|string',
            'file'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // max 5 MB
        ]);

        $noWa   = $request->input('no_wa');
        $pesan  = $request->input('pesan');
        $file   = $request->file('file'); // UploadedFile|null

        $respons = $this->orchestrator->tangani($noWa, $pesan, $file);

        return response()->json([
            'no_wa'   => $noWa,
            'respons' => $respons,
        ]);
    }
}
