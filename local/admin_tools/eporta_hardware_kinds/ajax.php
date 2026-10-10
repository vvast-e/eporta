<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaHardwareKindsJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaHardwareKindsUserHasAccess()) {
    eportaHardwareKindsJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaHardwareKindsJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $error = null;
    $id = eportaHardwareKindsCreate((string)($_POST['name'] ?? ''), $error);
    if (!$id) {
        eportaHardwareKindsJsonFail($error ?: 'Ошибка создания типа', 422);
    }
    echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        eportaHardwareKindsJsonFail('Некорректный ID типа');
    }
    $error = null;
    $ok = eportaHardwareKindsUpdate($id, (string)($_POST['name'] ?? ''), (int)($_POST['sort'] ?? 500), ($_POST['active'] ?? 'Y') !== 'N', $error);
    if (!$ok) {
        eportaHardwareKindsJsonFail($error ?: 'Ошибка сохранения типа', 422);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaHardwareKindsJsonFail('Неизвестное действие');
