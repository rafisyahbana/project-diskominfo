<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi_terkirim', function (Blueprint $table) {
            $table->boolean('status_terkirim')->default(false)->after('pesan');
            $table->string('error_pesan')->nullable()->after('status_terkirim');
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi_terkirim', function (Blueprint $table) {
            $table->dropColumn(['status_terkirim', 'error_pesan']);
        });
    }
};
