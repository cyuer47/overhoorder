<?php
// identicon.php - eenvoudige, betrouwbare identicon generator (PHP + GD)
// Usage: identicon.php?seed=user_123&size=160
// Zorg dat de PHP GD-extensie aanstaat (beeldcreatie).

// --- Controleer GD
if (!extension_loaded('gd')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "GD extension niet gevonden. Activeer de PHP GD-extensie op je server.";
    exit;
}

// --- Helper: HSL -> RGB (h:0-360, s:0-100, l:0-100) returned 0..255 ints
function hsl_to_rgb($h, $s, $l) {
    $s /= 100; $l /= 100;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $hp = $h / 60;
    $x = $c * (1 - abs(fmod($hp, 2) - 1));
    $r = $g = $b = 0;
    if ($hp >= 0 && $hp < 1) { $r = $c; $g = $x; $b = 0; }
    elseif ($hp >= 1 && $hp < 2) { $r = $x; $g = $c; $b = 0; }
    elseif ($hp >= 2 && $hp < 3) { $r = 0; $g = $c; $b = $x; }
    elseif ($hp >= 3 && $hp < 4) { $r = 0; $g = $x; $b = $c; }
    elseif ($hp >= 4 && $hp < 5) { $r = $x; $g = 0; $b = $c; }
    else { $r = $c; $g = 0; $b = $x; }
    $m = $l - $c/2;
    return [
        (int)round(($r + $m) * 255),
        (int)round(($g + $m) * 255),
        (int)round(($b + $m) * 255)
    ];
}

// --- Main generator
function generate_identicon($seed, $size = 140, $grid = 7, $padding = 12) {
    // extra salt om gelijknamige seeds iets te diversifiëren op site-niveau
    $salt = "SALT_2025_Overhoorder_🔥_da4bb2a464de4b2393917cb238f31e07b49ee4e695ce89c54efa29c61c44bfdd";
    $hash = hash('sha256', $seed . $salt);

    // Afmetingen
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imageantialias($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    // Kies kleuren uit hash (deterministisch)
    $h = hexdec(substr($hash, 0, 2)) / 255 * 360;                     // hue 0..360
    $s = 60 + (hexdec(substr($hash, 2, 2)) % 26);                    // sat 60..85
    $l_fg = 40 + (hexdec(substr($hash, 4, 2)) % 20);                  // fg lightness 40..59
    $l_bg = 92 - (hexdec(substr($hash, 6, 2)) % 6);                   // bg lightness ~86..92

    $fg_rgb = hsl_to_rgb($h, $s, $l_fg);
    $bg_rgb = hsl_to_rgb(fmod($h + 25, 360), max(20, $s-20), $l_bg); // zachte bg variant

    $fg = imagecolorallocate($img, $fg_rgb[0], $fg_rgb[1], $fg_rgb[2]);
    $bg = imagecolorallocate($img, $bg_rgb[0], $bg_rgb[1], $bg_rgb[2]);

    // achtergrond vullen (lichte vulling)
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);

    // subtiele noise (texture) — laag alpha zodat het niet overheerst
    for ($i = 0; $i < intval($size * $size / 800); $i++) {
        $nx = rand(0, $size-1);
        $ny = rand(0, $size-1);
        $alpha = rand(90, 115);
        $ncol = imagecolorallocatealpha($img, 255, 255, 255, $alpha);
        imagesetpixel($img, $nx, $ny, $ncol);
    }

    // Grid berekenen en centreren
    $available = $size - ($padding * 2);
    $cell = floor($available / $grid);
    $gridWidthPx = $cell * $grid;
    $offsetX = (int)(($size - $gridWidthPx) / 2);
    $offsetY = (int)(($size - $gridWidthPx) / 2);

    // bouw 7x7 bool grid symmetrisch (meer variatie door gebruik van bytes)
    $gridArr = array_fill(0, $grid, array_fill(0, $grid, false));
    $hashLen = strlen($hash);
    for ($y = 0; $y < $grid; $y++) {
        for ($x = 0; $x < ceil($grid / 2); $x++) {
            // kies index in hash op basis van positie
            $idx = ($y * $grid + $x) % $hashLen;
            $val = hexdec($hash[$idx]);
            // extra mix met volgende nibble zodat patronen minder eenvoudig spiegelt
            $val ^= hexdec($hash[($idx+3) % $hashLen]);
            $on = ($val % 2) === 1;

            $gridArr[$y][$x] = $on;
            $gridArr[$y][$grid - 1 - $x] = $on; // mirror
        }
    }

    // extra pattern-layer (lijnen/chevrons/circles) bepaald door hash byte
    $patternType = hexdec(substr($hash, 10, 2)) % 4; // 0-none,1-diags,2-cross,3-circles

    // teken cellen
    $radius = max(0, (int)round($cell * 0.12)); // kleine ronde hoek
    for ($y = 0; $y < $grid; $y++) {
        for ($x = 0; $x < $grid; $x++) {
            if ($gridArr[$y][$x]) {
                $x1 = $offsetX + $x * $cell;
                $y1 = $offsetY + $y * $cell;
                $x2 = $x1 + $cell;
                $y2 = $y1 + $cell;

                // filled rounded rect (approx) — hoek door ellipse + rect
                imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $fg);
                imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $fg);
                imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $fg);
                imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $fg);
                imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $fg);
                imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $fg);
            } else {
                // kleine accent binnen lege cellen: subtiele lijnen op sommige lege cellen
                $hIdx = hexdec($hash[($y*$grid + $x) % $hashLen]);
                if (($hIdx % 13) === 0) {
                    $x1 = $offsetX + $x * $cell;
                    $y1 = $offsetY + $y * $cell;
                    $x2 = $x1 + $cell;
                    $y2 = $y1 + $cell;
                    imagesetthickness($img, max(1, (int)($cell/12)));
                    imageline($img, $x1+2, $y1+2, $x2-2, $y2-2, imagecolorallocatealpha($img, 255,255,255,80));
                    imagesetthickness($img, 1);
                }
            }
        }
    }

    // extra patroon overlay
    $overlayCol = imagecolorallocatealpha($img, max(0,$fg_rgb[0]-30), max(0,$fg_rgb[1]-30), max(0,$fg_rgb[2]-30), 90);
    switch ($patternType) {
        case 1:
            // diagonale lijnen
            for ($i = -$grid; $i < $grid*2; $i+=1) {
                $sx = $offsetX + ($i)*$cell;
                imageline($img, $sx, $offsetY, $sx + $gridWidthPx, $offsetY + $gridWidthPx, $overlayCol);
            }
            break;
        case 2:
            // kruis
            imageline($img, $offsetX, $offsetY, $offsetX + $gridWidthPx, $offsetY + $gridWidthPx, $overlayCol);
            imageline($img, $offsetX + $gridWidthPx, $offsetY, $offsetX, $offsetY + $gridWidthPx, $overlayCol);
            break;
        case 3:
            // cirkels in cell centers
            for ($y = 0; $y < $grid; $y++) {
                for ($x = 0; $x < $grid; $x++) {
                    $cx = $offsetX + $x * $cell + $cell/2;
                    $cy = $offsetY + $y * $cell + $cell/2;
                    imagearc($img, (int)$cx, (int)$cy, (int)($cell*0.8), (int)($cell*0.8), 0, 360, $overlayCol);
                }
            }
            break;
        default:
            // geen overlay
            break;
    }

    // halo ring signature (subtiel), voor Overhoorder herkenbaarheid
    $haloCol = imagecolorallocatealpha($img, max(0,$fg_rgb[0]-10), max(0,$fg_rgb[1]-10), max(0,$fg_rgb[2]-10), 110);
    imagesetthickness($img, max(1, (int)($size/120)));
    $haloRadius = (int)($gridWidthPx * 1.08);
    imageellipse($img, $size/2, $size/2, $haloRadius, $haloRadius, $haloCol);
    imagesetthickness($img, 1);

    // ronde uitsnede / mask (optioneel) — we maken het rond zodat het mooi in avatars past
    $final = imagecreatetruecolor($size, $size);
    imagesavealpha($final, true);
    imagefill($final, 0, 0, $transparent);

    $cx = $size/2; $cy = $size/2;
    $radiusMask = (int)($gridWidthPx * 0.95 / 2) + $padding/2;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $dx = $x - $cx; $dy = $y - $cy;
            if (($dx*$dx + $dy*$dy) <= ($radiusMask*$radiusMask)) {
                $colorIndex = imagecolorat($img, $x, $y);
                imagesetpixel($final, $x, $y, $colorIndex);
            } else {
                imagesetpixel($final, $x, $y, $transparent);
            }
        }
    }

    header('Content-Type: image/png');
    imagepng($final);
    imagedestroy($img);
    imagedestroy($final);
    exit;
}

// --- Aanroep ---
$seed = isset($_GET['seed']) ? (string)$_GET['seed'] : 'anonymous';
$size = isset($_GET['size']) ? max(40, intval($_GET['size'])) : 140;
$grid = isset($_GET['grid']) ? max(5, intval($_GET['grid'])) : 7;
$padding = isset($_GET['padding']) ? max(6, intval($_GET['padding'])) : 12;

generate_identicon($seed, $size, $grid, $padding);
