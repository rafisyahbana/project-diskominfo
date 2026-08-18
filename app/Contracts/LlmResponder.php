<?php

namespace App\Contracts;

interface LlmResponder
{
    /**
     * Menyusun respons akhir yang natural untuk pengguna berdasarkan konteks.
     * 
     * @param string $konteks Konteks internal sistem (misal: "minta NIK", "beritahu OTP salah").
     * @param string|null $pesanAsli Pesan asli dari warga (opsional, untuk konteks).
     * @return string Pesan balasan ke pengguna.
     */
    public function susunRespons(string $konteks, ?string $pesanAsli = null): string;
}
