<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

header('Content-Type: application/json; charset=utf-8');

function eportaImportJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaImportUserHasAccess()) {
    eportaImportJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaImportJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

// Токен = имя файла в tmp-директории, разрешаем только hex — иначе path traversal
function eportaImportSafeToken(string $token): ?string {
    return preg_match('/^[a-f0-9]{32}$/', $token) ? $token : null;
}

if ($action === 'upload') {
    if (empty($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
        eportaImportJsonFail('Файл не загружен');
    }
    $origName = $_FILES['xlsx_file']['name'];
    if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'xlsx') {
        eportaImportJsonFail('Ожидается файл формата .xlsx');
    }

    $token = bin2hex(random_bytes(16));
    $tmpDir = eportaImportTmpDir();
    $xlsxPath = $tmpDir . '/' . $token . '.xlsx';
    if (!move_uploaded_file($_FILES['xlsx_file']['tmp_name'], $xlsxPath)) {
        eportaImportJsonFail('Не удалось сохранить загруженный файл');
    }

    $grid = eportaImportReadSheet($xlsxPath, 'Тест');
    @unlink($xlsxPath);

    if ($grid === null) {
        eportaImportJsonFail('Не удалось прочитать лист "Тест" — проверьте, что это тот же формат, что и эталонный файл (2026-07-29-Vilis.xlsx)');
    }

    $parsed = eportaImportParseGrid($grid);
    if (!empty($parsed['error']) || !empty($parsed['missing_required'])) {
        $msg = $parsed['error'] ?? ('Отсутствуют обязательные колонки: ' . implode(', ', $parsed['missing_required']));
        eportaImportJsonFail($msg);
    }
    if (empty($parsed['products'])) {
        eportaImportJsonFail('В файле не найдено ни одной строки с заполненным Артикулом');
    }

    file_put_contents($tmpDir . '/' . $token . '.json', json_encode($parsed['products'], JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'ok' => true,
        'token' => $token,
        'total' => count($parsed['products']),
        'headers' => $parsed['headers'],
        'unmapped_headers' => $parsed['unmapped'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'batch') {
    $token = eportaImportSafeToken((string)($_POST['token'] ?? ''));
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $limit = 10;
    if (!$token) {
        eportaImportJsonFail('Некорректный токен сессии импорта');
    }
    $jsonPath = eportaImportTmpDir() . '/' . $token . '.json';
    if (!file_exists($jsonPath)) {
        eportaImportJsonFail('Файл сессии импорта не найден (истёк или уже обработан)');
    }
    $products = json_decode(file_get_contents($jsonPath), true);
    if (!is_array($products)) {
        eportaImportJsonFail('Повреждён файл сессии импорта');
    }

    $total = count($products);
    $batch = array_slice($products, $offset, $limit);
    $results = [];
    foreach ($batch as $p) {
        try {
            $results[] = eportaImportOneProduct($p);
        } catch (Throwable $e) {
            $results[] = ['article' => $p['article'] ?? '', 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    $nextOffset = $offset + count($batch);
    $done = $nextOffset >= $total;
    if ($done) {
        @unlink($jsonPath);
    }

    echo json_encode([
        'ok' => true,
        'results' => $results,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'total' => $total,
        'done' => $done,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaImportJsonFail('Неизвестное действие');
