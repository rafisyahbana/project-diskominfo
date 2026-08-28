<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sesi = \App\Models\SesiVerifikasi::where('no_wa', '6282232914178')->latest()->first();
if ($sesi) {
    echo 'Status: ' . $sesi->status . PHP_EOL;
    echo 'Expired at: ' . $sesi->expired_at . PHP_EOL;
} else {
    echo 'Tidak ada sesi untuk nomor ini.' . PHP_EOL;
}
