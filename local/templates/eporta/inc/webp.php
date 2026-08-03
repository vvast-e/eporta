<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// WebP-варианты генерируются заранее — разовым батчем (scripts/import/convert_webp.php)
// для существующих товаров и хуком в eportaImportDownloadImage для новых. Здесь только
// раздача уже готовых файлов рядом с оригиналом, без конвертации на лету: nginx
// image_filter на проде умеет resize/quality/rotate, но не смену формата в WebP
// (см. project-память по Этапу 2 оптимизации, 2026-08-03).

if (!function_exists("eportaWebpVariant")) {
function eportaWebpVariant(string $src): ?string {
	$ext = pathinfo($src, PATHINFO_EXTENSION);
	if ($ext === "") return null;
	$webp = substr($src, 0, -strlen($ext)) . "webp";
	return is_file($_SERVER["DOCUMENT_ROOT"] . $webp) ? $webp : null;
}
}

// Уменьшенный вариант (max-width 480) под сетку каталога — второе число в имени файла,
// не отдельная папка, чтобы не плодить структуру рядом с CFile-хэшами.
if (!function_exists("eportaSmallVariant")) {
function eportaSmallVariant(string $src): array {
	$ext = pathinfo($src, PATHINFO_EXTENSION);
	if ($ext === "") return ["jpg" => null, "webp" => null];
	$base = substr($src, 0, -strlen($ext) - 1);
	$docRoot = $_SERVER["DOCUMENT_ROOT"];
	$smallJpg = "$base.480.jpg";
	$smallWebp = "$base.480.webp";
	return [
		"jpg" => is_file($docRoot . $smallJpg) ? $smallJpg : null,
		"webp" => is_file($docRoot . $smallWebp) ? $smallWebp : null,
	];
}
}

if (!function_exists("eportaImgDims")) {
function eportaImgDims(string $src): array {
	$disk = $_SERVER["DOCUMENT_ROOT"] . $src;
	$size = is_file($disk) ? @getimagesize($disk) : false;
	return $size ? ["width" => $size[0], "height" => $size[1]] : ["width" => null, "height" => null];
}
}

// sizes одинаковый для всех раскладок 4/5/6 колонок — переключатель колонок клиентский
// (CSS-переменная, не breakpoint), точный расчёт под каждое значение не окупает сложность,
// достаточно приблизительной подсказки браузеру.
if (!defined("EPORTA_GRID_SIZES")) {
	define("EPORTA_GRID_SIZES", "(max-width:640px) 45vw, (max-width:1200px) 30vw, 22vw");
}

// Печатает <picture><source type=webp>[<source jpg>]<img>. $attrs — произвольные
// HTML-атрибуты <img> (loading/decoding/class/style/id/onclick/data-*); width/height
// проставляются автоматически из реального файла, если не заданы явно.
// $small=true — добавить srcset/sizes с уменьшенным (480px) вариантом для сетки каталога.
if (!function_exists("eportaPicture")) {
function eportaPicture(string $src, string $alt, array $attrs = [], bool $small = false): void {
	$webp = eportaWebpVariant($src);
	$dims = eportaImgDims($src);
	$fullWidth = $dims["width"] ?: 1200;
	if ($dims["width"] && !isset($attrs["width"])) {
		$attrs["width"] = $dims["width"];
		$attrs["height"] = $dims["height"];
	}

	echo "<picture>";
	if ($small) {
		$sm = eportaSmallVariant($src);
		$webpSrcset = [];
		if ($sm["webp"]) $webpSrcset[] = htmlspecialcharsbx($sm["webp"]) . " 480w";
		if ($webp) $webpSrcset[] = htmlspecialcharsbx($webp) . " {$fullWidth}w";
		if ($webpSrcset) {
			echo '<source type="image/webp" srcset="' . implode(", ", $webpSrcset) . '" sizes="' . EPORTA_GRID_SIZES . '">';
		}
		if ($sm["jpg"]) {
			echo '<source srcset="' . htmlspecialcharsbx($sm["jpg"]) . ' 480w, ' . htmlspecialcharsbx($src) . " {$fullWidth}w" . '" sizes="' . EPORTA_GRID_SIZES . '">';
		}
	} elseif ($webp) {
		echo '<source type="image/webp" srcset="' . htmlspecialcharsbx($webp) . '">';
	}

	$attrStr = "";
	foreach ($attrs as $k => $v) {
		if ($v === null || $v === "") continue;
		$attrStr .= " " . $k . '="' . htmlspecialcharsbx((string)$v) . '"';
	}
	echo '<img src="' . htmlspecialcharsbx($src) . '" alt="' . htmlspecialcharsbx($alt) . '"' . $attrStr . ">";
	echo "</picture>";
}
}
