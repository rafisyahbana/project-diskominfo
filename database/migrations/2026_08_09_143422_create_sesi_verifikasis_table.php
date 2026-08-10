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
        Schema::create('sesi_verifikasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_wa')->index();
            $table->string('nik', 16)->nullable()->index();
            $table->string('otp_hash')->nullable();
            $table->enum('status', ['pending', 'verified', 'expired', 'failed'])->default('pending');
            $table->integer('percobaan_gagal')->default(0);
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesi_verifikasi');
    }
};
