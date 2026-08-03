<?php
// Общая логика генерации WebP/уменьшенного варианта для одного файла — используется
// и разовым батчем (scripts/import/convert_webp.php, весь upload/iblock целиком), и
// хуком импортёра (eportaImportOneProduct, только что сохранённые CFile) — чтобы
// не дублировать GD-код в двух местах. Чистый PHP+GD, без зависимостей от Bitrix.

if (!defined('EPORTA_WEBP_QUALITY')) define('EPORTA_WEBP_QUALITY', 82);
if (!defined('EPORTA_WEBP_SMALL_MAX_WIDTH')) define('EPORTA_WEBP_SMALL_MAX_WIDTH', 480);

if (!function_exists('eportaWebpLoadImage')) {
function eportaWebpLoadImage(string $path, string $ext) {
    switch (strtolower($ext)) {
        case 'jpg':
        case 'jpeg':
            return @imagecreatefromjpeg($path);
        case 'png':
            return @imagecreatefrompng($path);
        case 'gif':
            return @imagecreatefromgif($path);
        default:
            return false;
    }
}
}

if (!function_exists('eportaWebpFlattenToWhite')) {
function eportaWebpFlattenToWhite($img, int $w, int $h) {
    $flat = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($flat, 255, 255, 255);
    imagefilledrectangle($flat, 0, 0, $w, $h, $white);
    imagealphablending($flat, true);
    imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
    return $flat;
}
}

if (!function_exists('eportaWebpResize')) {
function eportaWebpResize($img, int $srcW, int $srcH, int $maxW) {
    $dstW = $maxW;
    $dstH = (int)round($srcH * ($maxW / $srcW));
    $dst = imagecreatetruecolor($dstW, $dstH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    return $dst;
}
}

// $diskPath — абсолютный путь к оригиналу (jpg/png/gif). Генерирует рядом path.webp,
// и, если исходник шире EPORTA_WEBP_SMALL_MAX_WIDTH, ещё path.480.jpg + path.480.webp.
// Пропускает файлы, для которых все нужные варианты уже существуют. Возвращает false
// при ошибке чтения/кодирования, не бросает исключений — некритичная оптимизация,
// не должна ронять импорт.
if (!function_exists('eportaWebpConvertFile')) {
function eportaWebpConvertFile(string $diskPath): bool {
    if (!is_file($diskPath)) return false;
    $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) return false;

    $base = substr($diskPath, 0, -(strlen($ext) + 1));
    $webpPath = "$base.webp";
    $smallJpgPath = "$base.480.jpg";
    $smallWebpPath = "$base.480.webp";

    $srcSize = @getimagesize($diskPath);
    if (!$srcSize) return false;
    [$w, $h] = $srcSize;

    $needFull = !is_file($webpPath);
    $needSmall = $w > EPORTA_WEBP_SMALL_MAX_WIDTH && (!is_file($smallJpgPath) || !is_file($smallWebpPath));
    if (!$needFull && !$needSmall) return true;

    $img = eportaWebpLoadImage($diskPath, $ext);
    if (!$img) return false;
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $ok = true;
    if ($needFull) {
        $ok = $ok && @imagewebp($img, $webpPath, EPORTA_WEBP_QUALITY);
    }
    if ($needSmall) {
        $small = eportaWebpResize($img, $w, $h, EPORTA_WEBP_SMALL_MAX_WIDTH);
        if ($ext === 'png' || $ext === 'gif') {
            $flat = eportaWebpFlattenToWhite($small, imagesx($small), imagesy($small));
            $ok = $ok && @imagejpeg($flat, $smallJpgPath, EPORTA_WEBP_QUALITY);
            imagedestroy($flat);
        } else {
            $ok = $ok && @imagejpeg($small, $smallJpgPath, EPORTA_WEBP_QUALITY);
        }
        $ok = $ok && @imagewebp($small, $smallWebpPath, EPORTA_WEBP_QUALITY);
        imagedestroy($small);
    }

    imagedestroy($img);
    return $ok;
}
}
