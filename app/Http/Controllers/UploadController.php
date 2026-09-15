<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PercakapanState;
use App\Models\DokumenPermohonan;
use App\Models\SesiVerifikasi;
use App\Contracts\WhatsAppNotifier;
use App\Services\ReferensiService;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    protected WhatsAppNotifier $notifier;
    protected ReferensiService $referensiService;

    public function __construct(WhatsAppNotifier $notifier, ReferensiService $referensiService)
    {
        $this->notifier = $notifier;
        $this->referensiService = $referensiService;
    }

    public function showForm($no_wa, $sesi_id, $jenis_dokumen)
    {
        $sesi = SesiVerifikasi::find($sesi_id);
        if (!$sesi || !$sesi->isVerifiedAndActive()) {
            return response()->view('errors.custom', [
                'message' => 'Sesi Anda tidak valid atau telah kedaluwarsa. Silakan mulai ulang dari WhatsApp.'
            ], 403);
        }

        if ($sesi->no_wa !== $no_wa) {
            return response()->view('errors.custom', ['message' => 'Akses ditolak.'], 403);
        }

        $state = PercakapanState::where('no_wa', $no_wa)->first();
        if (!$state || !in_array($state->langkah, ['menunggu_dokumen', 'menunggu_konfirmasi'])) {
            return response()->view('errors.custom', ['message' => 'Akses ditolak atau sesi tidak valid.'], 403);
        }

        // Check for duplicates
        $dokumenDiterima = $state->dokumen_diterima ?? [];
        if (array_key_exists($jenis_dokumen, $dokumenDiterima)) {
            return response()->view('errors.custom', [
                'message' => 'Dokumen ini sudah diunggah sebelumnya. Anda tidak perlu mengunggah ulang.'
            ], 409);
        }

        $labelDokumen = $this->labelDokumen($jenis_dokumen);

        // Generate signed POST URL (berlaku 60 menit dari sekarang)
        // sehingga form action sudah mengandung signature yang valid.
        $postUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'upload.process',
            now()->addMinutes(60),
            ['no_wa' => $no_wa, 'sesi_id' => $sesi_id, 'jenis_dokumen' => $jenis_dokumen]
        );

        return view('upload.form', compact('no_wa', 'sesi_id', 'jenis_dokumen', 'labelDokumen', 'postUrl'));
    }

    public function process(Request $request, $no_wa, $sesi_id, $jenis_dokumen)
    {
        $sesi = SesiVerifikasi::find($sesi_id);
        if (!$sesi || !$sesi->isVerifiedAndActive()) {
            return response()->view('errors.custom', [
                'message' => 'Sesi Anda tidak valid atau telah kedaluwarsa. Silakan mulai ulang dari WhatsApp.'
            ], 403);
        }

        if ($sesi->no_wa !== $no_wa) {
            return response()->view('errors.custom', ['message' => 'Akses ditolak.'], 403);
        }

        $state = PercakapanState::where('no_wa', $no_wa)->first();
        if (!$state || !in_array($state->langkah, ['menunggu_dokumen', 'menunggu_konfirmasi'])) {
            return response()->view('errors.custom', ['message' => 'Akses ditolak atau sesi tidak valid.'], 403);
        }

        $dokumenDiterima = $state->dokumen_diterima ?? [];
        if (array_key_exists($jenis_dokumen, $dokumenDiterima)) {
            return response()->view('errors.custom', [
                'message' => 'Dokumen ini sudah diunggah sebelumnya. Anda tidak perlu mengunggah ulang.'
            ], 409);
        }

        $request->validate([
            'file' => 'required|image|max:10240' // max 10MB
        ]);

        $file = $request->file('file');
        $folder = 'dokumen/' . $no_wa;
        $pathFile = Storage::disk('local')->putFile($folder, $file);

        // Tahap 2: Tesseract OCR non-blocking will go here later

        $dokumenRecord = DokumenPermohonan::create([
            'no_wa'          => $no_wa,
            'jenis_dokumen'  => $jenis_dokumen,
            'path_file'      => $pathFile,
            'id_permohonan'  => null,
            'status'         => 'aktif',
        ]);

        $dokumenDiterima[$jenis_dokumen] = $dokumenRecord->id;
        $state->update(['dokumen_diterima' => $dokumenDiterima]);

        // Dispatch OCR job secara async (non-blocking untuk state machine).
        // Job hanya memperbarui status_ocr di DB dan mengirim notif informatif ke warga.
        // OCR TIDAK PERNAH memblokir alur pengajuan — state machine berjalan tanpa menunggu OCR.
        if ($jenis_dokumen === 'fotokopi_ktp') {
            \App\Jobs\VerifikasiOcrJob::dispatch(
                $dokumenRecord->id,
                $sesi_id,
                $no_wa
            );

            // Jika antrean berjalan sinkron (sync), cek apakah dokumen langsung ditolak oleh OCR
            $dokumenRecord->refresh();
            if ($dokumenRecord->status === 'ditolak') {
                // Job sudah menghapus state dan mengirim pesan penolakan WhatsApp.
                // Jangan kirim pesan sukses dari controller.
                return view('upload.success', ['message' => 'Foto KTP telah diproses. Silakan periksa pesan WhatsApp Anda untuk hasil verifikasi.']);
            }
        }

        // Pastikan state selalu up-to-date (untuk menghindari stale memory jika queue sync memodifikasi state)
        $state->refresh();
        $dokumenDiterima = $state->dokumen_diterima ?? [];

        $katalog = $this->referensiService->syarat($state->jenis_surat_dipilih);
        $dokumenWajib = $katalog['dokumen_wajib'];
        $dokumenBerikutnya = $this->getDokumenMenunggu($dokumenWajib, $dokumenDiterima);

        if ($dokumenBerikutnya !== null) {
            $linkBaru = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'upload.form',
                now()->addMinutes(60),
                ['no_wa' => $no_wa, 'sesi_id' => $sesi_id, 'jenis_dokumen' => $dokumenBerikutnya]
            );

            $privacyKtp = ($dokumenBerikutnya === 'fotokopi_ktp')
                ? "\n\nUntuk perlindungan data pribadi, foto KTP hanya digunakan untuk proses pembacaan data permohonan dan tidak disimpan sebagai dokumen permanen pada sistem layanan."
                : "";

            $pesan = "✓ *{$this->labelDokumen($jenis_dokumen)}* berhasil diterima.\n\n"
                   . "Selanjutnya, silakan buka tautan berikut untuk mengunggah foto *{$this->labelDokumen($dokumenBerikutnya)}*:\n"
                   . "{$linkBaru}\n"
                   . "(Tautan berlaku selama 60 menit)"
                   . $privacyKtp;
            
            $this->notifier->kirim($no_wa, $pesan);
        } else {
            // Semua lengkap
            $ringkasanForm = implode("\n", array_map(
                fn($k, $v) => "• {$this->labelField($k)}: {$v}",
                array_keys($state->form_sementara ?? []),
                array_values($state->form_sementara ?? [])
            ));

            $ringkasanDokumen = implode("\n", array_map(
                fn($d) => "• {$this->labelDokumen($d)} ✓",
                array_keys($dokumenDiterima)
            ));

            $state->update(['langkah' => 'menunggu_konfirmasi']);

            $pesan = "✓ *{$this->labelDokumen($jenis_dokumen)}* berhasil diterima. Semua dokumen lengkap!\n\n"
                   . "*Ringkasan Pengajuan*\n"
                   . "Jenis Surat: *{$katalog['nama']}*\n\n"
                   . "*Data Form:*\n{$ringkasanForm}\n\n"
                   . "*Dokumen:*\n{$ringkasanDokumen}\n\n"
                   . "Ketik *YA* untuk konfirmasi dan kirim permohonan, atau *BATAL* untuk membatalkan.";
            
            $this->notifier->kirim($no_wa, $pesan);
        }

        return view('upload.success', ['message' => 'Dokumen berhasil diunggah! Silakan kembali ke WhatsApp Anda untuk instruksi selanjutnya.']);
    }

    private function getDokumenMenunggu(array $dokumenWajib, array $diterima): ?string
    {
        foreach ($dokumenWajib as $dok) {
            if (!array_key_exists($dok, $diterima)) {
                return $dok;
            }
        }
        return null;
    }

    private function labelDokumen(string $jenisDokumen): string
    {
        return match ($jenisDokumen) {
            'fotokopi_ktp'      => 'fotokopi KTP',
            'fotokopi_kk'       => 'fotokopi Kartu Keluarga (KK)',
            'dokumen_pendukung' => 'dokumen pendukung',
            default             => ucwords(str_replace('_', ' ', $jenisDokumen)),
        };
    }

    private function labelField(string $field): string
    {
        return ReferensiService::LABEL_FIELD[$field]
            ?? ucwords(str_replace('_', ' ', $field));
    }

    public function showFormRegistrasi($no_wa)
    {
        $state = PercakapanState::where('no_wa', $no_wa)->first();
        if (!$state || $state->langkah !== 'menunggu_upload_ktp_registrasi') {
            return response()->view('errors.custom', ['message' => 'Akses ditolak atau sesi tidak valid.'], 403);
        }

        $jenis_dokumen = 'fotokopi_ktp';
        $labelDokumen = 'Foto KTP Asli';

        $postUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'upload.process_registrasi',
            now()->addMinutes(60),
            ['no_wa' => $no_wa]
        );

        return view('upload.form', compact('no_wa', 'jenis_dokumen', 'labelDokumen', 'postUrl'));
    }

    public function processRegistrasi(Request $request, $no_wa)
    {
        $state = PercakapanState::where('no_wa', $no_wa)->first();
        if (!$state || $state->langkah !== 'menunggu_upload_ktp_registrasi') {
            return response()->view('errors.custom', ['message' => 'Akses ditolak atau sesi tidak valid.'], 403);
        }

        $request->validate([
            'file' => 'required|image|max:10240' // max 10MB
        ]);

        $file = $request->file('file');
        $folder = 'dokumen/' . $no_wa;
        $pathFile = Storage::disk('local')->putFile($folder, $file);

        $dokumenRecord = DokumenPermohonan::create([
            'no_wa'          => $no_wa,
            'jenis_dokumen'  => 'fotokopi_ktp',
            'path_file'      => $pathFile,
            'id_permohonan'  => null,
            'status'         => 'aktif',
        ]);

        $dokumenDiterima = $state->dokumen_diterima ?? [];
        $dokumenDiterima['fotokopi_ktp'] = $dokumenRecord->id;
        
        $formSementara = $state->form_sementara ?? [];
        $formSementara['percobaan_gagal_nik'] = 0; // Reset counter 

        $state->update([
            'dokumen_diterima' => $dokumenDiterima,
            'langkah'          => 'menunggu_nik_registrasi',
            'form_sementara'   => $formSementara
        ]);

        // Dispatch OCR khusus ekstrak NIK tanpa membandingkan
        \App\Jobs\EkstrakNikDariKtpJob::dispatch(
            $dokumenRecord->id,
            $no_wa
        );

        $pesan = "✓ *Foto KTP* berhasil diterima.\n\n"
               . "Sistem sedang memproses foto Anda. Untuk keamanan, silakan ketik *16 Digit NIK* Anda sekarang untuk pencocokan manual.";
        
        $this->notifier->kirim($no_wa, $pesan);

        return view('upload.success', ['message' => 'Foto KTP berhasil diunggah! Silakan kembali ke WhatsApp Anda untuk instruksi selanjutnya.']);
    }
}
