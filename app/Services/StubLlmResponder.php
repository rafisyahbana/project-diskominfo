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
            'menu_awal'
                => "Selamat datang di layanan administrasi surat.\n\nSilakan pilih layanan:\n1. Layanan surat\n2. Cek status permohonan\n3. Bantuan\n\nBalas dengan angka pilihan.",
            'menu_cek_status'
                => 'Fitur cek status permohonan belum tersedia saat ini.',
            'menu_bantuan'
                => 'Layanan ini memungkinkan Anda untuk mengajukan surat pengantar/keterangan secara online. Ketik angka 1 untuk memulai pengajuan, lengkapi form, lalu unggah dokumen persyaratannya melalui tautan yang diberikan.',
            'minta_nik'
                => 'Halo! Untuk memulai layanan surat, silakan balas pesan ini dengan 16 digit NIK Anda.',
            'minta_otp'
                => 'NIK ditemukan. Kami telah mengirimkan OTP ke nomor ini. Silakan balas dengan 6 digit OTP.',
            'nik_tidak_ditemukan'
                => 'Maaf, NIK tersebut tidak ditemukan di sistem kami. Pastikan Anda memasukkan 16 digit NIK yang benar.',
            'otp_salah'
                => 'OTP yang Anda masukkan salah. Silakan coba lagi.',
            'pilih_jenis_surat'
                => "Baik, Anda memilih layanan surat.\nSilakan pilih jenis surat yang ingin diajukan:\n1. Surat Keterangan Domisili\n2. Surat Keterangan Usaha\n3. Surat Keterangan Tidak Mampu\n4. Surat Pengantar SKCK\n5. Surat Keterangan Belum Pernah Menikah\n6. Surat Keterangan Kelahiran\n7. Surat Keterangan Kematian\n8. Surat Pengantar Pindah\n9. Surat Keterangan Penghasilan\n10. Surat Keterangan Tanah\n11. Surat Keterangan Ahli Waris\n12. Surat Keterangan Beda Nama\n13. Surat Pengantar Nikah Model N1-N4\n14. Lainnya\n\nBalas dengan angka pilihan.",
            'pilih_jenis_surat_konfirmasi'
                => 'Baik! Anda memilih jenis surat tersebut. Mari isi data yang dibutuhkan satu per satu.',
            'pilihan_tidak_valid'
                => 'Pilihan tidak dikenali. Silakan balas dengan angka 1 hingga 14 sesuai pilihan jenis surat.',
            'field_kosong_ulang'
                => 'Jawaban tidak boleh kosong. Silakan isi',
            'form_lengkap_lanjut_dokumen'
                => 'Semua data sudah terisi. Langkah berikutnya adalah mengirimkan dokumen pendukung.',
            'permohonan_sukses'
                => 'Permohonan Anda berhasil diajukan dengan ID: {id_permohonan}.',
            'permohonan_batal'
                => 'Pengajuan dibatalkan. Silakan balas dengan angka 1 hingga 14 untuk memilih kembali jenis surat yang ingin diajukan.',
            'konfirmasi_tidak_valid'
                => 'Silakan balas dengan YA untuk mengonfirmasi pengajuan, atau BATAL untuk membatalkan.',
            'sesi_expired_simpan_draft'
                => 'Maaf, sesi verifikasi Anda sudah kedaluwarsa. Data Anda telah kami simpan sementara. Silakan verifikasi ulang dengan membalas 16 digit NIK Anda.',
            default
                => "Sistem menerima pesan Anda. [konteks: $konteks]",
        };
    }
}
