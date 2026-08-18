<?php

namespace App\Services;

use App\Contracts\LlmResponder;

class StubLlmResponder implements LlmResponder
{
    public function susunRespons(string $konteks, ?string $pesanAsli = null): string
    {
        // Stub — template statis tanpa memanggil LLM sungguhan.
        // Ganti implementasi ini dengan pemanggilan API LLM (OpenAI, Gemini, dst)
        // saat siap ke production. Seluruh orchestrator tidak perlu diubah.
        return match ($konteks) {
            'minta_nik'
                => 'Halo! Untuk memulai layanan surat, silakan balas pesan ini dengan 16 digit NIK Anda.',
            'minta_otp'
                => 'NIK ditemukan. Kami telah mengirimkan OTP ke nomor ini. Silakan balas dengan 6 digit OTP.',
            'nik_tidak_ditemukan'
                => 'Maaf, NIK tersebut tidak ditemukan di sistem kami. Pastikan Anda memasukkan 16 digit NIK yang benar.',
            'otp_salah'
                => 'OTP yang Anda masukkan salah. Silakan coba lagi.',
            'pilih_jenis_surat'
                => 'Verifikasi berhasil! Silakan pilih jenis surat yang ingin diajukan: domisili, sktm, pengantar, atau lainnya.',
            'pilih_jenis_surat_konfirmasi'
                => 'Baik! Anda memilih jenis surat tersebut. Mari isi data yang dibutuhkan satu per satu.',
            'pilihan_tidak_valid'
                => 'Pilihan tidak dikenali. Silakan pilih dari: domisili, sktm, pengantar, atau lainnya.',
            'field_kosong_ulang'
                => 'Jawaban tidak boleh kosong. Silakan isi',
            'form_lengkap_lanjut_dokumen'
                => 'Semua data sudah terisi. Langkah berikutnya adalah mengirimkan dokumen pendukung.',
            'permohonan_sukses'
                => 'Permohonan Anda berhasil diajukan dengan ID: {id_permohonan}.',
            'permohonan_batal'
                => 'Pengajuan dibatalkan. Silakan pilih kembali jenis surat yang ingin diajukan: domisili, sktm, pengantar, atau lainnya.',
            'konfirmasi_tidak_valid'
                => 'Silakan balas dengan YA untuk mengonfirmasi pengajuan, atau BATAL untuk membatalkan.',
            'sesi_expired_simpan_draft'
                => 'Maaf, sesi verifikasi Anda sudah kedaluwarsa. Data Anda telah kami simpan sementara. Silakan verifikasi ulang dengan membalas 16 digit NIK Anda.',
            default
                => "Sistem menerima pesan Anda. [konteks: $konteks]",
        };
    }
}
