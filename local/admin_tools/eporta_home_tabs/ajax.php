<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/eporta_home_tabs_common.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaHomeTabsJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaHomeTabsUserHasAccess()) {
    eportaHomeTabsJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaHomeTabsJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
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
        eportaHomeTabsJsonFail('Некорректные данные формы');
    }
    $config = eportaHomeTabsSanitizeConfig($raw);
    eportaHomeTabsSaveConfig($config);
    echo json_encode(['ok' => true, 'config' => $config], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

eportaHomeTabsJsonFail('Неизвестное действие');
