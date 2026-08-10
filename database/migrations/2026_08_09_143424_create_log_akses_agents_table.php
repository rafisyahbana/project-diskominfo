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
        Schema::create('log_akses_agent', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_wa')->index();
            $table->string('nik', 16)->nullable();
            $table->string('tool_dipanggil');
            $table->json('payload')->nullable();
            $table->string('hasil');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_akses_agent');
    }
};
