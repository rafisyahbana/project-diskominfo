<?php

namespace App\Contracts;

interface WhatsAppNotifier
{
    /**
     * Kirim pesan WhatsApp ke nomor tujuan.
     *
     * @param  string $noWa   Nomor tujuan (format lokal, contoh: 08111222333)
     * @param  string $pesan  Isi pesan teks
     * @return bool           true jika berhasil terkirim / terdaftar, false jika gagal
     */
    public function kirim(string $noWa, string $pesan): bool;
}
