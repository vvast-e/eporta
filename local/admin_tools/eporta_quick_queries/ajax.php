<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaQuickQueriesJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaQuickQueriesUserHasAccess()) {
    eportaQuickQueriesJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaQuickQueriesJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'search') {
    $q = (string)($_POST['q'] ?? '');
    echo json_encode(['ok' => true, 'items' => eportaHomeTabsSearchProducts($q)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $raw = json_decode((string)($_POST['config'] ?? ''), true);
    if (!is_array($raw)) {
        eportaQuickQueriesJsonFail('Некорректные данные формы');
    }
    $list = eportaQuickQueriesSanitizeAndSave($raw, eportaQuickQueriesFilterOptions());
    $allPinned = [];
    foreach ($list as $query) {
        $allPinned = array_merge($allPinned, $query['pinned']);
    }
    $info = eportaHomeTabsGetProductsInfo($allPinned);
    $out = [];
    foreach ($list as $query) {
        $query['url'] = eportaQuickQueryUrl($query);
        $query['pinned'] = array_values(array_filter(array_map(function ($id) use ($info) {
            return $info[$id] ?? null;
        }, $query['pinned'])));
        $out[] = $query;
    }
    echo json_encode(['ok' => true, 'queries' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

eportaQuickQueriesJsonFail('Неизвестное действие');
