<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaPromoJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaPromoUserHasAccess()) {
    eportaPromoJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaPromoJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'list') {
    echo json_encode(['ok' => true, 'items' => eportaPromoList()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    $name = trim((string)($_POST['NAME'] ?? ''));
    if ($name === '') {
        eportaPromoJsonFail('Заголовок обязателен');
    }
    $code = trim((string)($_POST['CODE'] ?? ''));
    if ($code === '') {
        $code = eportaPromoGenerateCode($name);
    }
    $active = ($_POST['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N';
    // DETAIL_TEXT приходит уже готовым HTML из contenteditable-редактора (см. index.php) —
    // TEXT_TYPE=>'html' обязателен, иначе Bitrix экранирует теги на выводе.
    $fields = [
        'IBLOCK_ID' => EPORTA_PROMO_IBLOCK_ID,
        'NAME' => $name,
        'CODE' => $code,
        'ACTIVE' => $active,
        'PREVIEW_TEXT' => (string)($_POST['PREVIEW_TEXT'] ?? ''),
        'PREVIEW_TEXT_TYPE' => 'text',
        'DETAIL_TEXT' => (string)($_POST['DETAIL_TEXT'] ?? ''),
        'DETAIL_TEXT_TYPE' => 'html',
    ];

    $elObj = new CIBlockElement;
    if ($elementId > 0) {
        // IDOR-защита: без этой проверки чужой element_id из другого инфоблока (например,
        // товара из IBLOCK 19 или статьи из IBLOCK 28) был бы принят Update() как есть —
        // включая смену его IBLOCK_ID на 29, что молча портит данные другого раздела.
        if (!eportaPromoGet($elementId)) {
            eportaPromoJsonFail('Акция с таким ID не найдена', 404);
        }
        $ok = $elObj->Update($elementId, $fields);
        if (!$ok) {
            eportaPromoJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
        }
    } else {
        $elementId = $elObj->Add($fields);
        if (!$elementId) {
            eportaPromoJsonFail('Ошибка создания: ' . $elObj->LAST_ERROR, 500);
        }
    }

    echo json_encode(['ok' => true, 'element_id' => $elementId, 'code' => $code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'upload') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    if ($elementId <= 0 || !eportaPromoGet($elementId)) {
        eportaPromoJsonFail('Сначала сохраните акцию, потом загружайте картинку');
    }

    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        eportaPromoJsonFail('Файл не загружен');
    }

    $allowedExt = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        eportaPromoJsonFail('Допустимые форматы: JPG, PNG');
    }
    if ($_FILES['image']['size'] > 8 * 1024 * 1024) {
        eportaPromoJsonFail('Файл слишком большой (максимум 8 МБ)');
    }

    $tmpDir = eportaPromoTmpDir();
    $tmpPath = $tmpDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $tmpPath)) {
        eportaPromoJsonFail('Не удалось сохранить загруженный файл');
    }
    if (!@getimagesize($tmpPath)) {
        @unlink($tmpPath);
        eportaPromoJsonFail('Файл повреждён или не является изображением');
    }

    $fileArray = CFile::MakeFileArray($tmpPath);
    if (!$fileArray) {
        @unlink($tmpPath);
        eportaPromoJsonFail('Не удалось подготовить файл для сохранения');
    }

    // WebP для DETAIL_PICTURE генерируется автоматически общим хуком
    // eportaOnIBlockElementSaveGenerateWebp (local/php_interface/init.php) — при подключении
    // этого раздела нужно добавить IBLOCK_ID акций в его whitelist (по аналогии с IBLOCK 28
    // статей), иначе конвертация просто не сработает.
    $elObj = new CIBlockElement;
    $ok = $elObj->Update($elementId, [
        'PREVIEW_PICTURE' => $fileArray,
        'DETAIL_PICTURE' => $fileArray,
    ]);
    @unlink($tmpPath);
    if (!$ok) {
        eportaPromoJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
    }

    $el = CIBlockElement::GetByID($elementId)->GetNext();
    $imgPath = $el && $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
    echo json_encode(['ok' => true, 'image' => $imgPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'upload_inline') {
    // Картинка ВНУТРИ текста акции (не превью) — не привязана к элементу инфоблока, поэтому
    // работает даже для ещё не сохранённой (новой) акции. Сохраняется напрямую CFile::SaveFile
    // в /upload/promo_inline/, без связи с конкретным ID элемента.
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        eportaPromoJsonFail('Файл не загружен');
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        eportaPromoJsonFail('Допустимые форматы: JPG, PNG, WEBP');
    }
    if ($_FILES['image']['size'] > 8 * 1024 * 1024) {
        eportaPromoJsonFail('Файл слишком большой (максимум 8 МБ)');
    }

    $tmpDir = eportaPromoTmpDir();
    $tmpPath = $tmpDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $tmpPath)) {
        eportaPromoJsonFail('Не удалось сохранить загруженный файл');
    }
    if (!@getimagesize($tmpPath)) {
        @unlink($tmpPath);
        eportaPromoJsonFail('Файл повреждён или не является изображением');
    }

    $fileArray = CFile::MakeFileArray($tmpPath);
    if (!$fileArray) {
        @unlink($tmpPath);
        eportaPromoJsonFail('Не удалось подготовить файл для сохранения');
    }

    $fileId = \CFile::SaveFile($fileArray, 'promo_inline');
    @unlink($tmpPath);
    if (!$fileId) {
        eportaPromoJsonFail('Не удалось сохранить файл', 500);
    }

    $imgPath = \CFile::GetPath($fileId);
    echo json_encode(['ok' => true, 'image' => $imgPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    if ($elementId <= 0) {
        eportaPromoJsonFail('Некорректный ID');
    }
    // Та же IDOR-защита, что и в 'save' — без неё можно было бы удалить произвольный элемент
    // любого инфоблока (товар, статью), просто подставив его ID.
    if (!eportaPromoGet($elementId)) {
        eportaPromoJsonFail('Акция с таким ID не найдена', 404);
    }
    $ok = (new CIBlockElement)->Delete($elementId);
    if (!$ok) {
        eportaPromoJsonFail('Не удалось удалить акцию', 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaPromoJsonFail('Неизвестное действие');
