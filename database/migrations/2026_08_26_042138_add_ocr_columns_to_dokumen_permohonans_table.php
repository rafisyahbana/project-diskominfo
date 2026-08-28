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
        Schema::table('dokumen_permohonans', function (Blueprint $table) {
            // Status hasil OCR: pending=belum diproses, ok=NIK cocok, gagal=NIK beda, tidak_terbaca=OCR error
            $table->enum('status_ocr', ['pending', 'ok', 'gagal', 'tidak_terbaca'])
                  ->default('pending')
                  ->after('status');

            // NIK mentah yang terbaca OCR — untuk audit petugas, null jika OCR tidak berhasil
            $table->string('nik_terbaca', 20)->nullable()->after('status_ocr');

            // Kapan OCR diproses — null berarti job belum berjalan
            $table->timestamp('ocr_diproses_at')->nullable()->after('nik_terbaca');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_permohonans', function (Blueprint $table) {
            $table->dropColumn(['status_ocr', 'nik_terbaca', 'ocr_diproses_at']);
        });
    }
};
