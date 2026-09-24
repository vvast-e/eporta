<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaReviewsJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaReviewsUserHasAccess()) {
    eportaReviewsJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}
if (EPORTA_REVIEWS_IBLOCK_ID <= 0) {
    eportaReviewsJsonFail('Инфоблок "Отзывы" ещё не создан (запустите scripts/create_iblock_reviews.php)', 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaReviewsJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'list') {
    echo json_encode(['ok' => true, 'items' => eportaReviewsAdminList()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    $name = trim((string)($_POST['NAME'] ?? ''));
    if ($name === '') {
        eportaReviewsJsonFail('Имя автора обязательно');
    }
    $text = trim((string)($_POST['PREVIEW_TEXT'] ?? ''));
    if ($text === '') {
        eportaReviewsJsonFail('Текст отзыва обязателен');
    }
    $rating = (int)($_POST['RATING'] ?? 5);
    $rating = max(1, min(5, $rating));
    $active = ($_POST['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N';
    $activeFrom = trim((string)($_POST['ACTIVE_FROM'] ?? ''));

    $fields = [
        'IBLOCK_ID' => EPORTA_REVIEWS_IBLOCK_ID,
        'NAME' => $name,
        'ACTIVE' => $active,
        'PREVIEW_TEXT' => $text,
        'PREVIEW_TEXT_TYPE' => 'text',
        'PROPERTY_VALUES' => [
            'RATING' => (string)$rating,
            'CITY' => (string)($_POST['CITY'] ?? ''),
        ],
    ];
    if ($activeFrom !== '') {
        $fields['ACTIVE_FROM'] = $activeFrom;
    }

    $elObj = new CIBlockElement;
    if ($elementId > 0) {
        $ok = $elObj->Update($elementId, $fields);
        if (!$ok) {
            eportaReviewsJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
        }
    } else {
        if ($activeFrom === '') {
            $fields['ACTIVE_FROM'] = date('d.m.Y');
        }
        $elementId = $elObj->Add($fields);
        if (!$elementId) {
            eportaReviewsJsonFail('Ошибка создания: ' . $elObj->LAST_ERROR, 500);
        }
    }

    echo json_encode(['ok' => true, 'element_id' => $elementId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    if ($elementId <= 0) {
        eportaReviewsJsonFail('Некорректный ID');
    }
    $ok = (new CIBlockElement)->Delete($elementId);
    if (!$ok) {
        eportaReviewsJsonFail('Не удалось удалить отзыв', 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaReviewsJsonFail('Неизвестное действие');
