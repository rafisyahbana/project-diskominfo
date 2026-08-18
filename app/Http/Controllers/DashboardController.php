<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppNotifier;
use App\Models\Permohonan;
use App\Models\DokumenPermohonan;
use App\Services\ReferensiService;
use App\Services\SuratPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'menunggu_verifikasi');

        $permohonan = Permohonan::where('status', $status)
            ->orderBy('created_at', 'asc')
            ->paginate(15);

        return view('dashboard.permohonan.index', compact('permohonan', 'status'));
    }

    public function show(string $id)
    {
        $permohonan = Permohonan::with('petugas')->findOrFail($id);
        $dokumen    = DokumenPermohonan::where('id_permohonan', $id)->get();
        $labelFields = ReferensiService::LABEL_FIELD;

        return view('dashboard.permohonan.show', compact('permohonan', 'dokumen', 'labelFields'));
    }

    public function approve(string $id)
    {
        $permohonan = Permohonan::findOrFail($id);

        if ($permohonan->status !== 'menunggu_verifikasi') {
            return back()->withErrors([
                'status' => "Permohonan tidak dapat disetujui karena statusnya sudah '{$permohonan->status}'."
                         . " Kemungkinan sudah diproses oleh petugas lain.",
            ]);
        }

        $permohonan->update([
            'status'        => 'diproses',
            'diproses_oleh' => Auth::guard('petugas')->id(),
        ]);

        // ⚠ Approve ke 'diproses' adalah status antara (bukan final) — TIDAK kirim notif ke warga.

        return redirect()
            ->route('dashboard.permohonan.show', $id)
            ->with('success', 'Permohonan berhasil disetujui dan sedang diproses.');
    }

    public function reject(Request $request, string $id)
    {
        $request->validate([
            'catatan_petugas' => ['required', 'string', 'min:10'],
        ], [
            'catatan_petugas.required' => 'Alasan penolakan wajib diisi.',
            'catatan_petugas.min'      => 'Alasan penolakan minimal 10 karakter.',
        ]);

        $permohonan = Permohonan::findOrFail($id);

        if ($permohonan->status !== 'menunggu_verifikasi') {
            return back()->withErrors([
                'status' => "Permohonan tidak dapat ditolak karena statusnya sudah '{$permohonan->status}'."
                          . " Kemungkinan sudah diproses oleh petugas lain.",
            ])->withInput();
        }

        $permohonan->update([
            'status'          => 'ditolak',
            'catatan_petugas' => $request->catatan_petugas,
            'diproses_oleh'   => Auth::guard('petugas')->id(),
        ]);

        // Notifikasi ke warga: permohonan ditolak
        $pesan = $this->susunPesanDitolak($permohonan->fresh());
        $this->kirimNotifikasi($permohonan->no_wa, $pesan, $permohonan->id);

        return redirect()
            ->route('dashboard.permohonan.show', $id)
            ->with('success', 'Permohonan berhasil ditolak.');
    }

    public function previewDokumen(string $id)
    {
        $dokumen = DokumenPermohonan::findOrFail($id);

        if (!Storage::disk('local')->exists($dokumen->path_file)) {
            abort(404, 'Dokumen tidak ditemukan di storage.');
        }

        return Storage::disk('local')->response($dokumen->path_file);
    }

    public function terbitkan(string $id)
    {
        $permohonan = Permohonan::findOrFail($id);

        if ($permohonan->status !== 'diproses') {
            return back()->withErrors([
                'status' => "Surat hanya bisa diterbitkan untuk permohonan berstatus 'diproses'."
                          . " Status saat ini: '{$permohonan->status}'.",
            ]);
        }

        $service  = new SuratPdfService();
        $pathFile = $service->generate($permohonan);

        $permohonan->update([
            'status'         => 'selesai',
            'file_surat_url' => $pathFile,
        ]);

        $permohonan->refresh();

        // Bangun signed URL unduh warga (30 hari)
        $linkUnduh = URL::temporarySignedRoute(
            'surat.unduh',
            now()->addDays(30),
            ['permohonan' => $permohonan->id]
        );

        // Notifikasi ke warga: surat selesai
        $pesan = $this->susunPesanSelesai($permohonan, $linkUnduh);
        $this->kirimNotifikasi($permohonan->no_wa, $pesan, $permohonan->id);

        return redirect()
            ->route('dashboard.permohonan.show', $id)
            ->with('success', "Surat berhasil diterbitkan dengan nomor {$permohonan->nomor_surat}.");
    }

    /**
     * Route publik (signed URL, 30 hari) untuk warga mengunduh surat PDF.
     * Tidak perlu login petugas.
     */
    public function unduhSurat(Permohonan $permohonan)
    {
        if (!$permohonan->file_surat_url || !Storage::disk('local')->exists($permohonan->file_surat_url)) {
            abort(404, 'File surat tidak ditemukan.');
        }

        $filename = "surat-{$permohonan->nomor_surat}.pdf";
        return Storage::disk('local')->download($permohonan->file_surat_url, $filename);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function susunPesanSelesai(Permohonan $permohonan, string $linkUnduh): string
    {
        $jenis = ucwords(str_replace('_', ' ', $permohonan->jenis_surat));

        $pesan  = "✅ *Surat Anda Telah Diterbitkan*\n\n";
        $pesan .= "📄 Jenis Surat : {$jenis}\n";
        $pesan .= "🔖 Nomor Surat : {$permohonan->nomor_surat}\n";
        $pesan .= "📅 Tanggal     : " . now()->format('d M Y') . "\n\n";
        $pesan .= "Silakan unduh surat Anda melalui link berikut (berlaku 30 hari):\n";
        $pesan .= $linkUnduh . "\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan kami.";

        return $pesan;
    }

    private function susunPesanDitolak(Permohonan $permohonan): string
    {
        $jenis = ucwords(str_replace('_', ' ', $permohonan->jenis_surat));

        $pesan  = "❌ *Permohonan Surat Ditolak*\n\n";
        $pesan .= "📄 Jenis Surat : {$jenis}\n";
        $pesan .= "📅 Tanggal     : " . now()->format('d M Y') . "\n\n";
        $pesan .= "Alasan penolakan:\n_{$permohonan->catatan_petugas}_\n\n";
        $pesan .= "Jika Anda memiliki pertanyaan, silakan hubungi petugas Diskominfo.";

        return $pesan;
    }

    /**
     * Kirim notifikasi via WhatsAppNotifier.
     * Kegagalan kirim TIDAK membatalkan proses — hanya di-log sebagai warning.
     */
    private function kirimNotifikasi(string $noWa, string $pesan, ?string $idPermohonan = null): void
    {
        try {
            $notifier = app(WhatsAppNotifier::class);
            $berhasil = $notifier->kirim($noWa, $pesan);

            if (!$berhasil) {
                Log::warning('[DashboardController] Notifikasi gagal terkirim', [
                    'no_wa'          => $noWa,
                    'id_permohonan'  => $idPermohonan,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[DashboardController] Exception saat kirim notifikasi', [
                'no_wa'          => $noWa,
                'id_permohonan'  => $idPermohonan,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
