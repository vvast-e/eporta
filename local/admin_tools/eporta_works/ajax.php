<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaWorksJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaWorksUserHasAccess()) {
    eportaWorksJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}
if (EPORTA_WORKS_IBLOCK_ID <= 0) {
    eportaWorksJsonFail('Инфоблок "Наши работы" ещё не создан (запустите scripts/create_iblock_works.php)', 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaWorksJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'list') {
    echo json_encode(['ok' => true, 'items' => eportaWorksAdminList()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    $name = trim((string)($_POST['NAME'] ?? ''));
    if ($name === '') {
        eportaWorksJsonFail('Заголовок обязателен');
    }
    $code = trim((string)($_POST['CODE'] ?? ''));
    if ($code === '') {
        $code = eportaWorksGenerateCode($name);
    }
    $active = ($_POST['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N';
    $sort = (int)($_POST['SORT'] ?? 500);

    $fields = [
        'IBLOCK_ID' => EPORTA_WORKS_IBLOCK_ID,
        'NAME' => $name,
        'CODE' => $code,
        'ACTIVE' => $active,
        'SORT' => $sort,
        'PREVIEW_TEXT' => (string)($_POST['PREVIEW_TEXT'] ?? ''),
        'PREVIEW_TEXT_TYPE' => 'text',
        'PROPERTY_VALUES' => [
            'CITY' => (string)($_POST['CITY'] ?? ''),
            'COLLECTION' => (string)($_POST['COLLECTION'] ?? ''),
        ],
    ];

    $elObj = new CIBlockElement;
    if ($elementId > 0) {
        $ok = $elObj->Update($elementId, $fields);
        if (!$ok) {
            eportaWorksJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
        }
    } else {
        $elementId = $elObj->Add($fields);
        if (!$elementId) {
            eportaWorksJsonFail('Ошибка создания: ' . $elObj->LAST_ERROR, 500);
        }
    }

    echo json_encode(['ok' => true, 'element_id' => $elementId, 'code' => $code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'upload') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    if ($elementId <= 0 || !eportaWorksGet($elementId)) {
        eportaWorksJsonFail('Сначала сохраните работу, потом загружайте картинку');
    }

    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        eportaWorksJsonFail('Файл не загружен');
    }

    $allowedExt = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        eportaWorksJsonFail('Допустимые форматы: JPG, PNG');
    }
    if ($_FILES['image']['size'] > 8 * 1024 * 1024) {
        eportaWorksJsonFail('Файл слишком большой (максимум 8 МБ)');
    }

    $tmpDir = eportaWorksTmpDir();
    $tmpPath = $tmpDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $tmpPath)) {
        eportaWorksJsonFail('Не удалось сохранить загруженный файл');
    }
    if (!@getimagesize($tmpPath)) {
        @unlink($tmpPath);
        eportaWorksJsonFail('Файл повреждён или не является изображением');
    }

    $fileArray = CFile::MakeFileArray($tmpPath);
    if (!$fileArray) {
        @unlink($tmpPath);
        eportaWorksJsonFail('Не удалось подготовить файл для сохранения');
    }

    // WebP для PREVIEW_PICTURE — если на проекте уже есть общий хук
    // eportaOnIBlockElementSaveGenerateWebp (local/php_interface/init.php, см. eporta_articles/
    // eporta_banners), добавить IBLOCK_ID сюда в его whitelist отдельным пунктом деплоя.
    $elObj = new CIBlockElement;
    $ok = $elObj->Update($elementId, [
        'PREVIEW_PICTURE' => $fileArray,
    ]);
    @unlink($tmpPath);
    if (!$ok) {
        eportaWorksJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
    }

    $el = CIBlockElement::GetByID($elementId)->GetNext();
    $imgPath = $el && $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
    echo json_encode(['ok' => true, 'image' => $imgPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    if ($elementId <= 0) {
        eportaWorksJsonFail('Некорректный ID');
    }
    $ok = (new CIBlockElement)->Delete($elementId);
    if (!$ok) {
        eportaWorksJsonFail('Не удалось удалить работу', 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaWorksJsonFail('Неизвестное действие');
