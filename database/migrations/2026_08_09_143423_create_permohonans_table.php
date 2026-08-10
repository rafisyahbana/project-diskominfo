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
        Schema::create('permohonan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nik', 16)->index();
            $table->string('no_wa')->index();
            $table->string('jenis_surat');
            $table->json('data_form');
            $table->enum('status', ['draft', 'menunggu_verifikasi', 'diproses', 'ditolak', 'selesai'])->default('draft');
            $table->text('catatan_petugas')->nullable();
            $table->string('file_surat_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permohonan');
    }
};
