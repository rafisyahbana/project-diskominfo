<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_permohonans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Siapa yang upload — bisa diaudit bahkan jika permohonan dibatalkan
            $table->string('no_wa')->index();

            // Jenis slot dokumen yang diisi (fotokopi_ktp, fotokopi_kk, dst)
            $table->string('jenis_dokumen');

            // Lokasi file di Storage::disk('local') — simpan path relatif, bukan URL
            $table->string('path_file');

            // Diisi setelah ajukan_surat berhasil dipanggil
            $table->uuid('id_permohonan')->nullable()->index();

            // aktif = dokumen valid dalam alur; abandoned = warga batal sebelum konfirmasi
            $table->enum('status', ['aktif', 'abandoned'])->default('aktif');

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_permohonans');
    }
};
