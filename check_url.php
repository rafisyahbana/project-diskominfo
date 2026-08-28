<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$notif = \App\Models\NotifikasiTerkirim::where('no_wa', '6282232914178')
            ->orderBy('created_at', 'desc')
            ->first();

if ($notif && preg_match('/(https?:\/\/[^\s]+)/', $notif->pesan, $m)) {
    echo "Generated URL: " . $m[1] . "\n";
} else {
    echo "URL tidak ditemukan di notifikasi terakhir.\n";
}
