<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaCollectionsJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaCollectionsUserHasAccess()) {
    eportaCollectionsJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaCollectionsJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $error = null;
    $sectionId = eportaCollectionsCreate($name, $description, $error);
    if (!$sectionId) {
        eportaCollectionsJsonFail($error ?: 'Ошибка создания коллекции', 500);
    }
    echo json_encode(['ok' => true, 'id' => $sectionId], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'update') {
    $sectionId = (int)($_POST['id'] ?? 0);
    if ($sectionId <= 0) {
        eportaCollectionsJsonFail('Некорректный ID коллекции');
    }
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $sort = (int)($_POST['sort'] ?? 500);
    $active = ($_POST['active'] ?? 'Y') !== 'N';
    $error = null;
    $ok = eportaCollectionsUpdate($sectionId, $name, $description, $sort, $active, $error);
    if (!$ok) {
        eportaCollectionsJsonFail($error ?: 'Ошибка сохранения коллекции', 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaCollectionsJsonFail('Неизвестное действие');
