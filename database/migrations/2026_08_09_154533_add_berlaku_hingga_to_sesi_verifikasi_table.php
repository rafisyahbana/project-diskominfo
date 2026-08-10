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
        Schema::table('sesi_verifikasi', function (Blueprint $table) {
            $table->timestamp('berlaku_hingga')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sesi_verifikasi', function (Blueprint $table) {
            $table->dropColumn('berlaku_hingga');
        });
    }
};
