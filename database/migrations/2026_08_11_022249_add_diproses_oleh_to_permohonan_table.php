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
        Schema::table('permohonan', function (Blueprint $table) {
            // FK ke id petugas yang memproses, nullable (belum diproses = null)
            $table->unsignedBigInteger('diproses_oleh')->nullable()->after('file_surat_url');
            $table->foreign('diproses_oleh')->references('id')->on('petugas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permohonan', function (Blueprint $table) {
            $table->dropForeign(['diproses_oleh']);
            $table->dropColumn('diproses_oleh');
        });
    }
};
