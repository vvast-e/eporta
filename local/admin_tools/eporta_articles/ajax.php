<?php
define('NO_KEEP_STATISTIC_RAW_DATA', true);
define('STOP_STATISTICS', true);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require(__DIR__ . '/lib.php');

CModule::IncludeModule('iblock');

header('Content-Type: application/json; charset=utf-8');

function eportaArticlesJsonFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!eportaArticlesUserHasAccess()) {
    eportaArticlesJsonFail('Нет доступа: требуются права на запись в каталог (IBLOCK 19)', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    eportaArticlesJsonFail('Некорректный запрос (сессия истекла, обновите страницу)', 400);
}

$action = $_POST['action'] ?? '';

if ($action === 'list') {
    echo json_encode(['ok' => true, 'items' => eportaArticlesList()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    $name = trim((string)($_POST['NAME'] ?? ''));
    if ($name === '') {
        eportaArticlesJsonFail('Заголовок обязателен');
    }
    $code = trim((string)($_POST['CODE'] ?? ''));
    if ($code === '') {
        $code = eportaArticlesGenerateCode($name);
    }
    $active = ($_POST['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N';
    // DETAIL_TEXT приходит уже готовым HTML из contenteditable-редактора (см. index.php) —
    // TEXT_TYPE=>'html' обязателен, иначе Bitrix экранирует теги на выводе (тот же нюанс, что
    // и в catalog.element/.default/template.php про импортный DETAIL_TEXT).
    $fields = [
        'IBLOCK_ID' => EPORTA_ARTICLES_IBLOCK_ID,
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
        $ok = $elObj->Update($elementId, $fields);
        if (!$ok) {
            eportaArticlesJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
        }
    } else {
        $elementId = $elObj->Add($fields);
        if (!$elementId) {
            eportaArticlesJsonFail('Ошибка создания: ' . $elObj->LAST_ERROR, 500);
        }
    }

    echo json_encode(['ok' => true, 'element_id' => $elementId, 'code' => $code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'upload') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    if ($elementId <= 0 || !eportaArticlesGet($elementId)) {
        eportaArticlesJsonFail('Сначала сохраните статью, потом загружайте картинку');
    }

    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        eportaArticlesJsonFail('Файл не загружен');
    }

    $allowedExt = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        eportaArticlesJsonFail('Допустимые форматы: JPG, PNG');
    }
    if ($_FILES['image']['size'] > 8 * 1024 * 1024) {
        eportaArticlesJsonFail('Файл слишком большой (максимум 8 МБ)');
    }

    $tmpDir = eportaArticlesTmpDir();
    $tmpPath = $tmpDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $tmpPath)) {
        eportaArticlesJsonFail('Не удалось сохранить загруженный файл');
    }
    if (!@getimagesize($tmpPath)) {
        @unlink($tmpPath);
        eportaArticlesJsonFail('Файл повреждён или не является изображением');
    }

    $fileArray = CFile::MakeFileArray($tmpPath);
    if (!$fileArray) {
        @unlink($tmpPath);
        eportaArticlesJsonFail('Не удалось подготовить файл для сохранения');
    }

    // WebP для DETAIL_PICTURE генерируется автоматически общим хуком
    // eportaOnIBlockElementSaveGenerateWebp (local/php_interface/init.php) — IBLOCK 28 добавлен
    // в его whitelist, отдельно вызывать не нужно.
    $elObj = new CIBlockElement;
    $ok = $elObj->Update($elementId, [
        'PREVIEW_PICTURE' => $fileArray,
        'DETAIL_PICTURE' => $fileArray,
    ]);
    @unlink($tmpPath);
    if (!$ok) {
        eportaArticlesJsonFail('Ошибка сохранения: ' . $elObj->LAST_ERROR, 500);
    }

    $el = CIBlockElement::GetByID($elementId)->GetNext();
    $imgPath = $el && $el['PREVIEW_PICTURE'] ? CFile::GetPath($el['PREVIEW_PICTURE']) : '';
    echo json_encode(['ok' => true, 'image' => $imgPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    $elementId = (int)($_POST['element_id'] ?? 0);
    if ($elementId <= 0) {
        eportaArticlesJsonFail('Некорректный ID');
    }
    $ok = (new CIBlockElement)->Delete($elementId);
    if (!$ok) {
        eportaArticlesJsonFail('Не удалось удалить статью', 500);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

eportaArticlesJsonFail('Неизвестное действие');
