<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('percakapan_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_wa')->unique();
            $table->uuid('sesi_id')->nullable();
            $table->enum('langkah', [
                'awal', 
                'menunggu_upload_ktp_registrasi', // [BARU] Menunggu upload KTP untuk registrasi awal
                'menunggu_nik_registrasi',        // [BARU] Menunggu user ketik NIK untuk komparasi dengan OCR KTP
                'menunggu_nik',                   // [LAMA] Masih dipertahankan untuk kompatibilitas jika diperlukan
                'menunggu_otp', 
                'menunggu_pilihan_surat', 
                'mengisi_form', 
                'menunggu_dokumen', 
                'menunggu_konfirmasi', 
                'selesai_mengajukan', 
                'idle'
            ])->default('awal');
            $table->string('jenis_surat_dipilih')->nullable();
            $table->json('form_sementara')->nullable();
            $table->json('dokumen_diterima')->nullable();
            $table->json('riwayat_pesan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('percakapan_states');
    }
};
