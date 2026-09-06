<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaShowcaseJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaShowcaseUserHasAccess()) {
    eportaShowcaseJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaShowcaseJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'set_showcase') {
    $selectedId = (int)($_POST['id'] ?? 0);
    $allIdsRaw = $_POST['all_ids'] ?? '';
    $allIds = array_filter(array_map('intval', explode(',', (string)$allIdsRaw)));

    if (!$selectedId || !in_array($selectedId, $allIds, true)) {
        eportaShowcaseJsonFail('Некорректные данные варианта');
    }

    // Все ID должны реально принадлежать IBLOCK 19 — защита от произвольной записи по чужим ID.
    $verifyRes = CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'ID' => $allIds], false, false, ['ID']);
    $verifiedIds = [];
    while ($row = $verifyRes->Fetch()) {
        $verifiedIds[] = (int)$row['ID'];
    }
    if (count($verifiedIds) !== count($allIds) || !in_array($selectedId, $verifiedIds, true)) {
        eportaShowcaseJsonFail('Некорректные данные варианта');
    }

    $ok = eportaShowcaseSetVariant($selectedId, $verifiedIds);
    if (!$ok) {
        eportaShowcaseJsonFail('Ошибка сохранения', 500);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set_show_in_list') {
    $variantId = (int)($_POST['id'] ?? 0);
    $show = ($_POST['show'] ?? '') === '1';

    if (!$variantId) {
        eportaShowcaseJsonFail('Некорректные данные варианта');
    }

    // ID должен реально принадлежать IBLOCK 19 — защита от произвольной записи по чужим ID.
    $verifyRow = CIBlockElement::GetList([], ['IBLOCK_ID' => EPORTA_SHOWCASE_IBLOCK_ID, 'ID' => $variantId], false, false, ['ID'])->Fetch();
    if (!$verifyRow) {
        eportaShowcaseJsonFail('Некорректные данные варианта');
    }

    $ok = eportaShowcaseSetShowInList($variantId, $show);
    if (!$ok) {
        eportaShowcaseJsonFail('Ошибка сохранения', 500);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaShowcaseJsonFail('Неизвестное действие');
