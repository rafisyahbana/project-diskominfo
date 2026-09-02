<?php

/**
 * ============================================================================
 * TEST SCRIPT — Tesseract OCR untuk Project Laravel Diskominfo
 * ============================================================================
 *
 * Script ini menguji apakah Tesseract OCR sudah terinstal dengan benar
 * dan dapat dipanggil dari PHP sebelum diintegrasikan ke Laravel.
 *
 * Cara menjalankan:
 *   php test_ocr_local.php
 *
 * Prasyarat:
 *   - Tesseract OCR sudah terinstal (ikuti panduan_tesseract_ocr.md)
 *   - PHP sudah bisa menjalankan exec() / shell_exec()
 *   - Extension GD aktif di php.ini (untuk generate gambar test)
 * ============================================================================
 */

// ─────────────────────────────────────────────────────────────────────────────
// KONFIGURASI
// ─────────────────────────────────────────────────────────────────────────────

define('TESSERACT_EXE', 'C:/Program Files/Tesseract-OCR/tesseract.exe');
define('TESSERACT_LANG', 'ind+eng');
define('TEST_NIK', '3517231234560001');

// ─────────────────────────────────────────────────────────────────────────────
// HELPER FUNCTIONS
// ─────────────────────────────────────────────────────────────────────────────

function printHeader(string $title): void
{
    $line = str_repeat('─', 60);
    echo PHP_EOL . "┌{$line}┐" . PHP_EOL;
    echo '│ ' . str_pad("🔍 {$title}", 61) . '│' . PHP_EOL;
    echo "└{$line}┘" . PHP_EOL;
}

function printResult(bool $success, string $message): void
{
    $icon = $success ? '✅' : '❌';
    echo "{$icon} {$message}" . PHP_EOL;
}

// ─────────────────────────────────────────────────────────────────────────────
// TEST 1: Cek apakah Tesseract Executable ada
// ─────────────────────────────────────────────────────────────────────────────

printHeader('TEST 1: Cek Keberadaan Tesseract Executable');

$tesseractExists = file_exists(TESSERACT_EXE);
printResult($tesseractExists, $tesseractExists
    ? 'File tesseract.exe ditemukan di: ' . TESSERACT_EXE
    : 'tesseract.exe TIDAK ditemukan di: ' . TESSERACT_EXE
);

if (! $tesseractExists) {
    echo PHP_EOL . '⚠️  Tesseract belum terinstal. Ikuti panduan_tesseract_ocr.md' . PHP_EOL;
    echo '    Download: https://github.com/UB-Mannheim/tesseract/releases' . PHP_EOL;
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// TEST 2: Cek Versi Tesseract via exec()
// ─────────────────────────────────────────────────────────────────────────────

printHeader('TEST 2: Versi Tesseract & Kemampuan exec()');

$versionCmd = '"' . TESSERACT_EXE . '" --version 2>&1';
exec($versionCmd, $versionOutput, $versionExit);

if ($versionExit === 0) {
    printResult(true, 'PHP dapat menjalankan exec() dengan sukses');
    echo '    Versi: ' . ($versionOutput[0] ?? 'unknown') . PHP_EOL;
} else {
    printResult(false, 'PHP GAGAL menjalankan exec(). Cek php.ini: disable_functions');
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// TEST 3: Cek Bahasa yang Tersedia
// ─────────────────────────────────────────────────────────────────────────────

printHeader('TEST 3: Cek Bahasa Tesseract yang Tersedia');

$langCmd = '"' . TESSERACT_EXE . '" --list-langs 2>&1';
exec($langCmd, $langOutput, $langExit);

$availableLangs = array_slice($langOutput, 1); // Skip baris header
$hasInd = in_array('ind', $availableLangs);
$hasEng = in_array('eng', $availableLangs);

printResult($hasEng, $hasEng ? 'Bahasa English (eng) tersedia' : 'Bahasa English (eng) TIDAK tersedia');
printResult($hasInd, $hasInd
    ? 'Bahasa Indonesia (ind) tersedia'
    : 'Bahasa Indonesia (ind) TIDAK tersedia — download: https://github.com/tesseract-ocr/tessdata/raw/main/ind.traineddata'
);

echo '    Semua bahasa: ' . implode(', ', $availableLangs) . PHP_EOL;

// ─────────────────────────────────────────────────────────────────────────────
// TEST 4: OCR pada Gambar Sintetis (NIK)
// ─────────────────────────────────────────────────────────────────────────────

printHeader('TEST 4: OCR Baca NIK dari Gambar Sintetis');

if (! function_exists('imagecreatetruecolor')) {
    printResult(false, 'Extension GD tidak aktif. Aktifkan di php.ini: extension=gd');
    exit(1);
}

// Generate gambar test berisi NIK dengan resolusi tinggi
$scale = 4;
$nikText = TEST_NIK;

$img   = imagecreatetruecolor(800 * $scale, 150 * $scale);
$putih = imagecolorallocate($img, 255, 255, 255);
$hitam = imagecolorallocate($img, 0, 0, 0);

imagefill($img, 0, 0, $putih);

// Tulis NIK di gambar dengan font besar
imagestring($img, 5, 20 * $scale, 50 * $scale, $nikText, $hitam);

$tmpImagePath = sys_get_temp_dir() . '/ocr_test_nik.png';
imagepng($img, $tmpImagePath);
imagedestroy($img);

echo "    Gambar test dibuat: {$tmpImagePath}" . PHP_EOL;
echo "    NIK yang ditulis : {$nikText}" . PHP_EOL;

// Jalankan OCR
$ocrCmd = '"' . TESSERACT_EXE . '" ' . escapeshellarg($tmpImagePath)
    . ' stdout -l ' . TESSERACT_LANG . ' --oem 1 --psm 6 2>&1';

exec($ocrCmd, $ocrOutput, $ocrExit);

$rawText = trim(implode(PHP_EOL, $ocrOutput));
echo '    Raw OCR output   : ' . json_encode($rawText) . PHP_EOL;

// Ekstrak NIK dari output OCR
$detectedNik = null;
if (preg_match('/\b(\d{16})\b/', $rawText, $matches)) {
    $detectedNik = $matches[1];
} else {
    $cleaned = preg_replace('/[\s.\-]/', '', $rawText);
    if (preg_match('/(\d{16})/', $cleaned, $matches)) {
        $detectedNik = $matches[1];
    }
}

$nikMatch = $detectedNik === $nikText;
printResult($nikMatch, $nikMatch
    ? "NIK berhasil dibaca: {$detectedNik} (EXACT MATCH ✓)"
    : "NIK tidak cocok. Dideteksi: '{$detectedNik}', Diharapkan: '{$nikText}'"
);

// Bersihkan file temp
@unlink($tmpImagePath);

// ─────────────────────────────────────────────────────────────────────────────
// TEST 5: Contoh Baca dari File Gambar Eksternal
// ─────────────────────────────────────────────────────────────────────────────

printHeader('TEST 5: Cara Baca Teks dari File Gambar Nyata');

echo PHP_EOL;
echo '    Untuk membaca file gambar Anda sendiri, gunakan kode berikut:' . PHP_EOL;
echo PHP_EOL;
echo '    ┌─────────────────────────────────────────────────────────┐' . PHP_EOL;
echo "    │ \$imagePath = '/path/to/ktp.jpg';                        │" . PHP_EOL;
echo "    │ \$cmd = '\"C:/Program Files/Tesseract-OCR/tesseract.exe\"';│" . PHP_EOL;
echo "    │ \$cmd .= ' ' . escapeshellarg(\$imagePath);               │" . PHP_EOL;
echo "    │ \$cmd .= ' stdout -l ind+eng --oem 1 --psm 6 2>&1';      │" . PHP_EOL;
echo "    │ exec(\$cmd, \$output, \$exitCode);                          │" . PHP_EOL;
echo "    │ \$text = trim(implode(PHP_EOL, \$output));                 │" . PHP_EOL;
echo '    └─────────────────────────────────────────────────────────┘' . PHP_EOL;
echo PHP_EOL;

// Atau gunakan Service Class Laravel
echo '    Atau gunakan TesseractOcrService di Laravel:' . PHP_EOL;
echo PHP_EOL;
echo '    ┌─────────────────────────────────────────────────────────┐' . PHP_EOL;
echo '    │ use App\Services\TesseractOcrService;                   │' . PHP_EOL;
echo '    │                                                          │' . PHP_EOL;
echo '    │ $ocr  = new TesseractOcrService();                      │' . PHP_EOL;
echo "    │ \$text = \$ocr->readText('/path/to/ktp.jpg');             │" . PHP_EOL;
echo "    │ \$nik  = \$ocr->extractNik('/path/to/ktp.jpg');           │" . PHP_EOL;
echo '    └─────────────────────────────────────────────────────────┘' . PHP_EOL;

// ─────────────────────────────────────────────────────────────────────────────
// RINGKASAN
// ─────────────────────────────────────────────────────────────────────────────

printHeader('📊 RINGKASAN HASIL TEST');

$allPassed = $tesseractExists && ($versionExit === 0) && $hasEng && ($ocrExit === 0);

if ($allPassed) {
    echo PHP_EOL . '🎉 SEMUA TEST BERHASIL! Tesseract OCR siap digunakan di project Laravel.' . PHP_EOL;
    echo '   Selanjutnya, gunakan App\\Services\\TesseractOcrService di controller Anda.' . PHP_EOL;
} else {
    echo PHP_EOL . '⚠️  Ada beberapa test yang gagal. Ikuti panduan di atas untuk memperbaiki.' . PHP_EOL;
    echo '   Referensi: panduan_tesseract_ocr.md di artifact Antigravity' . PHP_EOL;
}

echo PHP_EOL;
