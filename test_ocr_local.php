<?php
$scale = 4;
$nikAsli = '3517231234560001';
$img = imagecreatetruecolor(800 * $scale, 150 * $scale);
$putih = imagecolorallocate($img, 255, 255, 255);
$hitam = imagecolorallocate($img, 0, 0, 0);
imagefill($img, 0, 0, $putih);
imagestring($img, 5, 20 * $scale, 50 * $scale, $nikAsli, $hitam);
$path = sys_get_temp_dir() . '/ocr_test_scale4.png';
imagepng($img, $path);
imagedestroy($img);
$cmd = '"C:/Program Files/Tesseract-OCR/tesseract.exe" ' . escapeshellarg($path) . ' stdout -l ind+eng --oem 1 --psm 6 2>&1';
exec($cmd, $output, $exitCode);
echo 'OCR Output: ' . implode(PHP_EOL, $output) . PHP_EOL;
unlink($path);
