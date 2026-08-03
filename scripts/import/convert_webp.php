<?php
// Разовый (и повторно безопасный — пропускает уже готовые файлы) батч: генерирует
// WebP-версии всех товарных фото в upload/iblock, плюс уменьшенный (max-width 480px)
// вариант в jpg+webp для сетки каталога. Не трогает оригиналы — только дописывает
// файлы рядом. GD-логика общая с хуком импортёра — см. eporta_import/webp_convert.php.
//
// Запуск на проде: php scripts/import/convert_webp.php [путь-к-upload/iblock]
//
// На лету через nginx image_filter конвертация в WebP невозможна (модуль умеет
// только resize/quality/rotate, не смену формата), поэтому варианты готовятся заранее
// (project_perf_stage1_2026_08_03 — Этап 2 роадмапа оптимизации).

require_once __DIR__ . '/../../local/admin_tools/eporta_import/webp_convert.php';

$root = $argv[1] ?? (__DIR__ . '/../../upload/iblock');
$root = rtrim($root, '/');
if (!is_dir($root)) {
	fwrite(STDERR, "Директория не найдена: $root\n");
	exit(1);
}

$stats = ['processed' => 0, 'skipped' => 0, 'errors' => 0, 'bytesBefore' => 0, 'bytesAfter' => 0];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $fileInfo) {
	$path = $fileInfo->getPathname();
	$ext = strtolower($fileInfo->getExtension());
	if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) continue;
	// Пропускаем уже сгенерированные уменьшенные варианты (path.480.jpg и т.п.), если
	// скрипт вдруг запустят повторно над каталогом, где они уже лежат.
	if (preg_match('/\.480\.(jpg|webp)$/i', $path)) continue;

	$webpPath = substr($path, 0, -(strlen($ext) + 1)) . '.webp';
	$alreadyDone = is_file($webpPath);
	$bytesBefore = filesize($path);

	if (eportaWebpConvertFile($path)) {
		if (!$alreadyDone && is_file($webpPath)) {
			$stats['processed']++;
			$stats['bytesBefore'] += $bytesBefore;
			$stats['bytesAfter'] += filesize($webpPath);
		} else {
			$stats['skipped']++;
		}
	} else {
		$stats['errors']++;
		fwrite(STDERR, "Не удалось обработать: $path\n");
	}
}

printf(
	"Обработано: %d, пропущено (уже готово): %d, ошибок: %d\nРазмер до: %.1f МБ, после (webp): %.1f МБ (экономия %.0f%%)\n",
	$stats['processed'],
	$stats['skipped'],
	$stats['errors'],
	$stats['bytesBefore'] / 1048576,
	$stats['bytesAfter'] / 1048576,
	$stats['bytesBefore'] > 0 ? (1 - $stats['bytesAfter'] / $stats['bytesBefore']) * 100 : 0
);
