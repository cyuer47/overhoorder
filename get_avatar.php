<?php
session_start();
require_once __DIR__ . '/php/db.php';

if (empty($_SESSION['docent_id'])) {
    http_response_code(403);
    exit;
}

$docent_id = $_SESSION['docent_id'];
$docent_naam = $_SESSION['docent_naam'] ?? 'Docent';

$file = $_GET['file'] ?? '';
$file = basename($file); // voorkom path traversal
$size = (int)($_GET['size'] ?? 128);

$path = __DIR__ . '/uploads/avatars/' . $file;

if ($file && file_exists($path)) {
    // Upload foto beschikbaar
    $mime = mime_content_type($path);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
} else {
    // Geen bestand: genereer Identicon
    $seed = $_GET['seed'] ?? $docent_naam;
    $size = max(32, min($size, 512));

    // Simpele Identicon generator (vierkantjes patroon)
    $img = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($img, 240, 240, 240); // lichte achtergrond
    imagefill($img, 0, 0, $bg);

    // hash van seed
    $hash = md5($seed);
    $color = imagecolorallocate($img, hexdec(substr($hash, 0, 2)), hexdec(substr($hash, 2, 2)), hexdec(substr($hash, 4, 2)));

    $block = $size / 5;
    for ($x = 0; $x < 3; $x++) {
        for ($y = 0; $y < 5; $y++) {
            if (hexdec($hash[$x*5 + $y]) % 2 == 0) {
                imagefilledrectangle($img, $x*$block, $y*$block, ($x+1)*$block, ($y+1)*$block, $color);
                // spiegel horizontaal
                if ($x < 2) {
                    imagefilledrectangle($img, (4-$x)*$block, $y*$block, (5-$x)*$block, ($y+1)*$block, $color);
                }
            }
        }
    }

    header('Content-Type: image/png');
    imagepng($img);
    imagedestroy($img);
    exit;
}
