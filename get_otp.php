<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$notifs = \App\Models\NotifikasiTerkirim::latest()->take(5)->get();
foreach ($notifs as $n) {
    echo "To: {$n->no_wa} | Msg: " . substr(str_replace(PHP_EOL, ' ', $n->pesan), 0, 100) . "\n";
}
