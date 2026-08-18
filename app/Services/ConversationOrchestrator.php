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
            'awal'                   => $this->handleAwal($state, $pesanMasuk),
            'menunggu_nik'           => $this->handleMenungguNik($state, $pesanMasuk),
            'menunggu_otp'           => $this->handleMenungguOtp($state, $pesanMasuk),
            'menunggu_pilihan_surat' => $this->handleMenungguPilihanSurat($state, $pesanMasuk),
            'mengisi_form'           => $this->handleMengisiForm($state, $pesanMasuk),
            'menunggu_dokumen'       => $this->handleMenungguDokumen($state, $pesanMasuk, $file),
            'menunggu_konfirmasi'    => $this->handleMenungguKonfirmasi($state, $pesanMasuk),
            default                  => "Fitur untuk langkah '{$state->langkah}' belum tersedia.",
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
        $state->update(['langkah' => 'menunggu_nik']);
        return $this->llmResponder->susunRespons('minta_nik', $pesanMasuk);
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
                        return "Verifikasi berhasil. Mari lanjutkan pengajuan sebelumnya.\n\nSilakan kirimkan foto *{$this->labelDokumen($dokumenBerikutnya)}*:";
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
        $pesanLower  = strtolower($pesanMasuk);
        $jenisDipilih = null;

        // Pencocokan deterministik — str_contains, BUKAN LLM
        if (str_contains($pesanLower, 'domisili')) {
            $jenisDipilih = 'domisili';
        } elseif (str_contains($pesanLower, 'sktm') || str_contains($pesanLower, 'tidak mampu')) {
            $jenisDipilih = 'sktm';
        } elseif (str_contains($pesanLower, 'pengantar')) {
            $jenisDipilih = 'pengantar';
        } elseif (str_contains($pesanLower, 'lainnya') || str_contains($pesanLower, 'lain')) {
            $jenisDipilih = 'lainnya';
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
                'dokumen_diterima'  => [],
            ]);

            return $this->llmResponder->susunRespons('form_lengkap_lanjut_dokumen', $pesanMasuk)
                 . "\n\nDokumen yang perlu dikirim:\n{$daftarDokumen}\n\n"
                 . "Estimasi waktu: *{$katalog['estimasi_waktu']}*.\n"
                 . "Silakan kirimkan foto/scan dokumen pertama (*{$this->labelDokumen($dokumenWajib[0])}*):";
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

        // (a) Tidak ada file → minta kirim foto
        if ($file === null) {
            $dokumenBerikutnya = $this->getDokumenMenunggu($dokumenWajib, $dokumenDiterima);
            if ($dokumenBerikutnya) {
                return "Tolong kirimkan foto *{$this->labelDokumen($dokumenBerikutnya)}* "
                     . "(bukan pesan teks).";
            }
        }

        // (b) File ada → simpan ke storage dan catat ke DB
        if ($file !== null) {
            // Simpan ke storage dengan path terstruktur: dokumen/{no_wa}/{timestamp}_{originalname}
            $folder   = 'dokumen/' . $state->no_wa;
            $pathFile = Storage::disk('local')->putFile($folder, $file);

            // Tentukan slot dokumen berikutnya secara deterministik
            $jenisDokumen = $this->getDokumenMenunggu($dokumenWajib, $dokumenDiterima);

            if ($jenisDokumen === null) {
                // Semua dokumen sudah diterima (state tidak konsisten) — abaikan upload ini
                return "Semua dokumen sudah diterima sebelumnya.";
            }

            // Simpan record ke tabel dokumen_permohonans (id_permohonan null dulu)
            $dokumenRecord = DokumenPermohonan::create([
                'no_wa'          => $state->no_wa,
                'jenis_dokumen'  => $jenisDokumen,
                'path_file'      => $pathFile,
                'id_permohonan'  => null,
                'status'         => 'aktif',
            ]);

            // Update dokumen_diterima di state: key = jenis_dokumen, value = id record
            $dokumenDiterima[$jenisDokumen] = $dokumenRecord->id;
            $state->update(['dokumen_diterima' => $dokumenDiterima]);

            // (c) Cek apakah masih ada dokumen yang kurang
            $dokumenBerikutnya = $this->getDokumenMenunggu($dokumenWajib, $dokumenDiterima);

            if ($dokumenBerikutnya !== null) {
                return "✓ *{$this->labelDokumen($jenisDokumen)}* diterima.\n\n"
                     . "Selanjutnya, kirimkan foto *{$this->labelDokumen($dokumenBerikutnya)}*:";
            }

            // Semua dokumen lengkap → buat ringkasan dan pindah ke menunggu_konfirmasi
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

            return "✓ *{$this->labelDokumen($jenisDokumen)}* diterima. Semua dokumen lengkap!\n\n"
                 . "*Ringkasan Pengajuan*\n"
                 . "Jenis Surat: *{$katalog['nama']}*\n\n"
                 . "*Data Form:*\n{$ringkasanForm}\n\n"
                 . "*Dokumen:*\n{$ringkasanDokumen}\n\n"
                 . "Ketik *YA* untuk konfirmasi dan kirim permohonan, atau *BATAL* untuk membatalkan.";
        }

        return "Silakan kirimkan foto dokumen yang diperlukan.";
    }

    private function handleMenungguKonfirmasi(PercakapanState $state, string $pesanMasuk): string
    {
        $pesanLower = strtolower(trim($pesanMasuk));

        if ($pesanLower === 'batal') {
            // Update semua dokumen menjadi abandoned
            $dokumenIds = array_values($state->dokumen_diterima ?? []);
            if (!empty($dokumenIds)) {
                DokumenPermohonan::whereIn('id', $dokumenIds)->update(['status' => 'abandoned']);
            }

            // Reset state (pertahankan sesi, kembali pilih surat)
            $state->update([
                'langkah'             => 'menunggu_pilihan_surat',
                'jenis_surat_dipilih' => null,
                'form_sementara'      => null,
                'dokumen_diterima'    => null,
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
