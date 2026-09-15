<?php

namespace App\Services;

use App\Models\PercakapanState;
use App\Models\DokumenPermohonan;
use App\Contracts\LlmResponder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ConversationOrchestrator
{
    protected VerifikasiService $verifikasiService;
    protected PermohonanService $permohonanService;
    protected ReferensiService $referensiService;
    protected LlmResponder $llmResponder;

    public function __construct(
        VerifikasiService $verifikasiService,
        PermohonanService $permohonanService,
        ReferensiService $referensiService,
        LlmResponder $llmResponder
    ) {
        $this->verifikasiService = $verifikasiService;
        $this->permohonanService = $permohonanService;
        $this->referensiService  = $referensiService;
        $this->llmResponder      = $llmResponder;
    }

    /**
     * Titik masuk utama orchestrator.
     * $file opsional — diisi saat warga mengirim foto (tahap menunggu_dokumen).
     */
    public function tangani(string $noWa, string $pesanMasuk, ?UploadedFile $file = null): string
    {
        $state = PercakapanState::firstOrCreate(
            ['no_wa' => $noWa],
            ['langkah' => 'awal']
        );

        // Tambahkan ke riwayat pesan (dibatasi 10 terakhir, FIFO)
        $riwayat   = $state->riwayat_pesan ?? [];
        $riwayat[] = ['role' => 'user', 'content' => $pesanMasuk];
        if (count($riwayat) > 10) {
            $riwayat = array_slice($riwayat, -10);
        }
        $state->riwayat_pesan = $riwayat;
        $state->save();

        // State Machine Router
        $respons = match ($state->langkah) {
            'awal'                           => $this->handleAwal($state, $pesanMasuk),
            'menunggu_upload_ktp_registrasi' => $this->handleMenungguUploadKtpRegistrasi($state, $pesanMasuk),
            'menunggu_nik_registrasi'        => $this->handleMenungguNikRegistrasi($state, $pesanMasuk),
            'menunggu_nik'                   => $this->handleMenungguNik($state, $pesanMasuk), // Dipertahankan untuk kompatibilitas sementara
            'menunggu_otp'                   => $this->handleMenungguOtp($state, $pesanMasuk),
            'menunggu_pilihan_surat'         => $this->handleMenungguPilihanSurat($state, $pesanMasuk),
            'mengisi_form'                   => $this->handleMengisiForm($state, $pesanMasuk),
            'menunggu_dokumen'               => $this->handleMenungguDokumen($state, $pesanMasuk, $file),
            'menunggu_konfirmasi'            => $this->handleMenungguKonfirmasi($state, $pesanMasuk),
            default                          => "Fitur untuk langkah '{$state->langkah}' belum tersedia.",
        };

        // Simpan riwayat respons sistem
        $riwayat   = $state->fresh()->riwayat_pesan ?? [];
        $riwayat[] = ['role' => 'system', 'content' => $respons];
        if (count($riwayat) > 10) {
            $riwayat = array_slice($riwayat, -10);
        }
        $state->riwayat_pesan = $riwayat;
        $state->save();

        return $respons;
    }

    // ── Handlers ─────────────────────────────────────────────────────────────

    private function handleAwal(PercakapanState $state, string $pesanMasuk): string
    {
        $pesanLower = trim(strtolower($pesanMasuk));

        if ($pesanLower === '1') {
            $state->update(['langkah' => 'menunggu_upload_ktp_registrasi']);
            
            $linkUpload = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'upload.ktp_registrasi',
                now()->addMinutes(60),
                ['no_wa' => $state->no_wa]
            );
            
            return "Untuk memulai, kami membutuhkan foto KTP asli Anda untuk verifikasi identitas.\n\n"
                 . "Silakan klik tautan berikut untuk mengunggah foto KTP Anda:\n"
                 . "{$linkUpload}\n\n"
                 . "(Tautan ini berlaku selama 60 menit).";
        } elseif ($pesanLower === '2') {
            return $this->llmResponder->susunRespons('menu_cek_status', $pesanMasuk);
        } elseif ($pesanLower === '3') {
            return $this->llmResponder->susunRespons('menu_bantuan', $pesanMasuk);
        }

        // Jika input tidak valid (misal: "Halo", "5"), kembali tampilkan menu utama tanpa mengubah state
        return $this->llmResponder->susunRespons('menu_awal', $pesanMasuk);
    }

    private function handleMenungguUploadKtpRegistrasi(PercakapanState $state, string $pesanMasuk): string
    {
        $linkUpload = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'upload.ktp_registrasi',
            now()->addMinutes(60),
            ['no_wa' => $state->no_wa]
        );
        return "Kami masih menunggu Anda mengunggah foto KTP.\n\nSilakan klik tautan berikut:\n{$linkUpload}";
    }

    private function handleMenungguNikRegistrasi(PercakapanState $state, string $pesanMasuk): string
    {
        // Cari dokumen KTP yang diunggah
        $dokumenDiterima = $state->dokumen_diterima ?? [];
        $ktpId = $dokumenDiterima['fotokopi_ktp'] ?? null;

        if (!$ktpId) {
            $state->update(['langkah' => 'menunggu_upload_ktp_registrasi']);
            return "Data KTP Anda tidak ditemukan. Silakan ulangi proses upload KTP.";
        }

        $dokumenKtp = DokumenPermohonan::find($ktpId);

        if (!$dokumenKtp) {
            $state->update(['langkah' => 'menunggu_upload_ktp_registrasi']);
            return "Data KTP Anda tidak ditemukan di sistem. Silakan ulangi proses upload KTP.";
        }

        if ($dokumenKtp->status_ocr === 'aktif' || $dokumenKtp->status_ocr === null) {
            return "Sistem masih memproses foto KTP Anda. Mohon tunggu sebentar, lalu ketikkan ulang NIK Anda.";
        }

        if ($dokumenKtp->status_ocr === 'tidak_terbaca') {
            // OCR tidak berhasil membaca teks KTP sama sekali
            $linkUpload = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'upload.ktp_registrasi',
                now()->addMinutes(60),
                ['no_wa' => $state->no_wa]
            );
            return "⚠️ Foto KTP tidak terbaca jelas oleh sistem (mungkin buram atau terlalu gelap).\n\n"
                 . "Silakan unggah KTP baru yang lebih jelas di tautan berikut:\n{$linkUpload}";
        }

        // Cek input warga
        if (preg_match('/(\d{16})/', $pesanMasuk, $matches)) {
            $nikKetik = $matches[1];
            $nikOcr = $dokumenKtp->nik_terbaca;
            
            if ($nikOcr && $nikKetik === $nikOcr) {
                // COCOK! Lanjut ke VerifikasiService untuk generate OTP
                $result = $this->verifikasiService->mulai($nikKetik, $state->no_wa);
                
                if ($result['code'] === 200) {
                    $state->update([
                        'langkah' => 'menunggu_otp',
                        'sesi_id' => $result['data']['sesi_id'],
                    ]);
                    return $this->llmResponder->susunRespons('minta_otp', $pesanMasuk);
                }
                
                return "Terjadi kesalahan saat memproses permintaan OTP Anda.";
            } else {
                // TIDAK COCOK
                $formSementara = $state->form_sementara ?? [];
                $gagal = ($formSementara['percobaan_gagal_nik'] ?? 0) + 1;
                $formSementara['percobaan_gagal_nik'] = $gagal;
                
                if ($gagal >= 3) {
                    // Batas percobaan habis
                    Storage::disk('local')->delete($dokumenKtp->path_file);
                    $dokumenKtp->delete();
                    
                    $state->update([
                        'langkah' => 'awal',
                        'form_sementara' => null,
                        'dokumen_diterima' => null
                    ]);
                    
                    return "Batas percobaan habis. Silakan mulai ulang permohonan dari awal dengan foto KTP yang lebih jelas.";
                }
                
                $state->update(['form_sementara' => $formSementara]);
                $sisa = 3 - $gagal;
                
                $linkUpload = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'upload.ktp_registrasi',
                    now()->addMinutes(60),
                    ['no_wa' => $state->no_wa]
                );
                
                return "⚠️ NIK yang Anda ketik tidak cocok dengan hasil bacaan KTP.\n\n"
                     . "Jika Anda salah ketik, silakan ketik ulang NIK Anda (Tersisa {$sisa} percobaan).\n\n"
                     . "ATAU jika foto sebelumnya buram, silakan unggah KTP baru di tautan berikut:\n"
                     . "{$linkUpload}";
            }
        }
        
        return "Format NIK tidak valid. Silakan ketik 16 digit NIK Anda (tanpa spasi/tanda hubung).";
    }

    private function handleMenungguNik(PercakapanState $state, string $pesanMasuk): string
    {
        // Ekstrak 16 digit NIK menggunakan regex — BUKAN LLM
        if (preg_match('/(\d{16})/', $pesanMasuk, $matches)) {
            $nik    = $matches[1];
            $result = $this->verifikasiService->mulai($nik, $state->no_wa);

            if ($result['code'] === 200) {
                $state->update([
                    'langkah' => 'menunggu_otp',
                    'sesi_id' => $result['data']['sesi_id'],
                ]);
                return $this->llmResponder->susunRespons('minta_otp', $pesanMasuk);
            }

            if (in_array($result['status'], ['nik_tidak_ditemukan', 'no_wa_tidak_cocok'])) {
                // Pesan generik untuk mencegah kebocoran info nomor HP terdaftar
                return $this->llmResponder->susunRespons('nik_tidak_ditemukan', $pesanMasuk);
            }
        }

        return $this->llmResponder->susunRespons('minta_nik', $pesanMasuk);
    }

    private function handleMenungguOtp(PercakapanState $state, string $pesanMasuk): string
    {
        if (preg_match('/(\d{6})/', $pesanMasuk, $matches)) {
            $otp    = $matches[1];
            $result = $this->verifikasiService->cekOtp($state->sesi_id, $otp);

            if ($result['code'] === 200) {
                if ($state->jenis_surat_dipilih) {
                    $katalog       = $this->referensiService->syarat($state->jenis_surat_dipilih);
                    $pertanyaan    = $katalog['pertanyaan_form'];
                    $dokumenWajib  = $katalog['dokumen_wajib'];
                    $formSementara = $state->form_sementara ?? [];
                    $dokumenDiterima = $state->dokumen_diterima ?? [];

                    $fieldMenunggu = $this->getFieldMenunggu($pertanyaan, $formSementara);
                    
                    // 1. Jika form belum lengkap -> mengisi_form
                    if ($fieldMenunggu !== null) {
                        $state->update(['langkah' => 'mengisi_form']);
                        return "Verifikasi berhasil. Mari lanjutkan pengisian data sebelumnya.\n\nSilakan isi *{$this->labelField($fieldMenunggu)}*:";
                    }

                    // 2. Jika dokumen belum lengkap -> menunggu_dokumen
                    $dokumenBerikutnya = $this->getDokumenMenunggu($dokumenWajib, $dokumenDiterima);
                    if ($dokumenBerikutnya !== null) {
                        $state->update(['langkah' => 'menunggu_dokumen']);
                        return "Verifikasi berhasil. Mari lanjutkan pengajuan sebelumnya.\n\n"
                             . $this->handleMenungguDokumen($state, '', null);
                    }

                    // 3. Jika form & dokumen lengkap -> menunggu_konfirmasi
                    $ringkasanForm = implode("\n", array_map(
                        fn($k, $v) => "• {$this->labelField($k)}: {$v}",
                        array_keys($formSementara),
                        array_values($formSementara)
                    ));

                    $ringkasanDokumen = implode("\n", array_map(
                        fn($d) => "• {$this->labelDokumen($d)} ✓",
                        array_keys($dokumenDiterima)
                    ));

                    $state->update(['langkah' => 'menunggu_konfirmasi']);

                    return "Verifikasi berhasil. Anda memiliki draft pengajuan yang belum selesai.\n\n"
                         . "*Ringkasan Pengajuan*\n"
                         . "Jenis Surat: *{$katalog['nama']}*\n\n"
                         . "*Data Form:*\n{$ringkasanForm}\n\n"
                         . "*Dokumen:*\n{$ringkasanDokumen}\n\n"
                         . "Ketik *YA* untuk konfirmasi dan kirim permohonan, atau *BATAL* untuk membatalkan.";
                }

                $state->update(['langkah' => 'menunggu_pilihan_surat']);
                return $this->llmResponder->susunRespons('pilih_jenis_surat', $pesanMasuk);
            }

            if ($result['code'] === 401) {
                return $this->llmResponder->susunRespons('otp_salah', $pesanMasuk);
            }

            // expired (410) atau gagal permanen (429) → reset ke awal
            if (in_array($result['code'], [410, 429])) {
                $state->update(['langkah' => 'awal', 'sesi_id' => null]);
                return 'Sesi verifikasi Anda telah berakhir atau gagal. '
                     . 'Silakan mulai ulang dengan memasukkan 16 digit NIK Anda.';
            }
        }

        return 'Format OTP tidak valid. Silakan balas dengan 6 digit angka OTP yang kami kirimkan.';
    }

    private function handleMenungguPilihanSurat(PercakapanState $state, string $pesanMasuk): string
    {
        $pesanLower  = trim(strtolower($pesanMasuk));
        $jenisDipilih = null;

        $pilihanMap = [
            '1'  => 'domisili',
            '2'  => 'usaha',
            '3'  => 'sktm',
            '4'  => 'skck',
            '5'  => 'belum_menikah',
            '6'  => 'kelahiran',
            '7'  => 'kematian',
            '8'  => 'pindah',
            '9'  => 'penghasilan',
            '10' => 'tanah',
            '11' => 'ahli_waris',
            '12' => 'beda_nama',
            '13' => 'nikah',
            '14' => 'lainnya',
        ];

        if (array_key_exists($pesanLower, $pilihanMap)) {
            $jenisDipilih = $pilihanMap[$pesanLower];
        }

        if ($jenisDipilih) {
            $katalog   = $this->referensiService->syarat($jenisDipilih);
            $pertanyaan = $katalog['pertanyaan_form'];

            $state->update([
                'jenis_surat_dipilih' => $jenisDipilih,
                'langkah'             => 'mengisi_form',
                'form_sementara'      => [],   // kosong di awal
            ]);

            // Langsung tanya field pertama
            $fieldPertama = $pertanyaan[0];
            return $this->llmResponder->susunRespons('pilih_jenis_surat_konfirmasi', $pesanMasuk)
                 . "\n\nSilakan isi *{$this->labelField($fieldPertama)}*:";
        }

        return $this->llmResponder->susunRespons('pilihan_tidak_valid', $pesanMasuk);
    }

    private function handleMengisiForm(PercakapanState $state, string $pesanMasuk): string
    {
        $katalog      = $this->referensiService->syarat($state->jenis_surat_dipilih);
        $pertanyaan   = $katalog['pertanyaan_form'];
        $formSementara = $state->form_sementara ?? [];

        // Cari field pertama yang belum terisi
        $fieldMenunggu = $this->getFieldMenunggu($pertanyaan, $formSementara);

        // (d) Ada field yang sedang ditunggu → validasi dan simpan jawaban
        if ($fieldMenunggu !== null) {
            $jawaban = trim($pesanMasuk);

            if ($jawaban === '') {
                return $this->llmResponder->susunRespons('field_kosong_ulang', $pesanMasuk)
                     . " *{$this->labelField($fieldMenunggu)}*:";
            }

            // Gunakan array baru dan forceFill/save() eksplisit agar dirty checking Eloquent 
            // tidak terlewat pada nested JSON.
            $formSementara[$fieldMenunggu] = $jawaban;
            $state->forceFill(['form_sementara' => $formSementara])->save();

            // (e) Cek field berikutnya
            $fieldBerikutnya = $this->getFieldMenunggu($pertanyaan, $formSementara);

            if ($fieldBerikutnya !== null) {
                return "Baik. Selanjutnya, silakan isi *{$this->labelField($fieldBerikutnya)}*:";
            }

            // Semua field terisi → ambil syarat dokumen, pindah ke menunggu_dokumen
            $dokumenWajib = $katalog['dokumen_wajib'];
            $daftarDokumen = implode("\n", array_map(
                fn($d) => "• " . $this->labelDokumen($d),
                $dokumenWajib
            ));
            $state->update([
                'langkah'           => 'menunggu_dokumen',
            ]);

            return $this->llmResponder->susunRespons('form_lengkap_lanjut_dokumen', $pesanMasuk)
                 . "\n\nDokumen yang perlu dikirim:\n{$daftarDokumen}\n\n"
                 . "Estimasi waktu: *{$katalog['estimasi_waktu']}*.\n\n"
                 . $this->handleMenungguDokumen($state, '', null);
        }

        // Fallback (seharusnya tidak tercapai jika state konsisten)
        return $this->llmResponder->susunRespons('pilih_jenis_surat', $pesanMasuk);
    }

    private function handleMenungguDokumen(
        PercakapanState $state,
        string $pesanMasuk,
        ?UploadedFile $file
    ): string {
        $katalog       = $this->referensiService->syarat($state->jenis_surat_dipilih);
        $dokumenWajib  = $katalog['dokumen_wajib'];
        $dokumenDiterima = $state->dokumen_diterima ?? [];
        
        $dokumenBerikutnya = $this->getDokumenMenunggu($dokumenWajib, $dokumenDiterima);

        if ($dokumenBerikutnya === null) {
            // Seharusnya tidak masuk sini jika state valid, tapi jaga-jaga
            return "Semua dokumen sudah diterima sebelumnya.";
        }

        $linkUpload = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'upload.form',
            now()->addMinutes(60),
            ['no_wa' => $state->no_wa, 'sesi_id' => $state->sesi_id, 'jenis_dokumen' => $dokumenBerikutnya]
        );

        $privacyKtp = ($dokumenBerikutnya === 'fotokopi_ktp')
            ? "\n\nUntuk perlindungan data pribadi, foto KTP hanya digunakan untuk proses pembacaan data permohonan dan tidak disimpan sebagai dokumen permanen pada sistem layanan."
            : "";

        // Jika ini adalah balasan baru masuk saat di state ini, anggap mengingatkan
        if ($pesanMasuk !== '') {
             return "Kami sedang menunggu Anda mengunggah dokumen *{$this->labelDokumen($dokumenBerikutnya)}*.\n"
                  . "Mohon klik tautan berikut dan selesaikan unggahan di browser Anda:\n"
                  . "{$linkUpload}\n\n"
                  . "(Tautan ini berlaku selama 60 menit)."
                  . $privacyKtp;
        }

        // Kalau pesanMasuk kosong, berarti baru saja pindah state
        return "Silakan buka tautan berikut untuk mengunggah foto *{$this->labelDokumen($dokumenBerikutnya)}*:\n"
             . "{$linkUpload}\n\n"
             . "(Tautan ini berlaku selama 60 menit)."
             . $privacyKtp;
    }

    private function handleMenungguKonfirmasi(PercakapanState $state, string $pesanMasuk): string
    {
        $pesanLower = strtolower(trim($pesanMasuk));

        if ($pesanLower === 'batal') {
            // Update semua dokumen menjadi abandoned KECUALI fotokopi_ktp
            $dokumenDiterima = $state->dokumen_diterima ?? [];
            $dokumenIdsToAbandon = [];
            $ktpId = $dokumenDiterima['fotokopi_ktp'] ?? null;

            foreach ($dokumenDiterima as $jenis => $id) {
                if ($jenis !== 'fotokopi_ktp') {
                    $dokumenIdsToAbandon[] = $id;
                }
            }

            if (!empty($dokumenIdsToAbandon)) {
                DokumenPermohonan::whereIn('id', $dokumenIdsToAbandon)->update(['status' => 'abandoned']);
            }

            // Reset state (pertahankan sesi dan KTP, kembali pilih surat)
            $state->update([
                'langkah'             => 'menunggu_pilihan_surat',
                'jenis_surat_dipilih' => null,
                'form_sementara'      => null,
                'dokumen_diterima'    => $ktpId ? ['fotokopi_ktp' => $ktpId] : null,
            ]);

            return $this->llmResponder->susunRespons('permohonan_batal', $pesanMasuk);
        }

        if ($pesanLower === 'ya') {
            // Panggil PermohonanService::ajukan
            $result = $this->permohonanService->ajukan(
                $state->sesi_id,
                $state->jenis_surat_dipilih,
                $state->form_sementara ?? []
            );

            // Jika sesi tidak valid / expired
            if ($result['code'] !== 201) {
                // Reset ke menunggu_nik, tapi pertahankan draft (jenis_surat, form, dokumen)
                $state->update([
                    'langkah' => 'menunggu_nik',
                    'sesi_id' => null, // reset sesi, harus verif ulang
                ]);

                return $this->llmResponder->susunRespons('sesi_expired_simpan_draft', $pesanMasuk);
            }

            // Jika sukses
            $idPermohonan = $result['data']['id_permohonan'];

            // Update dokumen dengan id_permohonan
            $dokumenIds = array_values($state->dokumen_diterima ?? []);
            if (!empty($dokumenIds)) {
                DokumenPermohonan::whereIn('id', $dokumenIds)->update([
                    'id_permohonan' => $idPermohonan
                ]);
            }

            // Update state selesai
            $state->update([
                'langkah' => 'selesai_mengajukan',
            ]);

            $responsRaw = $this->llmResponder->susunRespons('permohonan_sukses', $pesanMasuk);
            return str_replace('{id_permohonan}', $idPermohonan, $responsRaw);
        }

        return $this->llmResponder->susunRespons('konfirmasi_tidak_valid', $pesanMasuk);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Kembalikan field pertama dari $pertanyaan yang belum ada di $form.
     * Return null jika semua sudah terisi.
     */
    private function getFieldMenunggu(array $pertanyaan, array $form): ?string
    {
        foreach ($pertanyaan as $field) {
            if (!array_key_exists($field, $form)) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Kembalikan slot dokumen pertama yang belum ada di $diterima.
     * Return null jika semua sudah diterima.
     */
    private function getDokumenMenunggu(array $dokumenWajib, array $diterima): ?string
    {
        foreach ($dokumenWajib as $dok) {
            if (!array_key_exists($dok, $diterima)) {
                return $dok;
            }
        }
        return null;
    }

    /**
     * Terjemahkan snake_case field ke label manusiawi untuk ditampilkan ke warga.
     */
    private function labelField(string $field): string
    {
        return ReferensiService::LABEL_FIELD[$field]
            ?? ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Terjemahkan kode jenis dokumen ke label manusiawi.
     */
    private function labelDokumen(string $jenisDokumen): string
    {
        return match ($jenisDokumen) {
            'fotokopi_ktp'      => 'fotokopi KTP',
            'fotokopi_kk'       => 'fotokopi Kartu Keluarga (KK)',
            'dokumen_pendukung' => 'dokumen pendukung',
            default             => ucwords(str_replace('_', ' ', $jenisDokumen)),
        };
    }
}
