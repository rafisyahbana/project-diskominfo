<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$noWa = '6282232914178';
$urlSesiId = '01a03c83-7c62-709b-92a5-19300cbc2a22';

echo "=== HASIL DEBUG ===\n\n";

// 2. Cek PercakapanState
$state = \App\Models\PercakapanState::where('no_wa', $noWa)->first();
if ($state) {
    echo "2. PercakapanState sesi_id: " . $state->sesi_id . "\n";
} else {
    echo "2. PercakapanState TIDAK DITEMUKAN untuk $noWa\n";
}

// 3. Bandingkan dengan sesi_id di URL
echo "\n3. Perbandingan Sesi ID:\n";
echo "ID dari URL : " . $urlSesiId . "\n";
echo "ID di State : " . ($state ? $state->sesi_id : 'null') . "\n";
if ($state && $state->sesi_id === $urlSesiId) {
    echo "--> SAMA PERSIS\n";
} else {
    echo "--> BERBEDA!\n";
}

// 4. Cek SesiVerifikasi dari URL
echo "\n4. Data SesiVerifikasi (ID dari URL):\n";
$sesi = \App\Models\SesiVerifikasi::find($urlSesiId);
if ($sesi) {
    echo "Status        : " . $sesi->status . "\n";
    echo "Berlaku Hingga: " . ($sesi->berlaku_hingga ?? 'NULL') . "\n";
    echo "Expired At    : " . $sesi->expired_at . "\n";
    echo "isVerifiedAndActive() : " . ($sesi->isVerifiedAndActive() ? 'TRUE' : 'FALSE') . "\n";
} else {
    echo "SesiVerifikasi TIDAK DITEMUKAN untuk ID $urlSesiId\n";
}

// 5. Cek SesiVerifikasi dari State (kalau beda)
if ($state && $state->sesi_id !== $urlSesiId) {
    echo "\n5. Data SesiVerifikasi (ID dari State):\n";
    $sesiState = \App\Models\SesiVerifikasi::find($state->sesi_id);
    if ($sesiState) {
        echo "Status        : " . $sesiState->status . "\n";
        echo "Berlaku Hingga: " . ($sesiState->berlaku_hingga ?? 'NULL') . "\n";
        echo "Expired At    : " . $sesiState->expired_at . "\n";
        echo "isVerifiedAndActive() : " . ($sesiState->isVerifiedAndActive() ? 'TRUE' : 'FALSE') . "\n";
    } else {
        echo "SesiVerifikasi TIDAK DITEMUKAN untuk ID {$state->sesi_id}\n";
    }
}
